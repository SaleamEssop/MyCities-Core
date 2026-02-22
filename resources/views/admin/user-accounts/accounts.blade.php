@extends('admin.layouts.main')
@section('title', 'Accounts - ' . $account->account_name)

@section('content')
<style>
    /* Mobile-first constraints - max width matches mobile viewport (414px = iPhone Pro Max) */
    .accounts-container {
        max-width: 414px;
        width: 100%;
        margin: 0 auto;
        padding: 16px;
        background: #f8f9fa;
        min-height: 100vh;
        box-sizing: border-box;
    }
    
    /* Ensure content doesn't overflow on smaller screens */
    @media (max-width: 414px) {
        .accounts-container {
            max-width: 100%;
            padding: 12px;
        }
    }
    
    /* Prevent horizontal scrolling */
    body {
        overflow-x: hidden;
    }
    
    * {
        box-sizing: border-box;
    }
    
    .unified-header {
        background: white;
        padding: 16px;
        border-radius: 8px;
        margin-bottom: 16px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .header-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;
    }
    
    .logo-text {
        font-size: 28px;
        font-style: italic;
    }
    
    .logo-my {
        color: #000;
        font-weight: 300;
    }
    
    .logo-cities {
        color: #3294B8;
        font-weight: 500;
    }
    
    .user-label {
        font-size: 14px;
        color: #000;
        margin-top: 4px;
    }
    
    .current-date {
        font-size: 12px;
        color: #666;
        margin-top: 4px;
    }
    
    .page-label {
        font-size: 14px;
        color: #000;
    }
    
    .header-divider {
        height: 1px;
        background: #e0e0e0;
        margin: 12px 0;
    }
    
    .payment-section {
        background: white;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 16px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .payment-label {
        font-size: 14px;
        color: #666;
        margin-bottom: 12px;
        text-align: center;
    }
    
    .payment-input-container {
        background: #f8f9fa;
        border: 2px solid #ddd;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 12px;
        cursor: pointer;
    }
    
    .payment-input-container:focus-within {
        border-color: #3294B8;
    }
    
    .payment-input {
        width: 100%;
        border: none;
        background: transparent;
        font-size: 32px;
        font-weight: 700;
        text-align: center;
        color: #3294B8;
        outline: none;
    }
    
    .update-btn {
        width: 100%;
        padding: 12px;
        font-size: 16px;
        font-weight: 600;
    }
    
    .periods-container {
        margin-bottom: 100px; /* Space for bottom nav */
    }
    
    .period-card {
        background: white;
        border-radius: 12px;
        margin-bottom: 15px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .period-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        background: #e8e8e8;
    }
    
    .period-header.current {
        background: linear-gradient(135deg, #4ECDC4, #44A08D);
        color: #fff;
    }
    
    .period-dates {
        font-size: 15px;
        font-weight: 600;
    }
    
    .period-amount {
        font-size: 18px;
        font-weight: 700;
    }
    
    .period-header.current .period-amount {
        color: #fff;
    }
    
    .period-details {
        padding: 12px 20px;
    }
    
    .detail-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        font-size: 15px;
    }
    
    .detail-label {
        color: #000;
    }
    
    .detail-value {
        color: #000;
        font-weight: 500;
    }
    
    .detail-value.credit-value {
        color: #4CAF50;
    }
    
    .payment-row {
        color: #4CAF50;
    }
    
    .balance-row {
        border-top: 1px solid #e0e0e0;
        margin-top: 8px;
        padding-top: 12px;
        font-weight: 600;
    }
    
    .no-periods {
        text-align: center;
        padding: 40px;
        color: #888;
    }
    
    .fixed-nav-tabs {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        display: flex;
        justify-content: space-around;
        background: #fff;
        border-top: 1px solid #e0e0e0;
        padding: 8px 0;
        box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
        z-index: 100;
    }
    
    .nav-tab {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
        background: none;
        border: none;
        color: #666;
        font-size: 11px;
        padding: 6px 12px;
        cursor: pointer;
        transition: color 0.2s;
        text-decoration: none;
    }
    
    .nav-tab:hover {
        color: #3294B8;
    }
    
    .nav-tab.active {
        color: #3294B8;
    }
</style>

<div class="accounts-container" data-component="accounts-container" data-component-id="accounts-container-1">
    <!-- Back Button -->
    <a href="{{ route('user-accounts.manager') }}" class="btn btn-secondary mb-3" data-component="back-button" data-component-id="accounts-back-button-1">
        <i class="fas fa-arrow-left mr-2"></i> Back to User Manager
    </a>

    @if($accountData)
        <!-- Unified Header -->
        <div class="unified-header" data-component="dashboard-header" data-component-id="accounts-header-1">
            <div class="header-top" data-component="header-top" data-component-id="accounts-header-top-1">
                <div>
                    <div class="logo-text" data-component="logo" data-component-id="accounts-logo-1">
                        <span class="logo-my">My</span><span class="logo-cities">Cities</span>
                    </div>
                    <div class="user-label" data-component="user-label" data-component-id="accounts-user-label-1">User: {{ $accountData['user_name'] ?? '' }}</div>
                    <div class="current-date" data-component="current-date" data-component-id="accounts-current-date-1">Current Date: {{ \Carbon\Carbon::now()->format('dS F Y') }}</div>
                </div>
                <span class="page-label" data-component="page-label" data-component-id="accounts-page-label-1">Accounts</span>
            </div>
            <div class="header-divider" data-component="header-divider" data-component-id="accounts-header-divider-1"></div>
        </div>

        <!-- Payment Entry Section -->
        <div class="payment-section" data-component="payment-entry" data-component-id="payment-entry-1">
            <div class="payment-label" data-component="payment-label" data-component-id="payment-label-1">Click to enter a payment amount</div>
            <form method="POST" action="{{ route('user-accounts.manager.add-payment') }}" data-component="payment-form" data-component-id="payment-form-1">
                @csrf
                <input type="hidden" name="account_id" value="{{ $account->id }}" data-component="hidden-input" data-component-id="payment-account-id-1">
                <div class="payment-input-container" onclick="document.getElementById('paymentInput').focus()" data-component="payment-input-container" data-component-id="payment-input-container-1">
                    <input 
                        type="text" 
                        id="paymentInput"
                        name="amount" 
                        class="payment-input"
                        placeholder="R0.00"
                        pattern="[0-9.]*"
                        inputmode="decimal"
                        value="R{{ number_format(max(0, $accountData['total_owing'] ?? 0), 2) }}"
                        data-component="payment-input" 
                        data-component-id="payment-input-1"
                    />
                </div>
                <button type="submit" class="btn btn-primary update-btn" data-component="submit-button" data-component-id="payment-submit-1">
                    <i class="fas fa-check mr-2"></i>Update
                </button>
            </form>
        </div>

        <!-- Billing Periods -->
        <div class="periods-container" data-component="billing-periods" data-component-id="billing-periods-1">
            @if(isset($accountData['periods']) && count($accountData['periods']) > 0)
                @foreach($accountData['periods'] as $index => $period)
                    <div class="period-card" data-component="period-card" data-component-id="period-card-{{ $index + 1 }}">
                        <!-- Period Header -->
                        <div class="period-header {{ $index === 0 ? 'current' : '' }}" data-component="period-header" data-component-id="period-header-{{ $index + 1 }}">
                            <span class="period-dates" data-component="period-dates" data-component-id="period-dates-{{ $index + 1 }}">
                                {{ \Carbon\Carbon::parse($period['start_date'])->format('d M Y') }} &gt; 
                                {{ \Carbon\Carbon::parse($period['end_date'])->format('d M Y') }}
                            </span>
                            <span class="period-amount" data-component="period-amount" data-component-id="period-amount-{{ $index + 1 }}">
                                R{{ $period['balance'] < 0 ? '-' : '' }}{{ number_format(abs($period['balance']), 2) }}
                            </span>
                        </div>

                        <!-- Period Details -->
                        <div class="period-details" data-component="period-details" data-component-id="period-details-{{ $index + 1 }}">
                            <div class="detail-row" data-component="detail-row" data-component-id="period-consumption-{{ $index + 1 }}">
                                <span class="detail-label" data-component="detail-label" data-component-id="period-consumption-label-{{ $index + 1 }}">Consumption - {{ $period['days'] ?? 0 }} days</span>
                                <span class="detail-value" data-component="detail-value" data-component-id="period-consumption-value-{{ $index + 1 }}">R{{ number_format($period['consumption_charge'] ?? 0, 2) }}</span>
                            </div>
                            
                            @if(($period['balance_bf'] ?? 0) != 0)
                                <div class="detail-row" data-component="detail-row" data-component-id="period-balance-bf-{{ $index + 1 }}">
                                    <span class="detail-label" data-component="detail-label" data-component-id="period-balance-bf-label-{{ $index + 1 }}">{{ $period['balance_bf'] < 0 ? 'Credit B/F' : 'Balance B/F' }}</span>
                                    <span class="detail-value {{ $period['balance_bf'] < 0 ? 'credit-value' : '' }}" data-component="detail-value" data-component-id="period-balance-bf-value-{{ $index + 1 }}">
                                        {{ $period['balance_bf'] < 0 ? '-' : '' }}R{{ number_format(abs($period['balance_bf']), 2) }}
                                    </span>
                                </div>
                            @endif

                            <!-- Payments for this period -->
                            @if(isset($period['payments']) && count($period['payments']) > 0)
                                @foreach($period['payments'] as $paymentIndex => $payment)
                                    <div class="detail-row payment-row" data-component="detail-row" data-component-id="period-payment-{{ $index + 1 }}-{{ $paymentIndex + 1 }}">
                                        <span class="detail-label" data-component="detail-label" data-component-id="period-payment-label-{{ $index + 1 }}-{{ $paymentIndex + 1 }}">Payment - {{ \Carbon\Carbon::parse($payment['date'])->format('d M Y') }}</span>
                                        <span class="detail-value" data-component="detail-value" data-component-id="period-payment-value-{{ $index + 1 }}-{{ $paymentIndex + 1 }}">R{{ number_format($payment['amount'], 2) }}</span>
                                    </div>
                                @endforeach
                            @endif

                            <!-- Balance -->
                            <div class="detail-row balance-row" data-component="detail-row" data-component-id="period-balance-{{ $index + 1 }}">
                                <span class="detail-label" data-component="detail-label" data-component-id="period-balance-label-{{ $index + 1 }}">Balance</span>
                                <span class="detail-value {{ $period['balance'] < 0 ? 'credit-value' : '' }}" data-component="detail-value" data-component-id="period-balance-value-{{ $index + 1 }}">
                                    R{{ $period['balance'] < 0 ? '-' : '' }}{{ number_format(abs($period['balance']), 2) }}
                                </span>
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="no-periods" data-component="no-periods" data-component-id="no-periods-1">
                    <i class="fas fa-receipt" style="font-size: 48px; color: #ccc; margin-bottom: 12px;"></i>
                    <p>No billing periods found</p>
                </div>
            @endif
        </div>

        <!-- Fixed Bottom Navigation -->
        <div class="fixed-nav-tabs" data-component="bottom-navigation" data-component-id="accounts-bottom-nav-1">
            <a href="{{ route('user-accounts.dashboard', ['accountId' => $account->id]) }}" class="nav-tab" data-component="nav-tab" data-component-id="accounts-nav-home-1">
                <i class="fas fa-home" style="font-size: 22px;"></i>
                <span>Home</span>
            </a>
            <a href="{{ route('user-accounts.dashboard', ['accountId' => $account->id]) }}" class="nav-tab" data-component="nav-tab" data-component-id="accounts-nav-dashboard-1">
                <i class="fas fa-chart-line" style="font-size: 22px;"></i>
                <span>Dashboard</span>
            </a>
            <a href="{{ route('user-accounts.readings', ['accountId' => $account->id]) }}" class="nav-tab" data-component="nav-tab" data-component-id="accounts-nav-readings-1">
                <i class="fas fa-edit" style="font-size: 22px;"></i>
                <span>Readings</span>
            </a>
            <a href="{{ route('user-accounts.accounts', ['accountId' => $account->id]) }}" class="nav-tab active" data-component="nav-tab" data-component-id="accounts-nav-accounts-1">
                <i class="fas fa-user-circle" style="font-size: 22px;"></i>
                <span>Accounts</span>
            </a>
        </div>
    @else
        <div class="alert alert-danger" data-component="error-alert" data-component-id="accounts-error-1">
            <i class="fas fa-exclamation-circle mr-2"></i>Failed to load accounts data
        </div>
    @endif
</div>

<!-- Component Debug Script -->
<script src="{{ asset('js/component-debug.js') }}"></script>

<script>
// Format payment input
document.getElementById('paymentInput')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/[^0-9.]/g, '');
    if (value && !value.startsWith('R')) {
        const num = parseFloat(value) || 0;
        e.target.value = 'R' + num.toFixed(2);
    }
});
</script>
@endsection





















