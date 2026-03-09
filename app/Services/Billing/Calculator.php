<?php

declare(strict_types=1);

namespace App\Services\Billing;

use Illuminate\Support\Facades\DB;

/**
 * Calculator (c.php) — Direct technical implementation of ProjectDescription.md.
 *
 * Mirror Documentation: every PD section ID maps to a method or block below.
 * All consumption values are integers (Litres/Wh). No floats until final cost step.
 * Monetary rounding (2 dp) only at Section 12.0 persistence.
 *
 * PD Section → Method:
 *   1.0  → validateSequentialGate()
 *   2.0  → generateSectors()
 *   3.0  → handleStraddle()
 *   4.0  → applyRemainderMethod()
 *   5.0  → calendar.php (Calendar class; this class uses Calendar)
 *   6.0  → computePeriod() (single public entry)
 *   7.0  → loadTariffTemplate()
 *   8.0  → applyTieredRates()
 *   9.0  → applyFixedCosts()
 *   10.0 → applyCustomerOverrides()
 *   11.0 → computeVat()
 *   12.0 → persistBill()
 *
 * Data chain: bills.tariff_template_id → regions_account_type_cost
 *             bills.account_id         → customer_cost_overrides
 *             bills.meter_id           → meters (water/electricity via template flags)
 */
final class Calculator
{
    public function __construct(
        private Calendar $calendar
    ) {}

    // =========================================================================
    // PD Section 6.0 — Public entry point
    // =========================================================================

