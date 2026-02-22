@extends('admin.layouts.main')
@section('title', 'Dashboard - ' . ($account->account_name ?? 'Unknown Account'))

@section('content')
<style>
    /* Mobile-first constraints - max width matches mobile viewport (414px = iPhone Pro Max) */
    .dashboard-container {
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
        .dashboard-container {
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
    
    .dashboard-header {
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
    
    .dashboard-label {
        font-size: 14px;
        color: #000;
    }
    
    .header-divider {
        height: 1px;
        background: #e0e0e0;
        margin: 12px 0;
    }
    
    .total-amount-row {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 16px;
        padding: 8px 0;
    }
    
    .nav-arrow {
        background: none;
        border: none;
        font-size: 32px;
        color: #000;
        cursor: pointer;
        padding: 8px 16px;
        transition: opacity 0.2s;
    }
    
    .nav-arrow:hover:not(:disabled) {
        opacity: 0.7;
    }
    
    .nav-arrow:disabled {
        opacity: 0.3;
        cursor: not-allowed;
    }
    
    .total-amount {
        font-size: 36px;
        font-weight: 700;
        color: #3294B8;
        text-align: center;
    }
    
    .period-text {
        font-size: 13px;
        font-weight: 600;
        color: #000;
        text-align: center;
        padding: 8px 0;
    }
    
    .view-only-indicator {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 0;
        color: #999;
        font-size: 14px;
        font-style: italic;
    }
    
    .meter-section {
        margin-bottom: 16px;
    }
    
    .meter-type-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 2px solid #3294B8;
    }
    
    .meter-type-header.electricity {
        border-bottom-color: #FF9800;
        margin-top: 24px;
    }
    
    .meter-icon {
        color: #3294B8;
    }
    
    .meter-type-header.electricity .meter-icon {
        color: #FF9800;
    }
    
    .meter-type-name {
        font-size: 18px;
        font-weight: 600;
        color: #000;
    }
    
    .meter-header {
        display: flex;
        align-items: center;
        background: #3294B8;
        color: white;
        padding: 12px 16px;
        gap: 12px;
        border-radius: 8px 8px 0 0;
        margin-bottom: 0;
    }
    
    .meter-header.electricity {
        background: #3294B8;
    }
    
    .meter-section {
        background: white;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 24px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .meter-icon {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .meter-stats {
        display: flex;
        flex: 1;
        justify-content: space-around;
    }
    
    .stat-item {
        text-align: center;
    }
    
    .stat-label {
        font-size: 10px;
        opacity: 0.9;
        margin-bottom: 2px;
    }
    
    .stat-value {
        font-size: 14px;
        font-weight: 600;
    }
    
    .billing-summary {
        padding: 16px;
        background: white;
        border-radius: 0 0 8px 8px;
    }
    
    .projected-charges {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        margin-top: 12px;
        border-top: 1px solid #e0e0e0;
        font-size: 15px;
        font-weight: 600;
        color: #3294B8;
        flex-wrap: nowrap;
    }
    
    .projected-charges-label {
        display: flex;
        align-items: center;
        gap: 8px;
        flex: 1;
        min-width: 0;
    }
    
    .projected-arrow {
        font-size: 16px;
    }
    
    .billing-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        flex-wrap: nowrap;
    }
    
    .billing-label {
        font-size: 15px;
        color: #000;
        flex: 1;
        min-width: 0;
    }
    
    .billing-amount {
        font-size: 15px;
        color: #000;
        font-weight: 500;
        text-align: right;
        flex-shrink: 0;
        min-width: 100px;
        white-space: nowrap;
    }
    
    .tier-label-container {
        display: flex;
        flex-direction: column;
        flex: 1;
    }
    
    .tier-label-main {
        font-size: 15px;
        color: #000;
    }
    
    .tier-label-sub {
        font-size: 11px;
        color: #666;
        margin-top: 2px;
    }
    
    .billing-divider {
        height: 1px;
        background: #e0e0e0;
        margin: 8px 0;
    }
    
    .period-total-row {
        padding-top: 12px;
    }
    
    .period-total-label {
        font-size: 18px;
        font-weight: 700;
        color: #3294B8;
    }
    
    .period-total-amount {
        font-size: 18px;
        font-weight: 700;
        color: #3294B8;
    }
    
    .details-toggle {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 12px;
        background: #e3f2fd;
        border-radius: 6px;
        cursor: pointer;
        margin-bottom: 12px;
        font-size: 13px;
        color: #1976d2;
        font-weight: 500;
    }
    
    .details-toggle:hover {
        background: #bbdefb;
    }
    
    .breakdown-details {
        background: #f8f9fa;
        border-radius: 6px;
        padding: 8px 12px;
        margin-bottom: 12px;
    }
    
    .detail-row {
        font-size: 12px;
        color: #555;
        padding: 4px 0;
    }
    
    .closing-reading-row {
        font-size: 14px;
        color: #333;
        padding: 8px 0;
        margin-bottom: 8px;
    }
    
    .estimate-warning {
        font-size: 13px;
        color: #666;
        font-style: italic;
        padding: 12px 0 4px;
        text-align: center;
    }
    
    .past-period-summary {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 16px;
    }
    
    .usage-stats-box {
        display: flex;
        justify-content: space-around;
        background: #0A485E;
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 15px;
    }
    
    .usage-stat-item {
        text-align: center;
        color: white;
    }
    
    .usage-stat-item .stat-label {
        display: block;
        font-size: 11px;
        opacity: 0.9;
        text-transform: uppercase;
        margin-bottom: 4px;
    }
    
    .usage-stat-item .stat-value {
        font-size: 16px;
        font-weight: 700;
    }
    
    .meter-breakdown-box {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 12px;
        margin-bottom: 15px;
    }
    
    .breakdown-title {
        font-size: 11px;
        font-weight: 600;
        color: #666;
        text-transform: uppercase;
        margin-bottom: 10px;
    }
    
    .meter-line {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        font-size: 13px;
        border-bottom: 1px solid #eee;
    }
    
    .meter-line:last-child {
        border-bottom: none;
    }
    
    .summary-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        font-size: 15px;
    }
    
    .summary-row.payment .payment-amount {
        color: #4CAF50;
    }
    
    .summary-row.balance {
        border-top: 1px solid #e0e0e0;
        margin-top: 8px;
        padding-top: 12px;
        font-weight: 600;
    }
</style>

<div class="dashboard-container" data-component="dashboard-container" data-component-id="dashboard-container-1">
    <!-- Back Button -->
    <a href="{{ route('user-accounts.manager') }}" class="btn btn-secondary mb-3" data-component="back-button" data-component-id="dashboard-back-button-1">
        <i class="fas fa-arrow-left mr-2"></i> Back to User Manager
    </a>

    @if($dashboardData)
        <div class="dashboard-header" data-component="dashboard-header" data-component-id="dashboard-header-1">
            <!-- Current Date -->
            <div class="current-date" data-component="current-date" data-component-id="current-date-1">
                Current date: {{ \Carbon\Carbon::now()->format('d M Y') }}
            </div>
            
            <!-- Top row: Logo + User + Dashboard -->
            <div class="header-top" data-component="header-top" data-component-id="header-top-1">
                <div>
                    <div class="logo-text" data-component="logo" data-component-id="logo-1">
                        <span class="logo-my">My</span><span class="logo-cities">Cities</span>
                    </div>
                    <div class="user-label" data-component="user-label" data-component-id="user-label-1">User: {{ $dashboardData['account']['name'] ?? '' }}</div>
                </div>
                <span class="dashboard-label" data-component="page-label" data-component-id="page-label-1">Dashboard</span>
            </div>
            
            <div class="header-divider" data-component="header-divider" data-component-id="header-divider-1"></div>
            
            <!-- Final Reading Due Notification (only for current period) -->
            @if($currentPeriodIndex === 0)
                <div class="reading-due-notification" data-component="reading-due-notification" data-component-id="reading-due-notification-1">
                    <span class="reading-due-arrow" data-component="reading-due-arrow-left" data-component-id="reading-due-arrow-left-1">&#8592;</span>
                    <span data-component="reading-due-text" data-component-id="reading-due-text-1">Final Reading due in 3 days</span>
                    <span class="reading-due-arrow" data-component="reading-due-arrow-right" data-component-id="reading-due-arrow-right-1">&#8594;</span>
                </div>
            @endif

            <!-- Total Amount with Period Navigation -->
            <div class="total-amount-row" data-component="period-navigation" data-component-id="period-navigation-1">
                @php
                    $currentPeriod = $currentPeriodIndex === 0 ? $dashboardData : ($allPeriods[$currentPeriodIndex - 1] ?? null);
                    $canGoBack = $currentPeriodIndex < count($allPeriods);
                    $canGoForward = $currentPeriodIndex > 0;
                    $grandTotal = $currentPeriodIndex === 0 
                        ? ($dashboardData['totals']['grand_total'] ?? 0)
                        : ($currentPeriod['consumption_charge'] ?? 0);
                @endphp
                
                <a href="{{ route('user-accounts.dashboard', ['accountId' => $account->id, 'period' => $currentPeriodIndex + 1]) }}" 
                   class="nav-arrow" 
                   data-component="nav-arrow-left" data-component-id="nav-arrow-left-1"
                   @if(!$canGoBack) style="pointer-events: none; opacity: 0.3;" @endif>
                    &#8592;
                </a>
                <div class="total-amount" data-component="grand-total" data-component-id="grand-total-1">
                    R{{ number_format($grandTotal, 2, '.', '') }}
                </div>
                <a href="{{ route('user-accounts.dashboard', ['accountId' => $account->id, 'period' => max(0, $currentPeriodIndex - 1)]) }}" 
                   class="nav-arrow" 
                   data-component="nav-arrow-right" data-component-id="nav-arrow-right-1"
                   @if(!$canGoForward) style="pointer-events: none; opacity: 0.3;" @endif>
                    &#8594;
                </a>
            </div>

            <!-- Period Display -->
            <div class="period-text" data-component="period-display" data-component-id="period-display-1">
                @if($currentPeriodIndex === 0)
                    @if(isset($dashboardData['period']) && $dashboardData['period'] && isset($dashboardData['period']['start_date']) && isset($dashboardData['period']['end_date']))
                        Period: From {{ \Carbon\Carbon::parse($dashboardData['period']['start_date'])->format('d F Y') }} 
                        to {{ \Carbon\Carbon::parse($dashboardData['period']['end_date'])->format('d F Y') }}
                    @else
                        Period: Not Available
                    @endif
                @else
                    @if(isset($currentPeriod) && isset($currentPeriod['start_date']) && isset($currentPeriod['end_date']))
                        Period: From {{ \Carbon\Carbon::parse($currentPeriod['start_date'])->format('d F Y') }} 
                        to {{ \Carbon\Carbon::parse($currentPeriod['end_date'])->format('d F Y') }}
                    @else
                        Period: Not Available
                    @endif
                @endif
            </div>

            <!-- View Only indicator for past periods -->
            @if($currentPeriodIndex > 0)
                <div class="view-only-indicator" data-component="view-only-indicator" data-component-id="view-only-indicator-1">
                    <i class="fas fa-history"></i>
                    <span>Viewing Past Period (Read Only)</span>
                </div>
            @endif

            <!-- Past Period Sections (Read-Only) -->
            @if($currentPeriodIndex > 0 && isset($currentPeriod))
                <!-- Status Indicator -->
                @php
                    $statusLabels = [
                        'ACTUAL' => 'Actual',
                        'PROVISIONAL' => 'Provisional',
                        'CALCULATED' => 'Calculated',
                        'PROJECTED' => 'Projected'
                    ];
                    $periodStatus = $currentPeriod['usage_status'] ?? 'PROVISIONAL';
                    $statusLabel = $statusLabels[$periodStatus] ?? $periodStatus;
                @endphp
                <div class="status-indicator" style="background: #e3f2fd; padding: 8px; border-radius: 4px; margin-bottom: 16px; text-align: center;">
                    <strong>Status: {{ $statusLabel }}</strong>
                </div>
                
                <!-- PAST PERIOD WATER SECTION -->
                @php
                    $pastWaterEnabled = ($currentPeriod['water']['enabled'] ?? false) || !empty($currentPeriod['water']);
                    $pastWaterMeter = $currentPeriod['water']['meter'] ?? null;
                    $hasPastWaterMeter = isset($currentPeriod['water']['meter']) && $currentPeriod['water']['meter'];
                    $hasPastWaterReadings = !empty($currentPeriod['water']['readings'] ?? []);
                    $shouldShowPastWater = ($pastWaterEnabled || $hasPastWaterMeter || $hasPastWaterReadings);
                @endphp
                @if($shouldShowPastWater)
                    <div class="meter-section" data-component="past-water-meter-section" data-component-id="past-water-meter-section-1">
                        <!-- Water Type Header -->
                        <div class="meter-type-header" data-component="past-water-type-header" data-component-id="past-water-type-header-1">
                            <i class="fas fa-tint meter-icon" style="font-size: 24px;"></i>
                            <span class="meter-type-name">Water</span>
                        </div>
                        
                        <!-- Water Meter Header Bar with Stats -->
                        <div class="meter-header water" data-component="past-water-meter-header" data-component-id="past-water-meter-header-1">
                            <div class="meter-icon" data-component="meter-icon" data-component-id="past-water-meter-icon-1">
                                <i class="fas fa-tint" style="font-size: 24px;"></i>
                            </div>
                            <div class="meter-stats" data-component="meter-stats" data-component-id="past-water-meter-stats-1">
                                <div class="stat-item" data-component="stat-item" data-component-id="past-water-stat-daily-1">
                                    <div class="stat-label">Daily Usage</div>
                                    <div class="stat-value">{{ $currentPeriod['water']['daily_average_formatted'] ?? '0 L' }}</div>
                                </div>
                                <div class="stat-item" data-component="stat-item" data-component-id="past-water-stat-total-1">
                                    <div class="stat-label">Total Usage</div>
                                    <div class="stat-value">{{ number_format($currentPeriod['water']['consumption_kl'] ?? 0, 2) }} kl</div>
                                </div>
                                <div class="stat-item" data-component="stat-item" data-component-id="past-water-stat-cost-1">
                                    <div class="stat-label">Daily Cost</div>
                                    <div class="stat-value">R {{ number_format(($currentPeriod['water']['charges']['total'] ?? 0) / max(1, ($currentPeriod['days'] ?? 30)), 2) }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Water Billing Summary -->
                        <div class="billing-summary" data-component="past-water-billing-summary" data-component-id="past-water-billing-summary-1">
                            @php
                                $pastWaterCharges = [];
                                if (($currentPeriod['water']['charges']['total'] ?? 0) > 0) {
                                    $pastWaterCharges[] = [
                                        'label' => 'Consumption Charges',
                                        'amount' => $currentPeriod['water']['charges']['total'] ?? 0
                                    ];
                                }
                            @endphp
                            
                            @foreach($pastWaterCharges as $index => $charge)
                                <div class="billing-row" data-component="past-water-charge" data-component-id="past-water-charge-{{ $index + 1 }}">
                                    <span class="billing-label">{{ $charge['label'] }}</span>
                                    <span class="billing-amount">R {{ number_format($charge['amount'], 2) }}</span>
                                </div>
                            @endforeach

                            <!-- Period Total -->
                            <div class="projected-charges" data-component="past-water-projected-charges" data-component-id="past-water-projected-charges-1">
                                <span class="projected-charges-label" data-component="past-water-projected-label" data-component-id="past-water-projected-label-1">
                                    <span class="projected-arrow" data-component="past-water-projected-arrow" data-component-id="past-water-projected-arrow-1">→</span>
                                    Period Total
                                </span>
                                <span class="billing-amount" data-component="past-water-projected-amount" data-component-id="past-water-projected-amount-1">R {{ number_format($currentPeriod['water']['totals']['period_total'] ?? 0, 2) }}</span>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- PAST PERIOD ELECTRICITY SECTION -->
                @php
                    $pastElectricityEnabled = ($currentPeriod['electricity']['enabled'] ?? false) || !empty($currentPeriod['electricity']);
                    $pastElectricityMeter = $currentPeriod['electricity']['meter'] ?? null;
                    $hasPastElectricityMeter = isset($currentPeriod['electricity']['meter']) && $currentPeriod['electricity']['meter'];
                    $hasPastElectricityReadings = !empty($currentPeriod['electricity']['readings'] ?? []);
                    $shouldShowPastElectricity = ($pastElectricityEnabled || $hasPastElectricityMeter || $hasPastElectricityReadings);
                @endphp
                @if($shouldShowPastElectricity)
                    <div class="meter-section" data-component="past-electricity-meter-section" data-component-id="past-electricity-meter-section-1">
                        <!-- Electricity Type Header -->
                        <div class="meter-type-header electricity" data-component="past-electricity-type-header" data-component-id="past-electricity-type-header-1">
                            <i class="fas fa-bolt meter-icon" style="font-size: 24px;"></i>
                            <span class="meter-type-name">Electricity</span>
                        </div>
                        
                        <!-- Electricity Meter Header Bar with Stats -->
                        <div class="meter-header electricity" data-component="past-electricity-meter-header" data-component-id="past-electricity-meter-header-1">
                            <div class="meter-icon" data-component="meter-icon" data-component-id="past-electricity-meter-icon-1">
                                <i class="fas fa-bolt" style="font-size: 24px;"></i>
                            </div>
                            <div class="meter-stats" data-component="meter-stats" data-component-id="past-electricity-meter-stats-1">
                                <div class="stat-item" data-component="stat-item" data-component-id="past-electricity-stat-daily-1">
                                    <div class="stat-label">Daily Usage</div>
                                    <div class="stat-value">{{ $currentPeriod['electricity']['daily_average_formatted'] ?? '0 kWh' }}</div>
                                </div>
                                <div class="stat-item" data-component="stat-item" data-component-id="past-electricity-stat-cost-1">
                                    <div class="stat-label">Daily Cost</div>
                                    <div class="stat-value">R {{ number_format(($currentPeriod['electricity']['charges']['total'] ?? 0) / max(1, ($currentPeriod['days'] ?? 30)), 2) }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Electricity Billing Summary -->
                        <div class="billing-summary" data-component="past-electricity-billing-summary" data-component-id="past-electricity-billing-summary-1">
                            @php
                                $pastElectricityCharges = [];
                                if (($currentPeriod['electricity']['charges']['total'] ?? 0) > 0) {
                                    $pastElectricityCharges[] = [
                                        'label' => 'Consumption Charges',
                                        'amount' => $currentPeriod['electricity']['charges']['total'] ?? 0
                                    ];
                                }
                                if (($currentPeriod['electricity']['totals']['vat_amount'] ?? 0) > 0) {
                                    $pastElectricityCharges[] = [
                                        'label' => 'VAT',
                                        'amount' => $currentPeriod['electricity']['totals']['vat_amount'] ?? 0
                                    ];
                                }
                            @endphp
                            
                            @foreach($pastElectricityCharges as $index => $charge)
                                <div class="billing-row" data-component="past-electricity-charge" data-component-id="past-electricity-charge-{{ $index + 1 }}">
                                    <span class="billing-label">{{ $charge['label'] }}</span>
                                    <span class="billing-amount">R {{ number_format($charge['amount'], 2) }}</span>
                                </div>
                            @endforeach

                            <!-- Period Total -->
                            <div class="projected-charges" data-component="past-electricity-projected-charges" data-component-id="past-electricity-projected-charges-1">
                                <span class="projected-charges-label" data-component="past-electricity-projected-label" data-component-id="past-electricity-projected-label-1">
                                    <span class="projected-arrow" data-component="past-electricity-projected-arrow" data-component-id="past-electricity-projected-arrow-1">→</span>
                                    Period Total
                                </span>
                                <span class="billing-amount" data-component="past-electricity-projected-amount" data-component-id="past-electricity-projected-amount-1">R {{ number_format($currentPeriod['electricity']['totals']['period_total'] ?? 0, 2) }}</span>
                            </div>
                        </div>
                    </div>
                @endif
            @endif
        </div>

        <!-- WATER SECTION -->
        @if(($dashboardData['tariff']['is_water'] ?? false) && ($dashboardData['water']['enabled'] ?? false) && $currentPeriodIndex === 0)
            <div class="meter-section" data-component="water-meter-section" data-component-id="water-meter-section-1">
                <!-- Water Type Header -->
                <div class="meter-type-header" data-component="water-type-header" data-component-id="water-type-header-1">
                    <i class="fas fa-tint meter-icon" style="font-size: 24px;"></i>
                    <span class="meter-type-name">Water</span>
                </div>
                
                <!-- Water Meter Header Bar with Stats -->
                <div class="meter-header water" data-component="water-meter-header" data-component-id="water-meter-header-1">
                    <div class="meter-icon" data-component="meter-icon" data-component-id="water-meter-icon-1">
                        <i class="fas fa-tint" style="font-size: 24px;"></i>
                    </div>
                    <div class="meter-stats" data-component="meter-stats" data-component-id="water-meter-stats-1">
                        <div class="stat-item" data-component="stat-item" data-component-id="water-stat-daily-1">
                            <div class="stat-label">Daily Usage</div>
                            <div class="stat-value">{{ $dashboardData['water']['daily_average_formatted'] ?? '0 L' }}</div>
                        </div>
                        <div class="stat-item" data-component="stat-item" data-component-id="water-stat-total-1">
                            <div class="stat-label">Total Usage</div>
                            <div class="stat-value">{{ number_format($dashboardData['water']['consumption_kl'] ?? 0, 2) }} kl</div>
                        </div>
                        <div class="stat-item" data-component="stat-item" data-component-id="water-stat-cost-1">
                            <div class="stat-label">Daily Cost</div>
                            <div class="stat-value">R {{ number_format(($dashboardData['water']['charges']['total'] ?? 0) / max(1, (isset($dashboardData['period']) && $dashboardData['period'] ? ($dashboardData['period']['days_in_period'] ?? 30) : 30)), 2) }}</div>
                        </div>
                    </div>
                </div>

                <!-- Water Billing Summary -->
                <div class="billing-summary" data-component="water-billing-summary" data-component-id="water-billing-summary-1">
                    <!-- Warning if water tariff not configured -->
                    @if(!empty($dashboardData['water']['warning']))
                        <div class="alert alert-warning" style="margin-bottom: 10px; padding: 10px; border-radius: 4px; background: #fff3cd; border: 1px solid #ffc107; color: #856404;">
                            <i class="fas fa-exclamation-triangle" style="margin-right: 5px;"></i>
                            {{ $dashboardData['water']['warning'] }}
                        </div>
                    @endif
                    
                    <!-- Show/Hide Details Toggle -->
                    <div class="details-toggle" onclick="toggleWaterDetails()" data-component="details-toggle" data-component-id="water-details-toggle-1">
                        <span id="waterDetailsToggleText">Show Details</span>
                        <i class="fas fa-chevron-down" id="waterDetailsIcon"></i>
                    </div>

                    <!-- Closing Reading -->
                    <div id="waterDetails" style="display: none;" data-component="water-details" data-component-id="water-details-1">
                        @if(isset($dashboardData['water']['closing_reading']))
                            <div class="closing-reading-row" data-component="closing-reading" data-component-id="water-closing-reading-1">
                                Closing Reading @php
                                    $value = $dashboardData['water']['closing_reading']['value'] ?? 0;
                                    $numValue = round((float)$value);
                                    $redDigits = str_pad((string)$numValue, 5, '0', STR_PAD_LEFT);
                                    echo $redDigits . ' - 00';
                                @endphp
                            </div>
                        @endif

                        <!-- Detailed Breakdown - Grouped by type like Vue -->
                        @if(isset($dashboardData['water']['charges']['breakdown']))
                            <div class="breakdown-details" data-component="breakdown-details" data-component-id="water-breakdown-details-1">
                                @php
                                    $breakdown = $dashboardData['water']['charges']['breakdown'] ?? [];
                                    // Map 'tier' type to 'water_in' for consistency with Vue
                                    $breakdown = array_map(function($item) {
                                        if (($item['type'] ?? '') === 'tier') {
                                            $item['type'] = 'water_in';
                                        }
                                        return $item;
                                    }, $breakdown);
                                    
                                    // Filter and group by type
                                    $waterIn = array_filter($breakdown, fn($b) => ($b['type'] ?? '') === 'water_in');
                                    $waterOut = array_filter($breakdown, fn($b) => ($b['type'] ?? '') === 'water_out');
                                    $additional = array_filter($breakdown, fn($b) => ($b['type'] ?? '') === 'additional');
                                    $fixed = array_filter($breakdown, fn($b) => ($b['type'] ?? '') === 'fixed');
                                    $customer = array_filter($breakdown, fn($b) => ($b['type'] ?? '') === 'customer');
                                @endphp
                                
                                <!-- Water In Tiers (individual tier breakdown) -->
                                @foreach($waterIn as $index => $item)
                                    @php
                                        // Parse tier label to extract tier range
                                        $label = $item['label'] ?? 'Tier ' . ($item['tier'] ?? '');
                                        $tierRange = '';
                                        
                                        // Extract tier range from label (e.g., "Tier 1 (0 to 6000 L)" -> "Tier 1 ( 0 to 6000 )")
                                        // Also handles "Tier 1 (0-6000 L)" format
                                        if (preg_match('/Tier\s+(\d+)\s*\(([^)]+)\)/', $label, $matches)) {
                                            $tierNum = $matches[1];
                                            $range = trim($matches[2]);
                                            // Remove "L" or "kWh" and clean up
                                            $range = preg_replace('/\s*(L|kWh)\s*/i', '', $range);
                                            // Normalize: replace "-" with " to " and ensure proper spacing
                                            $range = preg_replace('/\s*-\s*/', ' to ', $range);
                                            // Ensure spaces around "to"
                                            $range = preg_replace('/\s+to\s+/', ' to ', $range);
                                            $tierRange = "Tier {$tierNum} ( {$range} )";
                                        }
                                    @endphp
                                    <div class="billing-row detail-row" data-component="tier-breakdown" data-component-id="tier-breakdown-{{ $index + 1 }}">
                                        <div class="tier-label-container">
                                            <span class="tier-label-main">Consumption</span>
                                            @if($tierRange)
                                                <span class="tier-label-sub">{{ $tierRange }}</span>
                                            @endif
                                        </div>
                                        <span class="billing-amount" style="text-align: right;">R{{ number_format($item['charge'] ?? 0, 2) }}</span>
                                    </div>
                                @endforeach
                                
                                <!-- Water Out (Sewerage) -->
                                @foreach($waterOut as $index => $item)
                                    <div class="billing-row detail-row" data-component="water-out-charge" data-component-id="water-out-charge-{{ $index + 1 }}">
                                        <span class="billing-label">{{ $item['label'] ?? 'Sewerage' }}</span>
                                        <span class="billing-amount">R {{ number_format($item['charge'] ?? 0, 2) }}</span>
                                    </div>
                                @endforeach
                                
                                <!-- Additional Charges -->
                                @foreach($additional as $index => $item)
                                    <div class="billing-row detail-row" data-component="additional-charges" data-component-id="additional-charges-{{ $index + 1 }}">
                                        <span class="billing-label">{{ $item['label'] ?? 'Additional Charge' }}</span>
                                        <span class="billing-amount">R {{ number_format($item['charge'] ?? 0, 2) }}</span>
                                    </div>
                                @endforeach
                                
                                <!-- Fixed Costs -->
                                @foreach($fixed as $index => $item)
                                    <div class="billing-row detail-row" data-component="fixed-costs" data-component-id="fixed-costs-{{ $index + 1 }}">
                                        <span class="billing-label">{{ $item['label'] ?? 'Fixed Cost' }}</span>
                                        <span class="billing-amount">R {{ number_format($item['charge'] ?? 0, 2) }}</span>
                                    </div>
                                @endforeach
                                
                                <!-- Customer Costs -->
                                @foreach($customer as $index => $item)
                                    <div class="billing-row detail-row" data-component="customer-costs" data-component-id="customer-costs-{{ $index + 1 }}">
                                        <span class="billing-label">{{ $item['label'] ?? 'Customer Cost' }}</span>
                                        <span class="billing-amount">R {{ number_format($item['charge'] ?? 0, 2) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Water Charges Breakdown -->
                    @php
                        $waterCharges = [];
                        // Consumption Charges (tiered charges)
                        if (($dashboardData['water']['charges']['tier_breakdown'] ?? []) || ($dashboardData['water']['charges']['total'] ?? 0) > 0) {
                            $waterCharges[] = [
                                'label' => 'Consumption Charges',
                                'amount' => $dashboardData['water']['charges']['total'] ?? 0
                            ];
                        }
                        // Discharge charges (water out)
                        $waterOutTotal = 0;
                        if (isset($dashboardData['water']['charges']['breakdown'])) {
                            foreach ($dashboardData['water']['charges']['breakdown'] as $item) {
                                if (($item['type'] ?? '') === 'water_out') {
                                    $waterOutTotal += $item['charge'] ?? 0;
                                }
                            }
                        }
                        if ($waterOutTotal > 0) {
                            $waterCharges[] = [
                                'label' => 'Discharge charges',
                                'amount' => $waterOutTotal
                            ];
                        }
                        // Infrastructure Surcharge (fixed costs)
                        $infrastructureTotal = 0;
                        if (isset($dashboardData['water']['charges']['fixed_costs_breakdown'])) {
                            foreach ($dashboardData['water']['charges']['fixed_costs_breakdown'] as $cost) {
                                $infrastructureTotal += $cost['amount'] ?? 0;
                            }
                        }
                        if ($infrastructureTotal > 0) {
                            $waterCharges[] = [
                                'label' => 'Infrastructure Surcharge',
                                'amount' => $infrastructureTotal
                            ];
                        }
                    @endphp
                    
                    @foreach($waterCharges as $index => $charge)
                        <div class="billing-row" data-component="water-charge" data-component-id="water-charge-{{ $index + 1 }}">
                            <span class="billing-label">{{ $charge['label'] }}</span>
                            <span class="billing-amount">R{{ number_format($charge['amount'], 2) }}</span>
                        </div>
                    @endforeach

                    <!-- Projected Charges -->
                    <div class="projected-charges" data-component="projected-charges" data-component-id="water-projected-charges-1">
                        <span class="projected-charges-label" data-component="projected-label" data-component-id="water-projected-label-1">
                            <span class="projected-arrow" data-component="projected-arrow" data-component-id="water-projected-arrow-1">→</span>
                            Projected charges
                        </span>
                        <span class="billing-amount" data-component="projected-amount" data-component-id="water-projected-amount-1" style="text-align: right;">R{{ number_format($dashboardData['water']['totals']['period_total'] ?? 0, 2) }}</span>
                    </div>
                </div>
            </div>
        @endif

        <!-- ELECTRICITY SECTION -->
        @php
            $electricityEnabled = $dashboardData['electricity']['enabled'] ?? false;
            $electricityMeter = $dashboardData['electricity']['meter'] ?? null;
            $hasElectricityMeter = isset($dashboardData['electricity']['meter']) && $dashboardData['electricity']['meter'];
            $hasElectricityReadings = !empty($dashboardData['electricity']['readings'] ?? []);
            // Show electricity section if: enabled, has meter object, OR has readings (safety check)
            $shouldShowElectricity = ($electricityEnabled || $hasElectricityMeter || $hasElectricityReadings) && $currentPeriodIndex === 0;
        @endphp
        @if($shouldShowElectricity)
            <div class="meter-section" data-component="electricity-meter-section" data-component-id="electricity-meter-section-1">
                <!-- Electricity Type Header -->
                <div class="meter-type-header electricity" data-component="electricity-type-header" data-component-id="electricity-type-header-1">
                    <i class="fas fa-bolt meter-icon" style="font-size: 24px;"></i>
                    <span class="meter-type-name">Electricity</span>
                </div>
                
                <!-- Electricity Meter Header Bar with Stats -->
                <div class="meter-header electricity" data-component="electricity-meter-header" data-component-id="electricity-meter-header-1">
                    <div class="meter-icon" data-component="meter-icon" data-component-id="electricity-meter-icon-1">
                        <i class="fas fa-bolt" style="font-size: 24px;"></i>
                    </div>
                    <div class="meter-stats" data-component="meter-stats" data-component-id="electricity-meter-stats-1">
                        <div class="stat-item" data-component="stat-item" data-component-id="electricity-stat-daily-1">
                            <div class="stat-label">Daily Usage</div>
                            <div class="stat-value">{{ $dashboardData['electricity']['daily_average_formatted'] ?? '0 kWh' }}</div>
                        </div>
                        <div class="stat-item" data-component="stat-item" data-component-id="electricity-stat-cost-1">
                            <div class="stat-label">Daily Cost</div>
                            <div class="stat-value">R {{ number_format(($dashboardData['electricity']['charges']['total'] ?? 0) / max(1, (isset($dashboardData['period']) && $dashboardData['period'] ? ($dashboardData['period']['days_in_period'] ?? 30) : 30)), 2) }}</div>
                        </div>
                    </div>
                </div>

                <!-- Electricity Billing Summary -->
                <div class="billing-summary" data-component="electricity-billing-summary" data-component-id="electricity-billing-summary-1">
                    <!-- Warning if electricity tariff not configured -->
                    @if(!empty($dashboardData['electricity']['warning']))
                        <div class="alert alert-warning" style="margin-bottom: 10px; padding: 10px; border-radius: 4px; background: #fff3cd; border: 1px solid #ffc107; color: #856404;">
                            <i class="fas fa-exclamation-triangle" style="margin-right: 5px;"></i>
                            {{ $dashboardData['electricity']['warning'] }}
                        </div>
                    @endif
                    
                    <!-- Consumption Charges -->
                    <div class="billing-row" data-component="consumption-charges" data-component-id="consumption-charges-electricity">
                        <span class="billing-label">Consumption Charges</span>
                        <span class="billing-amount">R{{ number_format($dashboardData['electricity']['totals']['consumption_total'] ?? 0, 2) }}</span>
                    </div>

                    <!-- VAT (only if > 0) -->
                    @if(($dashboardData['electricity']['totals']['vat_amount'] ?? 0) > 0)
                        <div class="billing-row" data-component="vat-amount" data-component-id="vat-amount-electricity">
                            <span class="billing-label">VAT</span>
                            <span class="billing-amount">R {{ number_format($dashboardData['electricity']['totals']['vat_amount'] ?? 0, 2) }}</span>
                        </div>
                    @endif

                    <!-- Rates (if applicable) -->
                    @if(($dashboardData['electricity']['charges']['fixed_costs_breakdown'] ?? []) || ($dashboardData['electricity']['charges']['account_costs_breakdown'] ?? []))
                        @php
                            $ratesTotal = 0;
                            $fixedCosts = $dashboardData['electricity']['charges']['fixed_costs_breakdown'] ?? [];
                            $accountCosts = $dashboardData['electricity']['charges']['account_costs_breakdown'] ?? [];
                            foreach ($fixedCosts as $cost) {
                                $ratesTotal += $cost['amount'] ?? 0;
                            }
                            foreach ($accountCosts as $cost) {
                                $ratesTotal += $cost['amount'] ?? 0;
                            }
                        @endphp
                        @if($ratesTotal > 0)
                            <div class="billing-row" data-component="rates" data-component-id="rates-electricity">
                                <span class="billing-label">Rates</span>
                                <span class="billing-amount">R {{ number_format($ratesTotal, 2) }}</span>
                            </div>
                        @endif
                    @endif

                    <!-- Projected Charges -->
                    <div class="projected-charges" data-component="projected-charges" data-component-id="electricity-projected-charges-1">
                        <span class="projected-charges-label" data-component="projected-label" data-component-id="electricity-projected-label-1">
                            <span class="projected-arrow" data-component="projected-arrow" data-component-id="electricity-projected-arrow-1">→</span>
                            Projected charges
                        </span>
                        <span class="billing-amount" data-component="projected-amount" data-component-id="electricity-projected-amount-1" style="text-align: right;">R{{ number_format($dashboardData['electricity']['totals']['period_total'] ?? 0, 2) }}</span>
                    </div>
                </div>
            </div>
        @endif
    @else
        <div class="alert alert-danger" data-component="error-alert" data-component-id="dashboard-error-1">
            <i class="fas fa-exclamation-circle mr-2"></i>Failed to load dashboard data
        </div>
    @endif

    <!-- Persistent Error Banner with Show/Hide Toggle -->
    @if(isset($errors) && !empty($errors))
        <!-- Collapsed Error Indicator (shown only when user manually closes) -->
        <div id="error-banner-collapsed" class="error-banner-collapsed" style="position: fixed; top: 0; left: 0; right: 0; background: #dc3545; color: white; padding: 8px 16px; z-index: 10000; box-shadow: 0 2px 10px rgba(0,0,0,0.3); cursor: pointer; display: none;">
            <div style="max-width: 600px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; gap: 16px;">
                <div style="display: flex; align-items: center; gap: 8px; flex: 1;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span style="font-weight: 600;">Configuration Error</span>
                    <span style="font-size: 12px; opacity: 0.9;">(Click to view details)</span>
                </div>
                <button onclick="toggleErrorBanner()" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 16px; line-height: 1; flex-shrink: 0;" title="Show Error Details">
                    <i class="fas fa-chevron-down" id="error-toggle-icon"></i>
                </button>
            </div>
        </div>

        <!-- Expanded Error Banner (full details - shown by default) -->
        <div id="error-banner-expanded" class="error-banner-expanded" style="position: fixed; top: 0; left: 0; right: 0; background: #dc3545; color: white; padding: 16px; z-index: 10000; box-shadow: 0 2px 10px rgba(0,0,0,0.3); display: block;">
            <div style="max-width: 600px; margin: 0 auto;">
                <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px;">
                    <div style="flex: 1;">
                        <div style="font-weight: bold; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; font-size: 16px;">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Configuration Error</span>
                        </div>
                        
                        @foreach($errors as $error)
                            <div style="background: rgba(0,0,0,0.15); border-radius: 6px; padding: 12px; margin-bottom: 10px;">
                                <div style="font-weight: 600; font-size: 14px; margin-bottom: 6px;">
                                    <i class="fas fa-times-circle" style="margin-right: 6px;"></i>
                                    {{ $error['user_message'] ?? $error['message'] ?? 'Error' }}
                                </div>
                                
                                {{-- Always show details - this is what's missing --}}
                                @if(isset($error['details']))
                                    <div style="font-size: 13px; opacity: 0.95; margin-top: 6px; padding: 8px; background: rgba(0,0,0,0.1); border-radius: 4px; font-family: monospace;">
                                        <i class="fas fa-info-circle" style="margin-right: 6px;"></i>
                                        {{ $error['details'] }}
                                    </div>
                                @endif
                                
                                {{-- Parse and show specific missing fields --}}
                                @php
                                    $details = $error['details'] ?? '';
                                    $missingFields = [];
                                    
                                    if (stripos($details, 'bill_day') !== false) {
                                        $missingFields[] = ['field' => 'Bill Day', 'description' => 'The day of the month when bills are generated', 'location' => 'Account Settings or Tariff Template'];
                                    }
                                    if (stripos($details, 'read_day') !== false) {
                                        $missingFields[] = ['field' => 'Read Day', 'description' => 'The day of the month when meter readings are due', 'location' => 'Account Settings or Tariff Template'];
                                    }
                                    if (stripos($details, 'billing_day') !== false) {
                                        $missingFields[] = ['field' => 'Billing Day', 'description' => 'The billing cycle day in the tariff template', 'location' => 'Tariff Template'];
                                    }
                                    if ((stripos($details, 'No tariff template') !== false) || 
                                        (stripos($details, 'tariff template') !== false && stripos($details, 'assigned') !== false)) {
                                        $missingFields[] = ['field' => 'Tariff Template', 'description' => 'No tariff template assigned to this account', 'location' => 'Account Settings'];
                                    }
                                    if (stripos($details, 'electricity.breakdown') !== false || stripos($details, 'incomplete bill data') !== false) {
                                        $missingFields[] = ['field' => 'Electricity Configuration', 'description' => 'Electricity billing data is incomplete - check if electricity is enabled in tariff', 'location' => 'Tariff Template'];
                                    }
                                    if (stripos($details, 'meter') !== false && stripos($details, 'missing') !== false) {
                                        $missingFields[] = ['field' => 'Meter', 'description' => 'No meter assigned to this account', 'location' => 'Account Settings'];
                                    }
                                @endphp
                                
                                @if(count($missingFields) > 0)
                                    <div style="margin-top: 10px; font-size: 12px;">
                                        <div style="font-weight: 600; margin-bottom: 6px;">
                                            <i class="fas fa-clipboard-list" style="margin-right: 4px;"></i>
                                            Missing Configuration:
                                        </div>
                                        <table style="width: 100%; border-collapse: collapse; background: rgba(0,0,0,0.1); border-radius: 4px; overflow: hidden;">
                                            <thead>
                                                <tr style="background: rgba(0,0,0,0.15);">
                                                    <th style="padding: 6px 8px; text-align: left; font-weight: 600;">Field</th>
                                                    <th style="padding: 6px 8px; text-align: left; font-weight: 600;">Description</th>
                                                    <th style="padding: 6px 8px; text-align: left; font-weight: 600;">Where to Fix</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($missingFields as $field)
                                                    <tr style="border-top: 1px solid rgba(255,255,255,0.1);">
                                                        <td style="padding: 6px 8px; font-weight: 600;">{{ $field['field'] }}</td>
                                                        <td style="padding: 6px 8px;">{{ $field['description'] }}</td>
                                                        <td style="padding: 6px 8px;">{{ $field['location'] }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                        
                        <div style="font-size: 13px; margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.3); display: flex; gap: 16px; flex-wrap: wrap;">
                            <a href="{{ route('dashboard-errors.show', ['accountId' => $account->id]) }}" style="color: white; text-decoration: none; font-weight: bold; background: rgba(255,255,255,0.2); padding: 6px 12px; border-radius: 4px; display: inline-flex; align-items: center; gap: 6px;">
                                <i class="fas fa-info-circle"></i>View Full Details
                            </a>
                            <a href="{{ route('user-accounts.manager') }}" style="color: white; text-decoration: none; font-weight: bold; background: rgba(255,255,255,0.2); padding: 6px 12px; border-radius: 4px; display: inline-flex; align-items: center; gap: 6px;">
                                <i class="fas fa-wrench"></i>Fix in Account Manager
                            </a>
                            <a href="{{ route('tariff-template') }}" style="color: white; text-decoration: none; font-weight: bold; background: rgba(255,255,255,0.2); padding: 6px 12px; border-radius: 4px; display: inline-flex; align-items: center; gap: 6px;">
                                <i class="fas fa-file-invoice-dollar"></i>Edit Tariff Templates
                            </a>
                        </div>
                    </div>
                    <button onclick="toggleErrorBanner()" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-size: 18px; line-height: 1; flex-shrink: 0; display: flex; align-items: center; gap: 6px;" title="Hide Error Details">
                        <i class="fas fa-chevron-up" id="error-expand-icon"></i>
                        <span style="font-size: 12px;">Hide</span>
                    </button>
                </div>
            </div>
        </div>

        <style>
            .error-banner-collapsed,
            .error-banner-expanded {
                animation: slideDown 0.3s ease-out;
            }
            @keyframes slideDown {
                from {
                    transform: translateY(-100%);
                    opacity: 0;
                }
                to {
                    transform: translateY(0);
                    opacity: 1;
                }
            }
            .error-banner-collapsed:hover {
                background: #c82333 !important;
            }
            /* Dynamic body padding based on banner state */
            body.error-expanded {
                padding-top: 280px !important;
            }
            body.error-collapsed {
                padding-top: 50px !important;
            }
        </style>
        <script>
            // Check localStorage for saved state - DEFAULT IS EXPANDED (open)
            const errorBannerState = localStorage.getItem('errorBannerState_{{ $account->id }}');
            // Only collapse if user explicitly closed it (saved as 'collapsed')
            const isCollapsed = errorBannerState === 'collapsed';

            function toggleErrorBanner() {
                const collapsed = document.getElementById('error-banner-collapsed');
                const expanded = document.getElementById('error-banner-expanded');
                const toggleIcon = document.getElementById('error-toggle-icon');
                const expandIcon = document.getElementById('error-expand-icon');
                
                if (expanded.style.display === 'none' || expanded.style.display === '') {
                    // Show expanded, hide collapsed
                    collapsed.style.display = 'none';
                    expanded.style.display = 'block';
                    document.body.classList.remove('error-collapsed');
                    document.body.classList.add('error-expanded');
                    localStorage.setItem('errorBannerState_{{ $account->id }}', 'expanded');
                    if (toggleIcon) toggleIcon.classList.replace('fa-chevron-down', 'fa-chevron-up');
                } else {
                    // Show collapsed, hide expanded
                    collapsed.style.display = 'block';
                    expanded.style.display = 'none';
                    document.body.classList.remove('error-expanded');
                    document.body.classList.add('error-collapsed');
                    localStorage.setItem('errorBannerState_{{ $account->id }}', 'collapsed');
                    if (expandIcon) expandIcon.classList.replace('fa-chevron-up', 'fa-chevron-down');
                }
            }

            // Initialize on page load - DEFAULT IS EXPANDED (open)
            document.addEventListener('DOMContentLoaded', function() {
                const collapsed = document.getElementById('error-banner-collapsed');
                const expanded = document.getElementById('error-banner-expanded');
                
                if (isCollapsed) {
                    // User previously closed it - keep it collapsed
                    collapsed.style.display = 'block';
                    expanded.style.display = 'none';
                    document.body.classList.add('error-collapsed');
                } else {
                    // Default: show expanded (open)
                    collapsed.style.display = 'none';
                    expanded.style.display = 'block';
                    document.body.classList.add('error-expanded');
                }
            });

            // Also allow clicking on collapsed banner to expand
            document.getElementById('error-banner-collapsed').addEventListener('click', function(e) {
                if (e.target.tagName !== 'BUTTON' && !e.target.closest('button')) {
                    toggleErrorBanner();
                }
            });
        </script>
    @endif

    <!-- Fixed Bottom Navigation -->
    <div class="fixed-nav-tabs" data-component="bottom-navigation" data-component-id="bottom-navigation-1" style="position: fixed; bottom: 0; left: 0; right: 0; display: flex; justify-content: space-around; background: #fff; border-top: 1px solid #e0e0e0; padding: 8px 0; box-shadow: 0 -2px 10px rgba(0,0,0,0.1); z-index: 100;">
        <a href="{{ route('user-accounts.dashboard', ['accountId' => $account->id]) }}" class="nav-tab" data-component="nav-tab" data-component-id="dashboard-nav-home-1" style="display: flex; flex-direction: column; align-items: center; gap: 4px; background: none; border: none; color: #666; font-size: 11px; padding: 6px 12px; cursor: pointer; text-decoration: none;">
            <i class="fas fa-home" style="font-size: 22px;"></i>
            <span>Home</span>
        </a>
        <a href="{{ route('user-accounts.dashboard', ['accountId' => $account->id]) }}" class="nav-tab active" data-component="nav-tab" data-component-id="dashboard-nav-dashboard-1" style="display: flex; flex-direction: column; align-items: center; gap: 4px; background: none; border: none; color: #3294B8; font-size: 11px; padding: 6px 12px; cursor: pointer; text-decoration: none;">
            <i class="fas fa-chart-line" style="font-size: 22px;"></i>
            <span>Dashboard</span>
        </a>
        <a href="{{ route('user-accounts.readings', ['accountId' => $account->id]) }}" class="nav-tab" data-component="nav-tab" data-component-id="dashboard-nav-readings-1" style="display: flex; flex-direction: column; align-items: center; gap: 4px; background: none; border: none; color: #666; font-size: 11px; padding: 6px 12px; cursor: pointer; text-decoration: none;">
            <i class="fas fa-edit" style="font-size: 22px;"></i>
            <span>Readings</span>
        </a>
        <a href="{{ route('user-accounts.accounts', ['accountId' => $account->id]) }}" class="nav-tab" data-component="nav-tab" data-component-id="dashboard-nav-accounts-1" style="display: flex; flex-direction: column; align-items: center; gap: 4px; background: none; border: none; color: #666; font-size: 11px; padding: 6px 12px; cursor: pointer; text-decoration: none;">
            <i class="fas fa-user-circle" style="font-size: 22px;"></i>
            <span>Accounts</span>
        </a>
    </div>
</div>

<!-- Component Debug Script -->
<script src="{{ asset('js/component-debug.js') }}"></script>

<script>
function toggleWaterDetails() {
    const details = document.getElementById('waterDetails');
    const toggleText = document.getElementById('waterDetailsToggleText');
    const icon = document.getElementById('waterDetailsIcon');
    
    if (details.style.display === 'none') {
        details.style.display = 'block';
        toggleText.textContent = 'Hide Details';
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
    } else {
        details.style.display = 'none';
        toggleText.textContent = 'Show Details';
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
    }
}

function formatMeterReading(value) {
    if (!value) return '00000 - 00';
    const numValue = Math.round(parseFloat(value));
    const redDigits = String(numValue).padStart(5, '0');
    return redDigits + ' - 00';
}
</script>
@endsection




