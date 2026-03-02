<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Meter;
use App\Models\MeterReadings;
use App\Models\RegionsAccountTypeCost;
use App\Models\User;
use App\Services\Billing\Calendar;
use App\Services\Billing\Calculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CalculatorController — PD.md ↔ Calculator.php
 *
 * Serves the Billing Calculator Vue page (Admin/Calculator.vue).
 * Endpoints use the clean Calculator.php / Calendar.php implementations.
 */
class CalculatorController extends Controller
{
    // ── Page ─────────────────────────────────────────────────────────────────

    public function index(): Response
    {
        // Load users with accounts directly via user_id (not legacy sites path)
        $users = User::orderBy('name')->get()->map(function ($u) {
            $accounts = Account::where('user_id', $u->id)
                ->select('id', 'user_id', 'account_name', 'account_number', 'bill_day', 'tariff_template_id')
                ->get();
            return [
                'id'             => $u->id,
                'name'           => $u->name,
                'email'          => $u->email,
                'contact_number' => $u->contact_number,
                'accounts'       => $accounts->values(),
            ];
        })->filter(fn ($u) => count($u['accounts']) > 0)->values();

        $templates = RegionsAccountTypeCost::with('region')
            ->where(fn ($q) => $q->where('is_water', 1)->orWhere('is_electricity', 1))
            ->whereNotNull('template_name')
            ->where('template_name', '!=', '')
            ->orderBy('template_name')
            ->get()
            ->map(fn ($t) => [
                'id'             => $t->id,
                'name'           => $t->template_name,
                'region_name'    => $t->region?->name,
                'billing_day'    => $t->billing_day,
                'vat_rate'       => $t->vat_percentage ?? $t->vat_rate ?? 15,
                'is_water'       => (bool) $t->is_water,
                'is_electricity' => (bool) $t->is_electricity,
            ]);

        return Inertia::render('Admin/Calculator', [
            'users'           => $users,
            'tariffTemplates' => $templates,
            'today'           => now('Africa/Johannesburg')->format('Y-m-d'),
        ]);
    }

    // ── Compute full period (existing — requires bill_id in DB) ───────────────