    /**
     * Compute usage for an existing bill. Load bill, meter, readings; run gate → sectors → straddle → persist.
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function computePeriod(int $billId): array
    {
        return DB::transaction(function () use ($billId) {
            $bill = $this->loadBill($billId);
            $meter = $this->loadMeter($bill->meter_id);
            $readings = $this->loadReadingsForMeter($bill->meter_id);

            // PD Section 1.0
            $gate = $this->validateSequentialGate($bill, $readings, $meter);
            if (!$gate['allowed']) {
                return [
                    'success' => false,
                    'message' => $gate['blocked_reason'] ?? 'Sequential gate blocked',
                    'data' => ['heal_bill_id' => $gate['heal_bill_id'] ?? null],
                ];
            }

            // PD Section 2.0
            $sectors = $this->generateSectors($readings, $meter);

            // PD Section 3.0 + 4.0: For each sector that straddles period boundaries, split and apply remainder
            $periodStart = $bill->period_start_date ?? null;
            $periodEnd = $bill->period_end_date ?? null;
            if (!$periodStart || !$periodEnd) {
                return ['success' => false, 'message' => 'Bill missing period dates'];
            }
            $billDay = (int) ($bill->billing_day ?? 1);
            $usageL = 0;
            foreach ($sectors as $sector) {
                $boundaries = $this->calendar->boundariesBetween($sector['start'], $sector['end'], $billDay);
                // boundariesBetween() already guarantees all results are strictly inside the sector range,
                // so a non-empty array means the sector straddles at least one period boundary.
                if (!empty($boundaries)) {
                    $subs = $this->handleStraddle($sector, $boundaries, $this->calendar);
                    foreach ($subs as $sub) {
                        if ($this->segmentOverlapsPeriod($sub, $periodStart, $periodEnd)) {
                            $usageL += $sub['usage'];
                        }
                    }
                } else {
                    if ($this->sectorOverlapsPeriod($sector, $periodStart, $periodEnd)) {
                        $usageL += $sector['total_usage'];
                    }
                }
            }

            // PD Section 7.0 — resolve the tariff template for this bill
            $template = $this->loadTariffTemplate($bill);

            // PD Section 8.0 — apply tiered rates to integer usage
            $tiers        = $this->loadTierDefinitions((int) $template->id, $template);
            $chargeResult = $this->applyTieredRates($usageL, $tiers);

            // PD Section 9.0 — fixed cost line items from the template
            $fixedResult  = $this->applyFixedCosts((int) $template->id);

            // PD Section 10.0 — per-account cost overrides
            $overrideResult = $this->applyCustomerOverrides((int) $bill->account_id);

            // PD Section 11.0 — VAT on vatable subtotal only
            $vatRate         = (float) ($template->vat_rate ?? $template->vat_percentage ?? 15.0);
            $fixedVatable    = array_sum(array_map(fn ($f) => $f['is_vatable'] ? $f['amount'] : 0.0, $fixedResult['fixed_breakdown']));
            $overrideVatable = array_sum(array_map(fn ($o) => ($o['is_vatable'] ?? true) ? $o['amount'] : 0.0, $overrideResult['override_breakdown']));
            $vatAmount       = $this->computeVat($chargeResult['usage_charge'] + $fixedVatable + $overrideVatable, $vatRate);

            // PD Section 12.0 — persist full monetary result
            $billTotal = round(
                $chargeResult['usage_charge'] + $fixedResult['fixed_total'] + $overrideResult['override_total'] + $vatAmount,
                2
            );
            $this->persistBill($billId, $usageL, [
                'usage_charge'       => $chargeResult['usage_charge'],
                'tier_breakdown'     => $chargeResult['tier_breakdown'],
                'fixed_total'        => $fixedResult['fixed_total'],
                'fixed_breakdown'    => $fixedResult['fixed_breakdown'],
                'override_total'     => $overrideResult['override_total'],
                'override_breakdown' => $overrideResult['override_breakdown'],
                'vat_amount'         => $vatAmount,
                'bill_total'         => $billTotal,
            ]);

            return [
                'success' => true,
                'message' => 'OK',
                'data'    => [
                    'usage_litres'   => $usageL,
                    'usage_charge'   => $chargeResult['usage_charge'],
                    'fixed_total'    => $fixedResult['fixed_total'],
                    'override_total' => $overrideResult['override_total'],
                    'vat_amount'     => $vatAmount,
                    'bill_total'     => $billTotal,
                ],
            ];
        });
    }

    // =========================================================================
    // PD Section 1.0 — The Sequential Gate
    // =========================================================================

    /**
     * No period may be calculated if the previous period is not reconciled.
     *
     * @param object $bill { meter_id, period_start_date, period_end_date, ... }
     * @param array  $readings list of { reading_date, reading_value }
     * @param object $meter { id, start_reading, start_reading_date, digit_count }
     * @return array{allowed: bool, blocked_reason?: string, heal_bill_id?: int}
     */
    public function validateSequentialGate(object $bill, array $readings, object $meter): array
    {
        $prevBill = $this->findPreviousBill((int) $bill->meter_id, (string) $bill->period_start_date);
        if (!$prevBill) {
            return ['allowed' => true];
        }
        // PD 1.0: N-1 must have calculated_closing OR provisional_closing (either suffices for N's opening).
        $hasClosing = (isset($prevBill->calculated_closing) && $prevBill->calculated_closing !== null)
            || (isset($prevBill->provisional_closing) && $prevBill->provisional_closing !== null);
        if ($hasClosing) {
            return ['allowed' => true];
        }
        return [
            'allowed' => false,
            'blocked_reason' => 'Period N-1 has no calculated_closing or provisional_closing; heal previous period first.',
            'heal_bill_id' => (int) $prevBill->id,
        ];
    }

    // =========================================================================
    // PD Section 2.0 — Sector Splitting (chronological sectors from readings)
    // =========================================================================

