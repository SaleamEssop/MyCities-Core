<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Meter;
use App\Models\MeterReadings;
use App\Models\Payment;
use App\Models\RegionsAccountTypeCost;
use App\Repositories\BillingRepository;
use App\Services\Billing\Calculator;
use App\Services\Billing\Calendar;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DashboardService
{
    private BillingRepository $billingRepository;
    private Calculator $billingCalc;
    private Calendar $calendar;

    public function __construct(
        BillingRepository $billingRepository,
        Calculator $billingCalc,
        Calendar $calendar
    ) {
        $this->billingRepository = $billingRepository;
        $this->billingCalc       = $billingCalc;
        $this->calendar          = $calendar;
    }

    /**
     * Get comprehensive dashboard data for an account.
     */
    public function getDashboardData(Account $account): array
    {
        $userEmail = $account->site->user->email ?? '';
        $site = $this->billingRepository->findSiteByUserId($account->site->user_id ?? 0, $userEmail);
        $tariff = $this->billingRepository->getTariffTemplateForAccount($account);

        if (!$tariff) {
            throw new \Exception("No tariff template found for account #{$account->id}");
        }

        // Get meters
        $meters = $this->billingRepository->getAccountMetersByType($account);
        $waterMeter = $meters['water'];
        $electricityMeter = $meters['electricity'];

        // Calculate period info
        $periodInfo = $this->calculatePeriodInfo($account, $tariff);

        // Get meter data
        $waterData = $this->getMeterData($waterMeter, $tariff, 'water', $periodInfo);
        $electricityData = $this->getMeterData($electricityMeter, $tariff, 'electricity', $periodInfo);

        // Calculate totals
        $vatRate = $tariff->getVatRate() / 100;
        $waterCharges = $waterData['charges']['total'] ?? 0;
        $electricityCharges = $electricityData['charges']['total'] ?? 0;

        $waterVat = $waterData['charges']['vat_amount'] ?? ($waterCharges * $vatRate);
        $electricityVat = $electricityData['charges']['vat_amount'] ?? ($electricityCharges * $vatRate);

        $waterPeriodTotal = $waterCharges + $waterVat;
        $electricityPeriodTotal = $electricityCharges + $electricityVat;

        $waterData['totals'] = [
            'consumption_total' => round($waterCharges, 2),
            'vat_amount' => round($waterVat, 2),
            'vat_rate' => $tariff->getVatRate(),
            'period_total' => round($waterPeriodTotal, 2),
        ];

        $electricityData['totals'] = [
            'consumption_total' => round($electricityCharges, 2),
            'vat_amount' => round($electricityVat, 2),
            'vat_rate' => $tariff->getVatRate(),
            'period_total' => round($electricityPeriodTotal, 2),
        ];

        $totalCharges = $waterCharges + $electricityCharges;
        $totalVat = $waterVat + $electricityVat;
        $grandTotal = $totalCharges + $totalVat;

        // Get payments
        $paymentsData = $this->getPaymentsData($account, $periodInfo['start_date'], $periodInfo['end_date']);
        $totalPaid = $paymentsData['total_paid'];
        $balanceDue = $grandTotal - $totalPaid;

        return [
            'account' => [
                'id' => $account->id,
                'name' => $account->account_name,
                'account_number' => $account->account_number,
            ],
            'site' => [
                'id' => $site->id ?? null,
                'title' => $site->title ?? 'Unknown Site',
                'address' => $site->address ?? '',
            ],
            'tariff' => [
                'id' => $tariff->id,
                'name' => $tariff->template_name,
                'billing_type' => $tariff->isDateToDateBilling() ? 'DATE_TO_DATE' : 'MONTHLY',
                'vat_rate' => $tariff->getVatRate(),
                'is_water' => (bool) $tariff->is_water,
                'is_electricity' => (bool) $tariff->is_electricity,
                'water_tiers' => $tariff->water_in ?? [],
                'fixed_costs' => $tariff->fixed_costs ?? [],
            ],
            'water' => $waterData,
            'electricity' => $electricityData,
            'period' => $periodInfo,
            'payments' => $paymentsData['items'],
            'totals' => [
                'consumption_total' => round($totalCharges, 2),
                'vat_amount' => round($totalVat, 2),
                'grand_total' => round($grandTotal, 2),
                'total_paid' => round($totalPaid, 2),
                'balance_due' => round($balanceDue, 2),
            ],
        ];
    }

    /**
     * Get meter data from bills or calculate from readings.
     */
    private function getMeterData(?Meter $meter, RegionsAccountTypeCost $tariff, string $type, array $periodInfo): array
    {
        if (!$meter) {
            return [
                'enabled' => false,
                'meter' => null,
                'readings' => [],
                'consumption' => 0,
                'charges' => [
                    'total' => 0,
                    'breakdown' => [],
                    'tier_breakdown' => [],
                    'fixed_costs_breakdown' => [],
                    'account_costs_breakdown' => [],
                    'vat_amount' => 0,
                ],
            ];
        }

        // Try to get latest bill
        $latestBill = Bill::where('meter_id', $meter->id)
            ->where('account_id', $meter->account_id)
            ->with(['openingReading', 'closingReading'])
            ->orderBy('created_at', 'desc')
            ->first();

        // Get current period readings
        $readings = MeterReadings::where('meter_id', $meter->id)
            ->whereBetween('reading_date', [$periodInfo['start_date'], $periodInfo['end_date'] ?? Carbon::now()->toDateString()])
            ->orderBy('reading_date', 'asc')
            ->get();

        if ($latestBill) {
            // Use bill data as source of truth for persisted periods
            $breakdown = [];
            if (!empty($latestBill->tier_breakdown)) {
                foreach ($latestBill->tier_breakdown as $tier) {
                    $breakdown[] = [
                        'type' => 'tier',
                        'label' => $tier['label'] ?? 'Tier ' . ($tier['tier'] ?? ''),
                        'units' => $tier['units'] ?? 0,
                        'rate' => $tier['rate'] ?? 0,
                        'charge' => $tier['charge'] ?? 0,
                    ];
                }
            }

            return [
                'enabled' => true,
                'meter' => [
                    'id' => $meter->id,
                    'number' => $meter->meter_number,
                    'title' => $meter->meter_title,
                ],
                'readings' => $this->formatReadings($readings),
                'consumption' => (float) $latestBill->consumption,
                'charges' => [
                    'total' => (float) ($latestBill->total_amount - $latestBill->vat_amount),
                    'breakdown' => $breakdown,
                    'vat_amount' => (float) $latestBill->vat_amount,
                ],
                'period_start' => $latestBill->openingReading?->reading_date?->toDateString(),
                'period_end' => $latestBill->closingReading?->reading_date?->toDateString(),
            ];
        }

        // No bill yet — return consumption only; charges require a persisted bill.
        $consumption = $this->calculateConsumption($readings);

        return [
            'enabled' => true,
            'meter' => [
                'id'     => $meter->id,
                'number' => $meter->meter_number,
                'title'  => $meter->meter_title,
            ],
            'readings'    => $this->formatReadings($readings),
            'consumption' => $consumption,
            'charges'     => [
                'total'      => 0,
                'breakdown'  => [],
                'vat_amount' => 0,
            ],
        ];
    }

    private function calculateConsumption($readings): float
    {
        if ($readings->count() < 2)
            return 0;
        return $readings->last()->reading_value - $readings->first()->reading_value;
    }

    private function formatReadings($readings): array
    {
        return $readings->map(fn($r) => [
            'id' => $r->id,
            'value' => $r->reading_value,
            'date' => $r->reading_date instanceof Carbon ? $r->reading_date->toDateString() : $r->reading_date,
            'type' => $r->reading_type,
        ])->toArray();
    }

    private function calculatePeriodInfo(Account $account, RegionsAccountTypeCost $tariff): array
    {
        $billingDay = $account->bill_day ?: $tariff->billing_day ?: 1;

        $now         = Carbon::now('Africa/Johannesburg')->toDateString();
        $periodStart = $this->calendar->periodStart($now, (int) $billingDay);
        $periodEnd   = $this->calendar->periodEnd($periodStart, (int) $billingDay);

        return [
            'start_date'     => $periodStart,
            'end_date'       => $periodEnd,
            'days_in_period' => $this->calendar->blockDays($periodStart, $periodEnd),
            'status'         => 'OPEN',
        ];
    }

    private function getPaymentsData(Account $account, $startDate, $endDate): array
    {
        $payments = Payment::where('account_id', $account->id)
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->orderBy('payment_date', 'desc')
            ->get();

        return [
            'items' => $payments->map(fn($p) => [
                'id' => $p->id,
                'amount' => (float) $p->amount,
                'payment_date' => $p->payment_date instanceof Carbon ? $p->payment_date->toDateString() : $p->payment_date,
                'payment_method' => $p->payment_method,
            ])->toArray(),
            'total_paid' => (float) $payments->sum('amount'),
        ];
    }

    /**
     * Get billing history data for an account.
     */
    public function getBillingHistoryData(Account $account, array $filters = []): array
    {
        $tariff = $this->billingRepository->getTariffTemplateForAccount($account);
        if (!$tariff) {
            throw new \Exception("No tariff template found for account #{$account->id}");
        }

        $isDateToDate = $tariff->isDateToDateBilling();
        $billDay = $account->bill_day ?: $tariff->billing_day ?: 1;

        // 1. Generate periods
        $periods  = [];
        $readings = $this->billingRepository->getAllReadingsForAccount($account);

        if ($isDateToDate) {
            // Date-to-date: each consecutive reading pair is one period
            if ($readings->count() >= 2) {
                $sorted = $readings->sortBy('reading_date')->values();
                for ($i = 0; $i < $sorted->count() - 1; $i++) {
                    $start = $sorted[$i]->reading_date;
                    $end   = $sorted[$i + 1]->reading_date;
                    $periods[] = [
                        'start' => $start instanceof Carbon ? $start->toDateString() : (string) $start,
                        'end'   => $end   instanceof Carbon ? $end->toDateString()   : (string) $end,
                    ];
                }
            }
        } else {
            // Monthly: enumerate periods via Calculator (Calendar-backed)
            $firstReading = $readings->first();
            $startDate    = $firstReading
                ? ($firstReading->reading_date instanceof Carbon ? $firstReading->reading_date->toDateString() : (string) $firstReading->reading_date)
                : Carbon::now()->subMonths(6)->toDateString();
            $endDate = Carbon::now()->toDateString();

            $periods = $this->billingCalc->calculatePeriods((int) $billDay, $startDate, $endDate);
        }

        // 2. Fetch bills for these periods
        $bills = Bill::where('account_id', $account->id)
            ->with(['meter', 'openingReading', 'closingReading'])
            ->get();

        // 3. Map bills to periods
        $history = [];
        foreach ($periods as $period) {
            $pStart = Carbon::parse($period['start'] ?? $period['start_date']);
            $pEnd = Carbon::parse($period['end'] ?? $period['end_date']);

            $periodBills = $bills->filter(function ($bill) use ($pStart, $pEnd) {
                if (!$bill->period_start_date)
                    return false;
                $bStart = Carbon::parse($bill->period_start_date);
                return $bStart->isSameDay($pStart);
            });

            $history[] = [
                'period_start' => $pStart->toDateString(),
                'period_end' => $pEnd->toDateString(),
                'bills' => $periodBills->map(fn($b) => [
                    'id' => $b->id,
                    'meter_number' => $b->meter->meter_number ?? 'N/A',
                    'consumption' => $b->consumption,
                    'total_amount' => $b->total_amount,
                    'status' => $b->status,
                ]),
                'total_amount' => (float) $periodBills->sum('total_amount'),
            ];
        }

        return [
            'account_id' => $account->id,
            'billing_type' => $isDateToDate ? 'DATE_TO_DATE' : 'MONTHLY',
            'history' => array_reverse($history),
        ];
    }

    /**
     * Get unique billing dates for an account.
     */
    public function getBillingDates(Account $account): array
    {
        $readings = $this->billingRepository->getAllReadingsForAccount($account);
        $dates = $readings->pluck('reading_date')->map(fn($d) => $d instanceof Carbon ? $d->toDateString() : $d)->unique()->values();

        return [
            'account_id' => $account->id,
            'dates' => $dates,
        ];
    }
}