    public function compute(Request $request): JsonResponse
    {
        $request->validate(['bill_id' => 'required|integer|exists:bills,id']);
        try {
            $calendar   = new Calendar();
            $calculator = new Calculator($calendar);
            $result     = $calculator->computePeriod((int) $request->bill_id);
            return response()->json($result);
        } catch (\Throwable $e) {
            \Log::error('CalculatorController::compute', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── Stateless charge calculation (no DB bill needed) ────────────────────

    /**
     * POST /admin/calculator/compute-charge
     *
     * Stateless monetary breakdown for a given consumption (tiers, fixed costs,
     * customer overrides, VAT, total). No bill record is read or written.
     *
     * Params: tariff_template_id, consumption_litres; optional account_id.
     *   consumption_unit – 'litres' (default) | 'kwh' (no ÷1000 in tier maths).
     *   include_fixed    – true (default) | false (e.g. electricity to avoid double-counting fixed).
     */
    public function computeCharge(Request $request): JsonResponse
    {
        $request->validate([
            'tariff_template_id' => 'required|integer|exists:regions_account_type_cost,id',
            'consumption_litres' => 'required|integer|min:0',
            'account_id'         => 'nullable|integer|exists:accounts,id',
            'consumption_unit'   => 'nullable|string|in:litres,kwh',
            'include_fixed'      => 'nullable|boolean',
        ]);

        $templateId    = (int) $request->tariff_template_id;
        $consumption   = (int) $request->consumption_litres;
        $accountId     = $request->integer('account_id') ?: null;
        $isKwh         = ($request->input('consumption_unit') === 'kwh');
        $includeFixed  = $request->boolean('include_fixed', true);

        try {
            $template = DB::table('regions_account_type_cost')->where('id', $templateId)->first();
            if (!$template) {
                return response()->json(['success' => false, 'message' => 'Template not found'], 404);
            }

            $calendar   = new Calendar();
            $calculator = new Calculator($calendar);

            // Use rawUnits for electricity (kWh billed directly, no ÷1000)
            $rawUnits     = $isKwh || (bool) ($template->is_electricity ?? false);
            $tiers        = $calculator->loadTierDefinitions($templateId, $template);
            $chargeResult = $calculator->applyTieredRates($consumption, $tiers, $rawUnits);

            $fixedResult = ['fixed_total' => 0.0, 'fixed_breakdown' => []];
            if ($includeFixed) {
                $fixedResult = $calculator->applyFixedCosts($templateId);
            }

            $overrideResult = ['override_total' => 0.0, 'override_breakdown' => []];
            if ($accountId && $includeFixed) {
                $overrideResult = $calculator->applyCustomerOverrides($accountId);
            }

            $vatRate         = (float) ($template->vat_percentage ?? $template->vat_rate ?? 15.0);
            $fixedVatable    = array_sum(array_map(fn ($f) => $f['is_vatable'] ? $f['amount'] : 0.0, $fixedResult['fixed_breakdown']));
            $overrideVatable = array_sum(array_map(fn ($o) => ($o['is_vatable'] ?? true) ? $o['amount'] : 0.0, $overrideResult['override_breakdown']));
            $vatAmount       = $calculator->computeVat($chargeResult['usage_charge'] + $fixedVatable + $overrideVatable, $vatRate);
            $billTotal       = round($chargeResult['usage_charge'] + $fixedResult['fixed_total'] + $overrideResult['override_total'] + $vatAmount, 2);

            // Consumption label adapts to unit
            $consumptionKl = $rawUnits ? null : round($consumption / 1000, 3);

            return response()->json([
                'success' => true,
                'data'    => [
                    'consumption_litres'  => $consumption,
                    'consumption_kl'      => $consumptionKl,
                    'consumption_unit'    => $isKwh ? 'kwh' : 'litres',
                    'usage_charge'        => $chargeResult['usage_charge'],
                    'tier_breakdown'      => $chargeResult['tier_breakdown'],
                    'fixed_total'         => $fixedResult['fixed_total'],
                    'fixed_breakdown'     => $fixedResult['fixed_breakdown'],
                    'override_total'      => $overrideResult['override_total'],
                    'override_breakdown'  => $overrideResult['override_breakdown'],
                    'vat_amount'          => $vatAmount,
                    'bill_total'          => $billTotal,
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error('CalculatorController::computeCharge', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── Account data for USER+ACCOUNT mode ───────────────────────────────────

    /**
     * GET /admin/calculator/account/{id}
     *
     * Returns all meters and ALL their readings for an account.
     * The frontend reconstructs periods client-side from bill_day + readings.
     */
    public function getAccountData(int $accountId): JsonResponse
    {
        $account = Account::select('id', 'account_name', 'account_number', 'bill_day', 'tariff_template_id')
            ->find($accountId);

        if (!$account) {
            return response()->json(['success' => false, 'message' => 'Account not found'], 404);
        }

        $tariff = null;
        if ($account->tariff_template_id) {
            $tariffRow = DB::table('regions_account_type_cost')->where('id', $account->tariff_template_id)->first();
            if ($tariffRow) {
                $tariff = [
                    'id'             => $tariffRow->id,
                    'template_name'  => $tariffRow->template_name,
                    'billing_day'    => $tariffRow->billing_day ?? $account->bill_day,
                    'is_water'       => (bool) ($tariffRow->is_water ?? true),
                    'is_electricity' => (bool) ($tariffRow->is_electricity ?? false),
                    'vat_rate'       => (float) ($tariffRow->vat_percentage ?? $tariffRow->vat_rate ?? 15.0),
                ];
            }
        }

        $meterRows = DB::table('meters')
            ->where('account_id', $accountId)
            ->get();

        $meterTypeNames = DB::table('meter_types')->pluck('title', 'id');

        $meters = [];
        foreach ($meterRows as $m) {
            $typeTitle = strtolower($meterTypeNames[$m->meter_type_id] ?? '');
            $meterType = str_contains($typeTitle, 'water') ? 'water'
                : (str_contains($typeTitle, 'elec') ? 'electricity' : 'other');

            $readings = DB::table('meter_readings')
                ->where('meter_id', $m->id)
                ->orderBy('reading_date')
                ->get(['id', 'reading_date', 'reading_value'])
                ->map(fn ($r) => [
                    'id'    => $r->id,
                    'date'  => $r->reading_date,
                    'value' => (float) $r->reading_value,
                ])
                ->values()
                ->toArray();

            $meters[] = [
                'id'           => $m->id,
                'meter_title'  => $m->meter_title,
                'meter_number' => $m->meter_number,
                'meter_type_id'=> $m->meter_type_id,
                'meter_type'   => $meterType,
                'readings'     => $readings,
            ];
        }

        // Sort: water first, electricity second
        usort($meters, fn ($a, $b) => ($a['meter_type'] === 'water' ? 0 : 1) <=> ($b['meter_type'] === 'water' ? 0 : 1));

        return response()->json([
            'success' => true,
            'data'    => [
                'account' => [
                    'id'             => $account->id,
                    'account_name'   => $account->account_name,
                    'account_number' => $account->account_number,
                    'bill_day'       => (int) $account->bill_day,
                ],
                'tariff'  => $tariff,
                'meters'  => $meters,
            ],
        ]);
    }

    // ── Meter data for USER+ACCOUNT mode ────────────────────────────────────

    /**
     * GET /admin/calculator/meter/{id}
     *
     * Returns everything the calculator UI needs for a meter:
     *   - Opening anchors (provisional + actual) for the current period
     *   - Actual readings within the current period
     *   - Sectors computed from those readings (with block_days and daily_avg)
     *   - Provisional closing (live estimate based on last reading)
     *   - Previous closed periods with their closing anchors
     */
    public function getMeterData(int $meterId): JsonResponse
    {
        $meter = DB::table('meters')->where('id', $meterId)->first();
        if (!$meter) {
            return response()->json(['success' => false, 'message' => 'Meter not found'], 404);
        }

        $account = Account::select('id', 'bill_day', 'tariff_template_id', 'account_number', 'account_name')
            ->find($meter->account_id);
        if (!$account) {
            return response()->json(['success' => false, 'message' => 'Account not found'], 404);
        }

        $calendar   = new Calendar();
        $calculator = new Calculator($calendar);
        $today      = now('Africa/Johannesburg')->format('Y-m-d');
        $billDay    = (int) $account->bill_day;

        $periodStart = $calendar->periodStart($today, $billDay);
        $periodEnd   = $calendar->periodEnd($periodStart, $billDay);
        $periodDays  = $calendar->blockDays($periodStart, $periodEnd);

        // ── Opening anchors ────────────────────────────────────────────────
        // Priority: calculated_closing of prev bill → provisional_closing of prev bill → meter start_reading
        $prevBill = DB::table('bills')
            ->where('meter_id', $meterId)
            ->whereNotNull('period_end_date')
            ->where('period_end_date', '<', $periodStart)
            ->orderBy('period_end_date', 'desc')
            ->first();

        $openingProvisional = null;
        $openingActual      = null;

        if ($prevBill) {
            $provVal = isset($prevBill->provisional_closing) ? (float) $prevBill->provisional_closing : null;
            $calcVal = isset($prevBill->calculated_closing)  ? (float) $prevBill->calculated_closing  : null;

            // Provisional comes from the bill that was saved at period end
            if ($provVal !== null) {
                $openingProvisional = ['value' => $provVal, 'date' => $prevBill->period_end_date, 'source' => 'prev provisional'];
            }
            // Actual comes from a straddle calculation that resolved the previous period
            if ($calcVal !== null) {
                $openingActual = ['value' => $calcVal, 'date' => $prevBill->period_end_date, 'source' => 'prev calculated'];
            }
        }

        // Period 1 fallback: meter start_reading is the opening provisional anchor
        if (!$openingProvisional && $meter->start_reading !== null) {
            $openingProvisional = [
                'value'  => (float) $meter->start_reading,
                'date'   => $meter->start_reading_date ?? $periodStart,
                'source' => 'meter init',
            ];
        }

        // ── Readings within the current period ────────────────────────────
        $periodReadings = DB::table('meter_readings')
            ->where('meter_id', $meterId)
            ->whereBetween('reading_date', [$periodStart, $periodEnd])
            ->orderBy('reading_date')
            ->get(['id', 'reading_date', 'reading_value'])
            ->map(fn ($r) => [
                'id'    => $r->id,
                'date'  => $r->reading_date,
                'value' => (float) $r->reading_value,
            ])
            ->values()
            ->toArray();

        // ── Sectors for the current period ────────────────────────────────
        // Build the reading array: opening anchor (reference) + actual readings in period
        $sectors              = [];
        $closingProvisional   = null;
        $closingActual        = null;

        $openingAnchor = $openingActual ?? $openingProvisional;

        if ($openingAnchor && count($periodReadings) > 0) {
            // Compose input for generateSectors: anchor first, then actuals
            $sectorInput = array_merge(
                [['reading_date' => $openingAnchor['date'], 'reading_value' => $openingAnchor['value']]],
                array_map(fn ($r) => ['reading_date' => $r['date'], 'reading_value' => $r['value']], $periodReadings)
            );

            $meterObj = (object) ['digit_count' => $meter->digit_count ?? 4];
            $rawSectors = $calculator->generateSectors($sectorInput, $meterObj);

            // Only include sectors that fall within the current period
            foreach ($rawSectors as $s) {
                if ($s['end'] > $periodEnd) {
                    continue; // will be a straddle — skip for live view
                }
                $sectors[] = [
                    'start'         => $s['start'],
                    'end'           => $s['end'],
                    'start_reading' => $s['start_reading'],
                    'end_reading'   => $s['end_reading'],
                    'total_usage'   => $s['total_usage'],
                    'block_days'    => $s['block_days'],
                    'daily_avg'     => $s['daily_avg'],
                ];
            }

            // Provisional closing: opening + (daily_avg_from_last_reading × period_days)
            // Uses the span from opening anchor to last actual reading as the rate basis
            $lastReading = end($periodReadings);
            $usageSoFar  = (float) $lastReading['value'] - (float) $openingAnchor['value'];
            $daysSoFar   = $calendar->blockDays($openingAnchor['date'], $lastReading['date']);
            if ($daysSoFar > 0 && $usageSoFar >= 0) {
                $dailyRate          = $usageSoFar / $daysSoFar;
                $closingProvisional = (float) $openingAnchor['value'] + round($dailyRate * $periodDays);
            }

            // If last reading is on the period end date → this IS the actual closing (no estimate needed)
            if ($lastReading['date'] === $periodEnd) {
                $closingActual      = (float) $lastReading['value'];
                $closingProvisional = $closingActual;
            }
        }

        // ── Previous periods (history) ────────────────────────────────────
        $previousPeriods = DB::table('bills')
            ->where('meter_id', $meterId)
            ->whereNotNull('period_end_date')
            ->where('period_end_date', '<', $periodStart)
            ->orderBy('period_start_date', 'desc')
            ->get([
                'id', 'period_start_date', 'period_end_date', 'status',
                'consumption', 'total_amount', 'daily_usage',
                'calculated_closing',
            ])
            ->map(function ($b) {
                // provisional_closing may not be a DB column — guard with isset
                $provClosing = isset($b->provisional_closing) ? (float) $b->provisional_closing : null;
                return [
                    'id'                  => $b->id,
                    'period_start_date'   => $b->period_start_date,
                    'period_end_date'     => $b->period_end_date,
                    'status'              => $b->status,
                    'consumption'         => (float) $b->consumption,
                    'total_amount'        => (float) $b->total_amount,
                    'daily_usage'         => $b->daily_usage !== null ? (float) $b->daily_usage : null,
                    'closing_provisional' => $provClosing,
                    'closing_actual'      => $b->calculated_closing !== null ? (float) $b->calculated_closing : null,
                ];
            })
            ->values()
            ->toArray();

        return response()->json([
            'success' => true,
            'data'    => [
                'meter' => [
                    'id'                 => $meter->id,
                    'meter_number'       => $meter->meter_number,
                    'meter_title'        => $meter->meter_title,
                    'meter_type_id'      => $meter->meter_type_id,
                    'start_reading'      => $meter->start_reading !== null ? (float) $meter->start_reading : null,
                    'start_reading_date' => $meter->start_reading_date,
                ],
                'account' => [
                    'id'                 => $account->id,
                    'account_number'     => $account->account_number,
                    'account_name'       => $account->account_name,
                    'bill_day'           => $billDay,
                    'tariff_template_id' => $account->tariff_template_id,
                ],
                'current_period' => [
                    'start'      => $periodStart,
                    'end'        => $periodEnd,
                    'block_days' => $periodDays,
                ],
                'opening_provisional' => $openingProvisional,
                'opening_actual'      => $openingActual,
                'period_readings'     => $periodReadings,
                'sectors'             => $sectors,
                'closing_provisional' => $closingProvisional,
                'closing_actual'      => $closingActual,
                'previous_periods'    => $previousPeriods,
            ],
        ]);
    }

    // ── Reading management (USER+ACCOUNT mode) ───────────────────────────────

    /**
     * POST /admin/calculator/reading
     * Add a meter reading and return it.
     */
    public function addReading(Request $request): JsonResponse
    {
        $request->validate([
            'meter_id'      => 'required|integer|exists:meters,id',
            'reading_date'  => 'required|date',
            'reading_value' => 'required|numeric|min:0',
        ]);

        // Prevent duplicate date
        $exists = DB::table('meter_readings')
            ->where('meter_id', $request->meter_id)
            ->where('reading_date', $request->reading_date)
            ->exists();
        if ($exists) {
            return response()->json(['success' => false, 'message' => 'A reading for this date already exists.'], 422);
        }

        $id = DB::table('meter_readings')->insertGetId([
            'meter_id'      => $request->meter_id,
            'reading_date'  => $request->reading_date,
            'reading_value' => $request->reading_value,
            'reading_type'  => 'ACTUAL',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return response()->json([
            'success' => true,
            'reading' => [
                'id'    => $id,
                'date'  => $request->reading_date,
                'value' => (float) $request->reading_value,
            ],
        ]);
    }

    /**
     * DELETE /admin/calculator/reading/{id}
     */
    public function deleteReading(int $id): JsonResponse
    {
        $deleted = DB::table('meter_readings')->where('id', $id)->delete();
        return response()->json(['success' => $deleted > 0]);
    }

    /**
     * POST /admin/calculator/calculate-periods
     *
     * Returns all billing periods for an account based on its reading date span.
     */
    public function calculatePeriods(Request $request): JsonResponse
    {
        $accountId = $request->input('account_id');
        if (!$accountId) {
            return response()->json(['success' => false, 'error' => 'account_id is required'], 422);
        }

        $account = Account::with(['meters.readings'])->find($accountId);
        if (!$account) {
            return response()->json(['success' => false, 'error' => 'Account not found'], 404);
        }

        $billDay  = (int) ($account->bill_day ?? 15);
        $allDates = [];
        foreach ($account->meters as $meter) {
            foreach ($meter->readings as $reading) {
                $date = $reading->reading_date ?? $reading->date ?? null;
                if ($date) {
                    $allDates[] = is_object($date) ? $date->format('Y-m-d') : (string) $date;
                }
            }
        }

        if (empty($allDates)) {
            return response()->json(['success' => true, 'data' => ['periods' => []]]);
        }

        sort($allDates);
        $calendar = new Calendar();
        $calculator = new Calculator($calendar);
        $periods  = $calculator->calculatePeriods($billDay, reset($allDates), end($allDates));

        return response()->json(['success' => true, 'data' => ['periods' => $periods]]);
    }
}