    /**
     * Break readings into chronological sectors. Each sector = span between two consecutive readings.
     * Usage = end_reading - start_reading (integer). Rollover handled by meter digit_count.
     *
     * @param array  $readings [['reading_date' => string, 'reading_value' => int|float], ...]
     * @param object $meter { digit_count?: int }
     * @return array<int, array{start: string, end: string, start_reading: int, end_reading: int, total_usage: int, block_days: int, daily_avg: float}>
     */
    public function generateSectors(array $readings, object $meter): array
    {
        $sectors = [];
        $digitCount = (int) ($meter->digit_count ?? 4);
        $maxVal = (int) str_repeat('9', $digitCount) + 1;

        usort($readings, fn ($a, $b) => strcmp($a['reading_date'], $b['reading_date']));

        for ($i = 0; $i < count($readings) - 1; $i++) {
            $r1 = $readings[$i];
            $r2 = $readings[$i + 1];
            $v1 = (int) (float) $r1['reading_value'];
            $v2 = (int) (float) $r2['reading_value'];
            if ($v2 < $v1) {
                $v2 += $maxVal;
            }
            $totalUsage = $v2 - $v1;

            // Sector 0 (the first) starts from the reading date itself — this is the meter anchor
            // (start reading for Period 1, or opening reading for subsequent periods).
            // Every subsequent sector starts from nextDay(r1.date) so that the reading date
            // is owned exclusively by the sector it closes — no day is counted twice.
            $sectorStart = ($i === 0)
                ? $r1['reading_date']
                : $this->calendar->nextDay($r1['reading_date']);

            $blockDays = $this->calendar->blockDays($sectorStart, $r2['reading_date']);
            $dailyAvg  = $blockDays > 0 ? round($totalUsage / $blockDays, 2) : 0.0;

            $sectors[] = [
                'start'         => $sectorStart,
                'end'           => $r2['reading_date'],
                'start_reading' => $v1,
                'end_reading'   => (int) (float) $r2['reading_value'],
                'total_usage'   => $totalUsage,
                'block_days'    => $blockDays,
                'daily_avg'     => $dailyAvg,
            ];
        }
        return $sectors;
    }

    // =========================================================================
    // PD Section 3.0 — The Straddle Split (sector spans period boundaries)
    // =========================================================================

    /**
     * When a sector spans boundaries, split into sub-segments. Usage per segment via remainder method (integer).
     *
     * @param array  $sector { start, end, total_usage, block_days }
     * @param array  $periodBoundaries list of date strings (period end dates inside sector range)
     * @param object $calendar Calendar instance
     * @return array<int, array{start: string, end: string, usage: int}>
     */
    public function handleStraddle(array $sector, array $periodBoundaries, Calendar $calendar): array
    {
        $segments = [];
        $boundariesInRange = array_filter($periodBoundaries, function ($b) use ($sector) {
            return $b > $sector['start'] && $b < $sector['end'];
        });
        sort($boundariesInRange);

        $currentStart = $sector['start'];
        $segmentBlockCounts = [];
        $segmentRanges = [];

        foreach ($boundariesInRange as $boundary) {
            $segmentBlockCounts[] = $calendar->blockDays($currentStart, $boundary);
            $segmentRanges[] = ['start' => $currentStart, 'end' => $boundary];
            $currentStart = $calendar->nextDay($boundary);
        }
        $segmentBlockCounts[] = $calendar->blockDays($currentStart, $sector['end']);
        $segmentRanges[] = ['start' => $currentStart, 'end' => $sector['end']];

        $usages = $this->applyRemainderMethod((int) $sector['total_usage'], $segmentBlockCounts);

        foreach ($segmentRanges as $idx => $range) {
            $bd = $segmentBlockCounts[$idx];
            $segments[] = [
                'start'      => $range['start'],
                'end'        => $range['end'],
                'usage'      => $usages[$idx],
                'block_days' => $bd,
                'daily_avg'  => $bd > 0 ? round($usages[$idx] / $bd, 2) : 0.0,
            ];
        }
        return $segments;
    }

    // =========================================================================
    // PD Section 4.0 — The Remainder Method (no float; sum = total exactly)
    // =========================================================================

