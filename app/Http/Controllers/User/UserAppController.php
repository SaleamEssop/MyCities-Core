<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class UserAppController extends Controller
{
    // ------------------------------------------------------------------
    // SPLASH — public, date-matched ad
    // ------------------------------------------------------------------
    public function splash()
    {
        $ad = DB::table('ads')
            ->orderBy('priority', 'asc')
            ->orderBy('created_at', 'desc')
            ->first();

        $nextUrl = Auth::check()
            ? route('user.dashboard')
            : route('user.login');

        return Inertia::render('UserApp/Splash', [
            'ad' => $ad ? [
                'id'      => $ad->id,
                'title'   => $ad->name,
                'content' => $ad->description ?? '',
                'image'   => $ad->image ?? null,
            ] : null,
            'nextUrl' => $nextUrl,
        ]);
    }

    // ------------------------------------------------------------------
    // DASHBOARD
    // ------------------------------------------------------------------
    public function dashboard(Request $request)
    {
        $user = Auth::user();

        $accounts = DB::table('accounts')
            ->join('sites', 'sites.id', '=', 'accounts.site_id')
            ->where('sites.user_id', $user->id)
            ->select('accounts.*')
            ->get();

        if ($accounts->isEmpty()) {
            return Inertia::render('UserApp/Dashboard', [
                'accounts'           => [],
                'currentAccount'     => null,
                'waterBill'          => null,
                'electricityBill'    => null,
                'readingDueInDays'   => null,
                'periodLabel'        => null,
                'periodIndex'        => 0,
                'today'              => now('Africa/Johannesburg')->format('d M Y'),
            ]);
        }

        // Active account: from session or first
        $accountId = $request->session()->get('user_account_id', $accounts->first()->id);
        $account   = $accounts->firstWhere('id', $accountId) ?? $accounts->first();

        // Period index (0 = current, -1 = previous, -2 = two back, etc.)
        $periodIndex = (int) $request->get('period', 0);

        $meters = DB::table('meters')
            ->join('meter_types', 'meter_types.id', '=', 'meters.meter_type_id')
            ->where('meters.account_id', $account->id)
            ->select('meters.*', 'meter_types.title as type_title')
            ->get();

        $waterBill       = null;
        $electricityBill = null;
        $readingDueInDays = null;

        foreach ($meters as $meter) {
            $billData   = $this->getBillDataForMeter($meter, $periodIndex);
            $typeTitle  = strtolower($meter->type_title ?? '');

            if ($typeTitle === 'water') {
                $waterBill = $billData;
                if ($billData) {
                    $readingDueInDays = $this->calcReadingDueDays(
                        $meter->id,
                        $billData['period_end_date']
                    );
                }
            } elseif ($typeTitle === 'electricity') {
                $electricityBill = $billData;
                if ($readingDueInDays === null && $billData) {
                    $readingDueInDays = $this->calcReadingDueDays(
                        $meter->id,
                        $billData['period_end_date']
                    );
                }
            }
        }

        $periodLabel = null;
        if ($waterBill) {
            $periodLabel = date('d M Y', strtotime($waterBill['period_start_date']))
                . ' to '
                . date('d M Y', strtotime($waterBill['period_end_date']));
        } elseif ($electricityBill) {
            $periodLabel = date('d M Y', strtotime($electricityBill['period_start_date']))
                . ' to '
                . date('d M Y', strtotime($electricityBill['period_end_date']));
        }

        return Inertia::render('UserApp/Dashboard', [
            'accounts'           => $accounts->map(fn($a) => [
                'id'             => $a->id,
                'account_number' => $a->account_number,
            ])->values(),
            'currentAccount'     => [
                'id'             => $account->id,
                'account_number' => $account->account_number,
            ],
            'waterBill'          => $waterBill,
            'electricityBill'    => $electricityBill,
            'readingDueInDays'   => $readingDueInDays,
            'periodLabel'        => $periodLabel,
            'periodIndex'        => $periodIndex,
            'today'              => now('Africa/Johannesburg')->format('d M Y'),
        ]);
    }

    // ------------------------------------------------------------------
    // WATER READING PAGE
    // ------------------------------------------------------------------
    public function waterReading(Request $request)
    {
        $user    = Auth::user();
        $account = $this->getCurrentAccount($user, $request);

        if (!$account) {
            return redirect()->route('user.dashboard');
        }

        $meter = DB::table('meters')
            ->join('meter_types', 'meter_types.id', '=', 'meters.meter_type_id')
            ->where('meters.account_id', $account->id)
            ->where('meter_types.title', 'Water')
            ->select('meters.*')
            ->first();

        if (!$meter) {
            return redirect()->route('user.dashboard');
        }

        $readings = DB::table('meter_readings')
            ->where('meter_id', $meter->id)
            ->orderBy('reading_date', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                'id'            => $r->id,
                'reading_date'  => date('d M Y', strtotime($r->reading_date)),
                'reading_value' => self::formatWaterReading($r->reading_value),
                'reading_type'  => $r->reading_type ?? 'Actual',
            ])->values();

        $latestBill = DB::table('bills')
            ->where('meter_id', $meter->id)
            ->orderBy('period_end_date', 'desc')
            ->first();

        return Inertia::render('UserApp/WaterReading', [
            'meter' => [
                'id'            => $meter->id,
                'meter_number'  => $meter->meter_number
                    ?? ('M' . str_pad($meter->id, 6, '0', STR_PAD_LEFT)),
                'start_reading' => self::formatWaterReading($meter->start_reading ?? 0),
                'start_date'    => $meter->start_reading_date
                    ? date('d M Y', strtotime($meter->start_reading_date))
                    : null,
            ],
            'readings'    => $readings,
            'periodLabel' => $latestBill
                ? date('d M Y', strtotime($latestBill->period_start_date))
                  . ' to '
                  . date('d M Y', strtotime($latestBill->period_end_date))
                : null,
        ]);
    }

    // ------------------------------------------------------------------
    // ELECTRICITY READING PAGE
    // ------------------------------------------------------------------
    public function electricityReading(Request $request)
    {
        $user    = Auth::user();
        $account = $this->getCurrentAccount($user, $request);

        if (!$account) {
            return redirect()->route('user.dashboard');
        }

        $meter = DB::table('meters')
            ->join('meter_types', 'meter_types.id', '=', 'meters.meter_type_id')
            ->where('meters.account_id', $account->id)
            ->where('meter_types.title', 'Electricity')
            ->select('meters.*')
            ->first();

        if (!$meter) {
            return redirect()->route('user.dashboard');
        }

        $readings = DB::table('meter_readings')
            ->where('meter_id', $meter->id)
            ->orderBy('reading_date', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                'id'            => $r->id,
                'reading_date'  => date('d M Y', strtotime($r->reading_date)),
                'reading_value' => self::formatElectricityReading($r->reading_value),
                'reading_type'  => $r->reading_type ?? 'Actual',
            ])->values();

        $latestBill = DB::table('bills')
            ->where('meter_id', $meter->id)
            ->orderBy('period_end_date', 'desc')
            ->first();

        return Inertia::render('UserApp/ElectricityReading', [
            'meter' => [
                'id'            => $meter->id,
                'meter_number'  => $meter->meter_number
                    ?? ('E' . str_pad($meter->id, 6, '0', STR_PAD_LEFT)),
                'start_reading' => self::formatElectricityReading($meter->start_reading ?? 0),
                'start_date'    => $meter->start_reading_date
                    ? date('d M Y', strtotime($meter->start_reading_date))
                    : null,
            ],
            'readings'    => $readings,
            'periodLabel' => $latestBill
                ? date('d M Y', strtotime($latestBill->period_start_date))
                  . ' to '
                  . date('d M Y', strtotime($latestBill->period_end_date))
                : null,
        ]);
    }

    // ------------------------------------------------------------------
    // READING HISTORY
    // ------------------------------------------------------------------
    public function readingHistory(Request $request)
    {
        $user    = Auth::user();
        $account = $this->getCurrentAccount($user, $request);

        if (!$account) {
            return redirect()->route('user.dashboard');
        }

        $meters = DB::table('meters')
            ->join('meter_types', 'meter_types.id', '=', 'meters.meter_type_id')
            ->where('meters.account_id', $account->id)
            ->select('meters.*', 'meter_types.title as type_title')
            ->get();

        $allReadings = [];
        foreach ($meters as $meter) {
            $typeTitle = strtolower($meter->type_title ?? '');
            $rows = DB::table('meter_readings')
                ->where('meter_id', $meter->id)
                ->orderBy('reading_date', 'desc')
                ->get()
                ->map(fn($r) => [
                    'id'            => $r->id,
                    'meter_type'    => $typeTitle,
                    'reading_date'  => date('d M Y', strtotime($r->reading_date)),
                    'reading_value' => $typeTitle === 'water'
                        ? self::formatWaterReading($r->reading_value)
                        : self::formatElectricityReading($r->reading_value),
                    'reading_type'  => $r->reading_type ?? 'Actual',
                    '_sort_date'    => $r->reading_date,
                ])->values()->all();

            $allReadings = array_merge($allReadings, $rows);
        }

        usort($allReadings, fn($a, $b) => strcmp($b['_sort_date'], $a['_sort_date']));

        // Strip internal sort key
        $allReadings = array_map(function ($r) {
            unset($r['_sort_date']);
            return $r;
        }, $allReadings);

        return Inertia::render('UserApp/ReadingHistory', [
            'readings' => array_values($allReadings),
        ]);
    }

    // ------------------------------------------------------------------
    // ACCOUNT STATEMENT
    // ------------------------------------------------------------------
    public function account(Request $request)
    {
        $user    = Auth::user();
        $account = $this->getCurrentAccount($user, $request);

        if (!$account) {
            return redirect()->route('user.dashboard');
        }

        $bills = DB::table('bills')
            ->where('account_id', $account->id)
            ->orderBy('period_end_date', 'desc')
            ->limit(12)
            ->get()
            ->map(fn($b) => [
                'id'           => $b->id,
                'period_start' => date('d M Y', strtotime($b->period_start_date)),
                'period_end'   => date('d M Y', strtotime($b->period_end_date)),
                'bill_total'   => number_format((float)($b->bill_total ?? 0), 2),
                'status'       => $b->status ?? 'calculated',
                'is_current'   => in_array($b->status ?? '', ['open', 'calculated']),
            ])->values();

        return Inertia::render('UserApp/AccountStatement', [
            'account' => [
                'id'             => $account->id,
                'account_number' => $account->account_number,
                'name_on_bill'   => $account->name_on_bill ?? $user->name,
            ],
            'bills' => $bills,
        ]);
    }

    // ------------------------------------------------------------------
    // API: Store Reading (JSON)
    // ------------------------------------------------------------------
    public function storeReading(Request $request)
    {
        $data = $request->validate([
            'meter_id'      => ['required', 'integer', 'exists:meters,id'],
            'reading_date'  => ['required', 'date'],
            'reading_value' => ['required', 'numeric', 'min:0'],
            'reading_type'  => ['sometimes', 'string', 'in:Actual,Estimated,Provisional'],
        ]);

        // Verify the meter belongs to the authenticated user
        $meter = DB::table('meters')->where('id', $data['meter_id'])->first();
        $account = DB::table('accounts')
            ->join('sites', 'sites.id', '=', 'accounts.site_id')
            ->where('accounts.id', $meter->account_id)
            ->where('sites.user_id', Auth::id())
            ->select('accounts.*')
            ->first();

        if (!$account) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $id = DB::table('meter_readings')->insertGetId([
            'meter_id'      => $data['meter_id'],
            'reading_date'  => $data['reading_date'],
            'reading_value' => $data['reading_value'],
            'reading_type'  => $data['reading_type'] ?? 'Actual',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return response()->json(['success' => true, 'id' => $id]);
    }

    // ------------------------------------------------------------------
    // API: Dashboard data (JSON — period switching)
    // ------------------------------------------------------------------
    public function dashboardData(Request $request)
    {
        $user    = Auth::user();
        $account = $this->getCurrentAccount($user, $request);

        if (!$account) {
            return response()->json(['error' => 'No account found'], 404);
        }

        $periodIndex = (int) $request->get('period', 0);
        $meters      = DB::table('meters')
            ->join('meter_types', 'meter_types.id', '=', 'meters.meter_type_id')
            ->where('meters.account_id', $account->id)
            ->select('meters.*', 'meter_types.title as type_title')
            ->get();

        $waterBill = $electricityBill = null;
        foreach ($meters as $meter) {
            $billData  = $this->getBillDataForMeter($meter, $periodIndex);
            $typeTitle = strtolower($meter->type_title ?? '');
            if ($typeTitle === 'water')       $waterBill       = $billData;
            if ($typeTitle === 'electricity') $electricityBill = $billData;
        }

        return response()->json([
            'waterBill'       => $waterBill,
            'electricityBill' => $electricityBill,
        ]);
    }

    // ------------------------------------------------------------------
    // API: Accounts list
    // ------------------------------------------------------------------
    public function accountsList()
    {
        $accounts = DB::table('accounts')
            ->join('sites', 'sites.id', '=', 'accounts.site_id')
            ->where('sites.user_id', Auth::id())
            ->select('accounts.id', 'accounts.account_number')
            ->get();

        return response()->json($accounts);
    }

    // ====================================================================
    // PRIVATE HELPERS
    // ====================================================================

    private function getCurrentAccount($user, Request $request)
    {
        $accountId = $request->session()->get('user_account_id');

        if ($accountId) {
            $account = DB::table('accounts')
                ->join('sites', 'sites.id', '=', 'accounts.site_id')
                ->where('accounts.id', $accountId)
                ->where('sites.user_id', $user->id)
                ->select('accounts.*')
                ->first();
            if ($account) return $account;
        }

        return DB::table('accounts')
            ->join('sites', 'sites.id', '=', 'accounts.site_id')
            ->where('sites.user_id', $user->id)
            ->select('accounts.*')
            ->first();
    }

    private function getBillDataForMeter(object $meter, int $periodIndex): ?array
    {
        $bills = DB::table('bills')
            ->where('meter_id', $meter->id)
            ->orderBy('period_end_date', 'desc')
            ->get();

        $index = abs($periodIndex);
        $bill  = $bills->get($index);

        if (!$bill) return null;

        $breakdown = is_string($bill->breakdown ?? null)
            ? json_decode($bill->breakdown, true)
            : (array)($bill->breakdown ?? []);

        return [
            'bill_id'               => $bill->id,
            'period_start_date'     => $bill->period_start_date,
            'period_end_date'       => $bill->period_end_date,
            'daily_usage'           => $bill->daily_usage ?? 0,
            'total_usage'           => $breakdown['usage_l'] ?? 0,
            'usage_charge'          => number_format((float)($bill->usage_charge ?? 0), 2),
            'fixed_total'           => number_format((float)($bill->fixed_total ?? 0), 2),
            'vat_amount'            => number_format((float)($bill->vat_amount ?? 0), 2),
            'bill_total'            => number_format((float)($bill->bill_total ?? 0), 2),
            'projected_charge'      => number_format((float)($bill->bill_total ?? 0), 2),
            'status'                => $bill->status ?? 'calculated',
            'breakdown'             => $breakdown,
            'consumption_charge'    => number_format((float)($breakdown['consumption_charge'] ?? ($bill->usage_charge ?? 0)), 2),
            'discharge_charge'      => number_format((float)($breakdown['discharge_charge'] ?? 0), 2),
            'infrastructure_charge' => number_format((float)($breakdown['infrastructure_charge'] ?? 0), 2),
            'rates'                 => number_format((float)($breakdown['rates'] ?? 0), 2),
        ];
    }

    private function calcReadingDueDays(int $meterId, ?string $periodEndDate): ?int
    {
        if (!$periodEndDate) return null;

        $end  = \Carbon\Carbon::parse($periodEndDate, 'Africa/Johannesburg');
        $now  = now('Africa/Johannesburg');
        $days = (int) $now->diffInDays($end, false);

        return $days >= 0 ? $days : null;
    }

    /**
     * Format water reading as "NNNN - NN" (4 integer + 2 decimal, always 6 digits total).
     * e.g. 4.5 → "0004 - 50", 9653.53 → "9653 - 53"
     */
    public static function formatWaterReading(float|int|string $value): string
    {
        $f          = number_format((float) $value, 2, '.', '');
        [$int, $dec] = explode('.', $f);

        return str_pad($int, 4, '0', STR_PAD_LEFT)
             . ' - '
             . str_pad($dec, 2, '0', STR_PAD_RIGHT);
    }

    /**
     * Format electricity reading as "NNNNNN" (always 6 digits).
     * e.g. 123327 → "123327", 42 → "000042"
     */
    public static function formatElectricityReading(float|int|string $value): string
    {
        return str_pad((string)(int) $value, 6, '0', STR_PAD_LEFT);
    }
}
