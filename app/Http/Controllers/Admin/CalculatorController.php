<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RegionsAccountTypeCost;
use App\Models\User;
use App\Services\Billing\Calendar;
use App\Services\Billing\Calculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CalculatorController — PD.md ↔ Calculator.php
 *
 * Serves the Billing Calculator Vue page (Admin/Calculator.vue).
 * Compute endpoint uses the clean Calculator.php implementation.
 */
class CalculatorController extends Controller
{
    /**
     * Render the calculator page with initial data as Inertia props.
     */
    public function index(): Response
    {
        $users = User::with([
            'sites.accounts' => fn ($q) => $q->select('id', 'site_id', 'account_name', 'account_number', 'bill_day'),
        ])->get()->map(fn ($u) => [
            'id'             => $u->id,
            'name'           => $u->name,
            'email'          => $u->email,
            'contact_number' => $u->contact_number,
            'accounts'       => $u->sites->flatMap(fn ($s) => $s->accounts)->values(),
        ]);

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
                'vat_rate'       => $t->vat_rate ?? $t->vat_percentage ?? 15,
                'is_water'       => (bool) $t->is_water,
                'is_electricity' => (bool) $t->is_electricity,
            ]);

        return Inertia::render('Admin/Calculator', [
            'users'           => $users,
            'tariffTemplates' => $templates,
            'today'           => now('Africa/Johannesburg')->format('Y-m-d'),
        ]);
    }

    /**
     * POST /admin/calculator/compute
     *
     * Runs Calculator.php::computePeriod() (clean PD.md implementation).
     */
    public function compute(Request $request): JsonResponse
    {
        $request->validate(['bill_id' => 'required|integer|exists:bills,id']);

        try {
            $calendar   = new Calendar();
            $calculator = new Calculator($calendar);
            $result     = $calculator->computePeriod((int) $request->bill_id);

            return response()->json($result);
        } catch (\Throwable $e) {
            \Log::error('CalculatorController::compute error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