    /**
     * Split total usage into integer parts by block-day ratio. First N-1: floor(ratio); last: remainder.
     *
     * @param int   $totalUsage total Litres (or Wh) for the whole sector
     * @param int[] $segmentBlockCounts e.g. [12, 28, 10] for block days per segment
     * @return int[] usage per segment; sum equals $totalUsage
     */
    public function applyRemainderMethod(int $totalUsage, array $segmentBlockCounts): array
    {
        $totalBlocks = array_sum($segmentBlockCounts);
        if ($totalBlocks <= 0) {
            return array_fill(0, count($segmentBlockCounts), 0);
        }
        $result = [];
        $running = 0;
        $n = count($segmentBlockCounts);
        for ($i = 0; $i < $n - 1; $i++) {
            $seg = (int) floor($totalUsage * $segmentBlockCounts[$i] / $totalBlocks);
            $result[] = $seg;
            $running += $seg;
        }
        $result[] = $totalUsage - $running;
        return $result;
    }

    // =========================================================================
    // PD Section 7.0 — Tariff Template Resolution
    // =========================================================================

    /**
     * Load the active tariff template for a bill.
     * Uses bills.tariff_template_id directly; falls back to account.tariff_template_id.
     * Throws if no template is found — never silently produces a zero charge.
     */
    private function loadTariffTemplate(object $bill): object
    {
        $templateId = $bill->tariff_template_id ?? null;
        if (!$templateId) {
            $account    = DB::table('accounts')->where('id', $bill->account_id)->first();
            $templateId = $account?->tariff_template_id ?? null;  // nullsafe: account may not exist
        }
        if (!$templateId) {
            throw new \RuntimeException("Bill {$bill->id}: no tariff_template_id on bill or account.");
        }
        $template = DB::table('regions_account_type_cost')->where('id', $templateId)->first();
        if (!$template) {
            throw new \RuntimeException("Tariff template {$templateId} not found in regions_account_type_cost.");
        }
        return $template;
    }

    /**
     * Load tier definitions for a template.
     * Prefers structured rows from tariff_tiers table; falls back to JSON columns
     * (water_in / electricity) on the template row.
     *
     * @return array<int, array{min_units: float, max_units: float|null, rate_per_unit: float}>
     *         max_units is null for the final (unlimited) tier.
     */
    public function loadTierDefinitions(int $templateId, object $template): array
    {
        $rows = DB::table('tariff_tiers')
            ->where('tariff_template_id', $templateId)
            ->orderBy('tier_number')
            ->get();

        if ($rows->count() > 0) {
            return $rows->map(fn ($r) => [
                'min_units'    => (float) $r->min_units,
                'max_units'    => $r->max_units !== null ? (float) $r->max_units : null,  // null = unlimited
                'rate_per_unit' => (float) $r->rate_per_unit,
            ])->all();
        }

        // Fallback: JSON column — choose water_in or electricity based on template flags
        $json = null;
        if ($template->is_water) {
            $raw  = $template->water_in;
            $json = is_string($raw) ? json_decode($raw, true) : (array) ($raw ?? []);
        } elseif ($template->is_electricity) {
            $raw  = $template->electricity;
            $json = is_string($raw) ? json_decode($raw, true) : (array) ($raw ?? []);
        }

        if (empty($json)) {
            return [];
        }

        // Normalise JSON tier shape (handles both {min_units, max_units, rate_per_unit} and {min, max, rate})
        // null max_units = unlimited tier
        return array_map(fn ($t) => [
            'min_units'    => (float) ($t['min_units'] ?? $t['min'] ?? 0),
            'max_units'    => isset($t['max_units']) ? (float) $t['max_units'] : (isset($t['max']) ? (float) $t['max'] : null),
            'rate_per_unit' => (float) ($t['rate_per_unit'] ?? $t['rate'] ?? $t['cost'] ?? 0),
        ], $json);
    }

    // =========================================================================
    // PD Section 8.0 — Tiered Charge Computation
    // =========================================================================

