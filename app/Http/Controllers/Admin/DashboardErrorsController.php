<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

/**
 * Dashboard Errors Controller
 * 
 * Manages persistent error display and clearing for dashboard
 */
class DashboardErrorsController extends Controller
{
    /**
     * Clear errors for an account (after fixing the issue)
     */
    public function clearErrors(Request $request, $accountId)
    {
        $account = Account::findOrFail($accountId);
        
        // Clear errors from session
        Session::forget('dashboard_errors');
        
        return redirect()->route('user-accounts.dashboard', ['accountId' => $accountId])
            ->with('success', 'Errors cleared. If the issue is fixed, errors should not return.');
    }
    
    /**
     * Show error details page
     */
    public function showErrors($accountId)
    {
        $account = Account::with(['site', 'tariffTemplate'])->findOrFail($accountId);
        $errors = Session::get('dashboard_errors', []);
        
        return view('admin.user-accounts.dashboard-errors', [
            'account' => $account,
            'errors' => $errors,
        ]);
    }
}



















