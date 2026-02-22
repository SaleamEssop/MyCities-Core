<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Meter;
use App\Models\Payment;
use App\Models\Site;
use App\Repositories\BillingRepository;
use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BillingController extends Controller
{
    private $billingRepository;
    private $dashboardService;

    public function __construct(
        BillingRepository $billingRepository,
        DashboardService $dashboardService
    ) {
        $this->billingRepository = $billingRepository;
        $this->dashboardService = $dashboardService;
    }

    /**
     * Get comprehensive dashboard data for the logged-in user.
     */
    public function getDashboard(Request $request): JsonResponse
    {
        $correlationId = $request->header('X-Request-ID', 'unknown');

        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not authenticated'], 401);
            }

            $account = $this->billingRepository->findAccount($request->input('accountId'), $user);
            if (!$account) {
                return response()->json(['success' => false, 'message' => 'Account not found or access denied'], 404);
            }

            $dashboardData = $this->dashboardService->getDashboardData($account);

            return response()->json([
                'success' => true,
                'data' => array_merge($dashboardData, [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ]
                ])
            ]);

        } catch (\Exception $e) {
            Log::channel('api')->error('Dashboard request - Exception', [
                'correlation_id' => $correlationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while loading dashboard data.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Add a payment for an account.
     */
    public function addPayment(Request $request): JsonResponse
    {
        $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|string',
            'reference' => 'nullable|string',
        ]);

        try {
            $payment = Payment::create([
                'account_id' => $request->account_id,
                'amount' => $request->amount,
                'payment_date' => $request->payment_date,
                'payment_method' => $request->payment_method,
                'reference' => $request->reference,
                'captured_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment added successfully',
                'payment' => $payment
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to add payment', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get all payments for an account.
     */
    public function getPayments(Account $account): JsonResponse
    {
        $payments = Payment::where('account_id', $account->id)->orderBy('payment_date', 'desc')->get();
        return response()->json(['success' => true, 'payments' => $payments]);
    }

    /**
     * Get billing history for the logged-in user.
     */
    public function getBillingHistoryForUser(): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not authenticated'], 401);
        }

        $account = $this->billingRepository->findAccount(null, $user);
        if (!$account) {
            return response()->json(['success' => false, 'message' => 'Account not found'], 404);
        }

        try {
            $data = $this->dashboardService->getBillingHistoryData($account);
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get billing history for a specific account.
     */
    public function getBillingHistory(Account $account): JsonResponse
    {
        try {
            $data = $this->dashboardService->getBillingHistoryData($account);
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get unique billing dates for an account.
     */
    public function getBillingDates(Account $account): JsonResponse
    {
        $data = $this->dashboardService->getBillingDates($account);
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Get projected bill data for an account.
     */
    public function getProjectedBill(Account $account): JsonResponse
    {
        try {
            $tariff = $this->billingRepository->getTariffTemplateForAccount($account);
            if (!$tariff) {
                return response()->json(['success' => false, 'message' => 'No tariff found'], 404);
            }

            $dashboardData = $this->dashboardService->getDashboardData($account);

            return response()->json([
                'success' => true,
                'data' => [
                    'projected_total' => $dashboardData['totals']['grand_total'] ?? 0,
                    'period' => $dashboardData['period'] ?? [],
                    'breakdown' => [
                        'water' => $dashboardData['water']['charges'] ?? [],
                        'electricity' => $dashboardData['electricity']['charges'] ?? [],
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Calculate a specific bill using the unified engine.
     */
    public function calculateBill(Request $request): JsonResponse
    {
        $request->validate(['bill_id' => 'required|exists:bills,id']);

        try {
            $calculator = new \App\Services\CalculatorPHP();
            $results = $calculator->computePeriod((int) $request->bill_id);

            return response()->json(['success' => true, 'data' => $results]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get line item traceability (DEPRECATED).
     */
    public function getLineItemTraceability(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Line item traceability feature has been deprecated. Use billing history endpoint instead.',
            'alternative' => '/api/v1/billing/history'
        ], 410);
    }

    /**
     * Serve traceability report file (DEPRECATED).
     */
    public function serveTraceabilityReport(string $filename): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Traceability report feature has been deprecated.'
        ], 410);
    }

    /**
     * Get daily consumption data for a meter.
     */
    public function getDailyConsumption(Meter $meter): JsonResponse
    {
        $latestBill = Bill::where('meter_id', $meter->id)->orderBy('created_at', 'desc')->first();
        return response()->json([
            'success' => true,
            'data' => [
                'meter_id' => $meter->id,
                'daily_usage' => $latestBill->daily_usage ?? 0,
                'is_provisional' => (bool) ($latestBill->is_provisional ?? true),
            ]
        ]);
    }

    /**
     * Get account tariff details.
     */
    public function getAccountTariff(Account $account): JsonResponse
    {
        $tariff = $this->billingRepository->getTariffTemplateForAccount($account);
        return response()->json(['success' => true, 'data' => $tariff]);
    }

    /**
     * Get tariff tiers for an account.
     */
    public function getTariffTiers(Account $account): JsonResponse
    {
        $tariff = $this->billingRepository->getTariffTemplateForAccount($account);
        return response()->json([
            'success' => true,
            'data' => [
                'water_in' => $tariff->water_in ?? [],
                'water_out' => $tariff->water_out ?? [],
                'fixed_costs' => $tariff->fixed_costs ?? [],
            ]
        ]);
    }

    /**
     * Test endpoint for CalculatorPHP.
     */
    public function testCalculatorPHP(Request $request): JsonResponse
    {
        try {
            $request->validate(['bill_id' => 'required|exists:bills,id']);
            $billId = $request->input('bill_id');

            $calculator = new \App\Services\CalculatorPHP();
            $results = $calculator->computePeriod((int) $billId);

            return response()->json(['success' => true, 'results' => $results]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Compute Period logic.
     */
    public function computePeriod(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'account_id' => 'required|exists:accounts,id',
                'meter_id' => 'required|exists:meters,id',
            ]);

            $account = $this->billingRepository->findAccount($request->input('account_id'), Auth::user());
            $meter = Meter::find($request->input('meter_id'));

            $calculator = new \App\Services\CalculatorPHP();
            $results = $calculator->computePeriod($account, $meter);

            return response()->json(['success' => true, 'data' => $results]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get period info (Compatibility).
     */
    public function getPeriodInfo($account, $tariff)
    {
        $calculator = app(\App\Services\BillingPeriodCalculator::class);
        $now = now();
        $billDay = $account->bill_day ?: $tariff->billing_day ?: 1;
        $start = $calculator->findPeriodStartForDate($now, $billDay);
        $end = $calculator->calculatePeriodEnd($start, $billDay);
        return ['start_date' => $start, 'end_date' => $end];
    }
}