    /**
     * Apply tiered rates to a consumption value. Returns usage_charge (float, 2 dp)
     * and a per-tier breakdown for the bills.tier_breakdown JSON column.
     *
     * @param int   $usageUnits   Consumption in base units (litres for water, kWh for electricity).
     * @param array $tiers        Tier definitions from loadTierDefinitions() (min_units / max_units in same unit as $usageUnits).
     * @param bool  $rawUnits     When true (electricity), units are NOT divided by 1000. When false (water), L → kL (÷1000) before rate.
     * @return array{usage_charge: float, tier_breakdown: array}
     */
    public function applyTieredRates(int $usageUnits, array $tiers, bool $rawUnits = false): array
    {
        if (empty($tiers)) {
            return ['usage_charge' => 0.0, 'tier_breakdown' => []];
        }

        $remaining   = $usageUnits;
        $totalCharge = 0.0;
        $breakdown   = [];

        foreach ($tiers as $i => $tier) {
            if ($remaining <= 0) {
                break;
            }
            $capacity    = $tier['max_units'] === null ? $remaining : (int) max(0, $tier['max_units'] - $tier['min_units']);
            $unitsInTier = min($remaining, $capacity);
            // For water: convert L → kL (÷ 1000). For electricity: kWh is already the billing unit.
            $billingUnits = $rawUnits ? (float) $unitsInTier : ($unitsInTier / 1000.0);
            $amount       = round($billingUnits * $tier['rate_per_unit'], 4);
            $breakdown[]  = [
                'tier'         => $i + 1,
                'units'        => $unitsInTier,
                'units_kl'     => round($billingUnits, 3),  // kL for water, kWh for electricity
                'rate'         => $tier['rate_per_unit'],
                'amount'       => $amount,
            ];
            $totalCharge += $amount;
            $remaining   -= $unitsInTier;
        }

        return [
            'usage_charge'   => round($totalCharge, 2),
            'tier_breakdown' => $breakdown,
        ];
    }

    // =========================================================================
    // PD Section 5.1 — Period Enumeration
    // =========================================================================

    /**
     * Return all billing periods {start, end} (both INCLUSIVE) whose start falls
     * within the span of $startDate to $endDate. Uses Calendar for all date math.
     *
     * @return array<int, array{start: string, end: string}>
     */
    public function calculatePeriods(int $billDay, string $startDate, string $endDate): array
    {
        $calendar = new Calendar();
        $periods  = [];
        $current  = $calendar->periodStart($startDate, $billDay);
        $last     = $calendar->periodStart($endDate, $billDay);

        while ($current <= $last) {
            $end       = $calendar->periodEnd($current, $billDay);
            $periods[] = ['start' => $current, 'end' => $end];
            $current   = $calendar->nextDay($end);
        }

        return $periods;
    }

    // =========================================================================
    // PD Section 5.2 — Date-to-Date period enumeration
    // =========================================================================

    private const D2D_MIN_DAYS = 30;

    /**
     * Build D2D periods from anchor + readings. Accept each reading into current period;
     * when last reading is >= 30 days from period opening, close period and open next.
     * Next period opening = previous period closing (meter does not reset).
     *
     * @param array<int, array{date: string, litres?: int|float, value?: int|float}> $readings
     * @return array<int, array{start: string, end: string, blockDays: int, expanded: bool, d2dReadingsStartIndex: int|null, water: array, electricity: null, showBill: bool, calculating: bool, calcError: string, bill: null, closed: bool}>
     */
    public function buildD2dPeriodsFromAnchorReadings(string $anchorDate, $anchorLitres, array $readings, ?string $today = null): array
    {
        $anchorLitres = (int) round((float) ($anchorLitres ?? 0));
        $filtered = [];
        foreach ($readings as $r) {
            if (empty($r['date'])) {
                continue;
            }
            $litres = isset($r['litres']) ? (int) round((float) $r['litres']) : (isset($r['value']) ? (int) round((float) $r['value']) : 0);
            if ($litres < 0) {
                continue;
            }
            $filtered[] = ['date' => $r['date'], 'litres' => $litres];
        }
        usort($filtered, fn ($a, $b) => strcmp($a['date'], $b['date']));

        $periods = [];
        $anchor = ['date' => $anchorDate, 'litres' => $anchorLitres];
        $periodReadings = [];

        foreach ($filtered as $r) {
            $litres = $r['litres'];
            $prevDate = $periodReadings !== [] ? end($periodReadings)['date'] : $anchor['date'];
            $prevLitres = $periodReadings !== [] ? end($periodReadings)['litres'] : $anchor['litres'];
            $invalid = $r['date'] <= $prevDate || $litres < $prevLitres;

            $periodReadings[] = [
                'date' => $r['date'],
                'litres' => $litres,
                'klStr' => number_format($litres / 1000, 2, '.', ''),
                'error' => $invalid ? 'Date must be after previous; reading must be ≥ previous.' : '',
            ];

            $daysFromOpening = $this->calendar->blockDays($anchor['date'], $r['date']);
            if ($daysFromOpening >= self::D2D_MIN_DAYS) {
                $start = $anchor['date'];
                $end = $r['date'];
                $validChain = array_merge(
                    [['date' => $anchor['date'], 'litres' => $anchor['litres']]],
                    array_values(array_filter($periodReadings, fn ($x) => $x['error'] === ''))
                );
                $sectorInput = count($validChain) >= 2
                    ? array_map(fn ($x) => ['reading_date' => $x['date'], 'reading_value' => $x['litres']], $validChain)
                    : [
                        ['reading_date' => $anchor['date'], 'reading_value' => $anchor['litres']],
                        ['reading_date' => $r['date'], 'reading_value' => $litres],
                    ];
                $sectors = $this->buildD2dSectors($sectorInput);
                $usage = max(0, $litres - $anchor['litres']);
                $bd = $this->calendar->blockDays($start, $end);
                $dailyUsage = $bd > 0 ? (int) round($usage / $bd) : 0;
                $closedStartIndex = count($filtered) - count($periodReadings);

                $periods[] = [
                    'start' => $start,
                    'end' => $end,
                    'blockDays' => $bd,
                    'expanded' => count($periods) === 0,
                    'd2dReadingsStartIndex' => $closedStartIndex,
                    'water' => [
                        'openingLitres' => $anchor['litres'],
                        'openingDate' => $anchor['date'],
                        'readings' => array_map(fn ($x) => array_merge([], $x), $periodReadings),
                        'sectors' => $sectors,
                        'dailyUsage' => $dailyUsage,
                        'provisionalClosingLitres' => $litres,
                        'calculatedClosingLitres' => $litres,
                        'stats' => null,
                        'adjustmentBroughtForward' => 0,
                        'insufficientData' => false,
                    ],
                    'electricity' => null,
                    'showBill' => false,
                    'calculating' => false,
                    'calcError' => '',
                    'bill' => null,
                    'closed' => true,
                ];

                $closingReading = end($periodReadings);
                $anchor = ['date' => $closingReading['date'], 'litres' => $closingReading['litres']];
                $periodReadings = [];
            }
        }

        $lastClosed = $periods !== [] ? $periods[count($periods) - 1] : null;
        $openOpeningLitres = $lastClosed !== null && isset($lastClosed['water'])
            ? ($lastClosed['water']['calculatedClosingLitres'] ?? $lastClosed['water']['provisionalClosingLitres'])
            : $anchor['litres'];
        $openOpeningDate = $lastClosed !== null && isset($lastClosed['water'])
            ? ($lastClosed['end'] ?? $anchor['date'])
            : $anchor['date'];

        $start = $openOpeningDate;
        $lastR = $periodReadings !== [] ? $periodReadings[count($periodReadings) - 1] : null;
        $end = $lastR !== null ? $lastR['date'] : $start;
        if ($today !== null && $today !== '' && $end < $today) {
            $end = $today;
        }
        $openAnchor = ['date' => $openOpeningDate, 'litres' => $openOpeningLitres];
        $validChain = array_merge(
            [$openAnchor],
            array_values(array_filter($periodReadings, fn ($x) => $x['error'] === ''))
        );
        $sectorInput = count($validChain) >= 2
            ? array_map(fn ($x) => ['reading_date' => $x['date'], 'reading_value' => $x['litres']], $validChain)
            : ($periodReadings !== []
                ? [
                    ['reading_date' => $openOpeningDate, 'reading_value' => $openOpeningLitres],
                    ['reading_date' => $periodReadings[0]['date'], 'reading_value' => $periodReadings[0]['litres']],
                ]
                : []);
        $sectors = count($sectorInput) >= 2 ? $this->buildD2dSectors($sectorInput) : [];
        $lastLitres = $lastR !== null ? $lastR['litres'] : $openOpeningLitres;
        $usage = max(0, $lastLitres - $openOpeningLitres);
        $bd = $this->calendar->blockDays($start, $end);
        $dailyUsage = $bd > 0 ? (int) round($usage / $bd) : 0;
        $d2dReadingsStartIndex = $periodReadings !== [] ? count($filtered) - count($periodReadings) : count($filtered);

        $periods[] = [
            'start' => $start,
            'end' => $end,
            'blockDays' => $bd,
            'expanded' => true,
            'd2dReadingsStartIndex' => $d2dReadingsStartIndex,
            'water' => [
                'openingLitres' => $openOpeningLitres,
                'openingDate' => $openOpeningDate,
                'readings' => $periodReadings,
                'sectors' => $sectors,
                'dailyUsage' => $dailyUsage,
                'provisionalClosingLitres' => $lastLitres,
                'calculatedClosingLitres' => $lastLitres,
                'stats' => null,
                'adjustmentBroughtForward' => 0,
                'insufficientData' => false,
            ],
            'electricity' => null,
            'showBill' => false,
            'calculating' => false,
            'calcError' => '',
            'bill' => null,
            'closed' => false,
        ];

        return $periods;
    }

    /**
     * Build sectors from a chain of reading_date + reading_value (for D2D). Inclusive block days.
     *
     * @param array<int, array{reading_date: string, reading_value: int|float}> $readings
     * @return array<int, array{start: string, end: string, start_reading: int, end_reading: int, total_usage: int, block_days: int, daily_avg: float}>
     */
    private function buildD2dSectors(array $readings): array
    {
        $sorted = $readings;
        usort($sorted, fn ($a, $b) => strcmp($a['reading_date'], $b['reading_date']));
        $sectors = [];
        for ($i = 0; $i < count($sorted) - 1; $i++) {
            $r1 = $sorted[$i];
            $r2 = $sorted[$i + 1];
            $sStart = $i === 0 ? $r1['reading_date'] : $this->calendar->nextDay($r1['reading_date']);
            if ($r2['reading_date'] <= $r1['reading_date']) {
                continue;
            }
            $bd = $this->calendar->blockDays($sStart, $r2['reading_date']);
            $usage = max(0, (int) round((float) $r2['reading_value'] - (float) $r1['reading_value']));
            $sectors[] = [
                'start' => $sStart,
                'end' => $r2['reading_date'],
                'start_reading' => (int) round((float) $r1['reading_value']),
                'end_reading' => (int) round((float) $r2['reading_value']),
                'total_usage' => $usage,
                'block_days' => $bd,
                'daily_avg' => $bd > 0 ? round($usage / $bd * 10) / 10 : 0.0,
            ];
        }
        return $sectors;
    }

    // =========================================================================
    // PD Section 9.0 — Fixed Costs
    // =========================================================================

    /**
     * Load and sum fixed cost line items from tariff_fixed_costs for the template.
     *
     * @return array{fixed_total: float, fixed_breakdown: array}
     */
    public function applyFixedCosts(int $templateId): array
    {
        $rows      = DB::table('tariff_fixed_costs')->where('tariff_template_id', $templateId)->get();
        $total     = 0.0;
        $breakdown = [];

        foreach ($rows as $row) {
            $amount      = (float) $row->amount;
            $breakdown[] = ['name' => $row->name, 'amount' => $amount, 'is_vatable' => (bool) $row->is_vatable];
            $total      += $amount;
        }

        return ['fixed_total' => round($total, 2), 'fixed_breakdown' => $breakdown];
    }

    // =========================================================================
    // PD Section 10.0 — Customer Cost Overrides
    // =========================================================================

    /**
     * Load per-account cost overrides from customer_cost_overrides table.
     * Returns zero silently if no overrides exist for the account.
     *
     * @return array{override_total: float, override_breakdown: array}
     */
    public function applyCustomerOverrides(int $accountId): array
    {
        $rows      = DB::table('customer_cost_overrides')->where('account_id', $accountId)->get();
        $total     = 0.0;
        $breakdown = [];

        foreach ($rows as $row) {
            $amount      = (float) $row->value;
            $breakdown[] = ['name' => $row->cost_name, 'amount' => $amount, 'is_vatable' => true];
            $total      += $amount;
        }

        return ['override_total' => round($total, 2), 'override_breakdown' => $breakdown];
    }

    // =========================================================================
    // PD Section 11.0 — VAT
    // =========================================================================

    /**
     * Compute VAT on the vatable subtotal. Rate is a percentage (e.g. 15.0 = 15%).
     * Rounding to 2 dp deferred to persistBill (PD 12.0).
     */
    public function computeVat(float $vatableAmount, float $vatRate): float
    {
        return round($vatableAmount * $vatRate / 100, 2);
    }

    // =========================================================================
    // Helpers (used by computePeriod; no separate PD section)
    // =========================================================================

    private function loadBill(int $id): object
    {
        $row = DB::table('bills')->where('id', $id)->first();
        if (!$row) {
            throw new \RuntimeException("Bill {$id} not found.");
        }
        return $row;
    }

    private function loadMeter(int $id): object
    {
        $row = DB::table('meters')->where('id', $id)->first();
        if (!$row) {
            throw new \RuntimeException("Meter {$id} not found.");
        }
        return $row;
    }

    /** @return array<int, array{reading_date: string, reading_value: mixed}> */
    private function loadReadingsForMeter(int $meterId): array
    {
        $rows = DB::table('meter_readings')
            ->where('meter_id', $meterId)
            ->orderBy('reading_date')
            ->get();
        return $rows->map(fn ($r) => ['reading_date' => $r->reading_date, 'reading_value' => $r->reading_value])->all();
    }

    private function findPreviousBill(int $meterId, string $periodStart): ?object
    {
        return DB::table('bills')
            ->where('meter_id', $meterId)
            ->where('period_start_date', '<', $periodStart)
            ->orderBy('period_start_date', 'desc')
            ->first();
    }


    private function sectorOverlapsPeriod(array $sector, string $periodStart, string $periodEnd): bool
    {
        return $sector['end'] >= $periodStart && $sector['start'] <= $periodEnd;
    }

    private function segmentOverlapsPeriod(array $segment, string $periodStart, string $periodEnd): bool
    {
        return $segment['end'] >= $periodStart && $segment['start'] <= $periodEnd;
    }

    // =========================================================================
    // PD Section 12.0 — Persistence: Bill Total & Breakdown
    // =========================================================================

    /**
     * Persist the full billing result to bills in a single atomic update.
     * This is the only place monetary rounding (2 dp) is applied.
     *
     * @param array $charge {usage_charge, tier_breakdown, fixed_total, fixed_breakdown,
     *                        override_total, override_breakdown, vat_amount, bill_total}
     */
    private function persistBill(int $billId, int $usageL, array $charge): void
    {
        DB::table('bills')->where('id', $billId)->update([
            'consumption'             => $usageL,
            'usage_charge'            => $charge['usage_charge'],
            'tiered_charge'           => $charge['usage_charge'],
            'tier_breakdown'          => json_encode($charge['tier_breakdown']),
            'fixed_costs_total'       => $charge['fixed_total'],
            'fixed_costs_breakdown'   => json_encode($charge['fixed_breakdown']),
            'account_costs_breakdown' => json_encode($charge['override_breakdown']),
            'vat_amount'              => $charge['vat_amount'],
            'total_amount'            => $charge['bill_total'],
            'status'                  => 'calculated',
            'updated_at'              => now()->toDateTimeString(),
        ]);
    }
}
