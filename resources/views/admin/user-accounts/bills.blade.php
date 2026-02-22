@extends('admin.layouts.main')
@section('title', 'Billing History - ' . ($account->account_name ?? 'Unknown Account'))

@section('content')
<style>
    /* Mobile-first constraints - max width matches mobile viewport (414px = iPhone Pro Max) */
    .bills-container {
        max-width: 414px;
        width: 100%;
        margin: 0 auto;
        padding: 16px;
        background: #f8f9fa;
        min-height: 100vh;
        box-sizing: border-box;
    }
    
    @media (max-width: 414px) {
        .bills-container {
            max-width: 100%;
            padding: 12px;
        }
    }
    
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
    
    .page-label {
        font-size: 14px;
        color: #000;
    }
    
    .header-divider {
        height: 1px;
        background: #e0e0e0;
        margin: 12px 0;
    }
    
    .page-title {
        font-size: 18px;
        font-weight: 700;
        color: #000;
        text-align: center;
        padding: 12px 0;
    }
    
    .period-nav {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 16px;
        padding: 12px 0;
        margin-bottom: 16px;
    }
    
    .nav-arrow {
        background: none;
        border: none;
        font-size: 24px;
        color: #000;
        cursor: pointer;
        padding: 8px;
        transition: opacity 0.2s;
    }
    
    .nav-arrow:hover:not(:disabled) {
        opacity: 0.7;
    }
    
    .nav-arrow:disabled {
        opacity: 0.3;
        cursor: not-allowed;
    }
    
    .period-text {
        font-size: 14px;
        font-weight: 600;
        color: #000;
        text-align: center;
        flex: 1;
    }
    
    .bill-card {
        background: white;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .bill-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;
        padding-bottom: 12px;
        border-bottom: 1px solid #e0e0e0;
    }
    
    .bill-period {
        flex: 1;
    }
    
    .period-number {
        font-size: 12px;
        color: #6b7280;
        margin-bottom: 4px;
    }
    
    .period-dates {
        font-size: 14px;
        font-weight: 600;
        color: #000;
        margin-bottom: 4px;
    }
    
    .period-status {
        font-size: 12px;
        padding: 2px 8px;
        border-radius: 4px;
        font-weight: 600;
        display: inline-block;
    }
    
    .status-provisional {
        background: #fff3cd;
        color: #856404;
    }
    
    .status-calculated {
        background: #d1ecf1;
        color: #0c5460;
    }
    
    .status-actual {
        background: #d4edda;
        color: #155724;
    }
    
    .bill-details {
        margin-bottom: 12px;
    }
    
    .detail-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        font-size: 14px;
        border-bottom: 1px solid #f5f5f5;
    }
    
    .detail-row:last-child {
        border-bottom: none;
    }
    
    .detail-label {
        color: #6b7280;
    }
    
    .detail-value {
        font-weight: 600;
        color: #000;
    }
    
    .total-row {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 2px solid #3294B8;
    }
    
    .total-label {
        font-size: 16px;
        font-weight: 700;
        color: #000;
    }
    
    .total-amount {
        font-size: 20px;
        font-weight: 700;
        color: #3294B8;
    }
    
    .btn-secondary {
        width: 100%;
        padding: 12px;
        background: #e0e0e0;
        color: #000;
        border: none;
        border-radius: 6px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: block;
        text-align: center;
        margin-bottom: 12px;
    }
    
    .empty-state {
        background: white;
        border-radius: 8px;
        padding: 32px 16px;
        text-align: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 16px;
    }
    
    .empty-state-icon {
        font-size: 48px;
        margin-bottom: 12px;
        opacity: 0.5;
    }
    
    .empty-state-text {
        font-size: 14px;
        color: #6b7280;
    }
    
    .alert {
        padding: 12px;
        border-radius: 6px;
        margin-bottom: 16px;
        font-size: 14px;
    }
    
    .alert-danger {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
</style>

<div class="bills-container">
    <!-- Unified Header -->
    <div class="unified-header">
        <div class="header-top">
            <div>
                <div class="logo-text">
                    <span class="logo-my">My</span><span class="logo-cities">Cities</span>
                </div>
                <div class="user-label">{{ $account->site->user->name ?? 'Unknown User' }}</div>
            </div>
            <div class="page-label">Billing History</div>
        </div>
        <div class="header-divider"></div>
        <div class="page-title">{{ $account->account_name ?? 'Unknown Account' }}</div>
    </div>

    <!-- Alert Messages -->
    @if(session('alert-class') && session('alert-message'))
        <div class="alert {{ session('alert-class') }}">
            {{ session('alert-message') }}
        </div>
    @endif

    <!-- Period Navigation (if applicable) -->
    @if(isset($accountData['bills']) && count($accountData['bills']) > 1)
        <div class="period-nav">
            <button class="nav-arrow" onclick="navigatePeriod(-1)">←</button>
            <div class="period-text" id="periodIndicator">Period Navigation</div>
            <button class="nav-arrow" onclick="navigatePeriod(1)">→</button>
        </div>
    @endif

    <!-- Billing Mode Info -->
    @if(isset($isDateToDate))
        <div class="info-section" style="background: white; border-radius: 8px; padding: 16px; margin-bottom: 16px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">Billing Mode</div>
            <div style="font-size: 14px; font-weight: 600; color: #000;">
                {{ $isDateToDate ? 'Date to Date (30-day rolling periods)' : 'Period to Period (Monthly)' }}
            </div>
        </div>
    @endif

    <!-- Bills List -->
    @if(isset($accountData['bills']) && count($accountData['bills']) > 0)
        @foreach($accountData['bills'] as $bill)
            <div class="bill-card">
                <div class="bill-header">
                    <div class="bill-period">
                        @if(isset($bill['period_number']))
                            <div class="period-number">Period {{ $bill['period_number'] }}</div>
                        @endif
                        <div class="period-dates">
                            @if(isset($bill['start_date']))
                                {{ \Carbon\Carbon::parse($bill['start_date'])->format('d M Y') }}
                                @if(isset($bill['end_date']) && $bill['end_date'])
                                    - {{ \Carbon\Carbon::parse($bill['end_date'])->format('d M Y') }}
                                @else
                                    - <span style="color: #6b7280;">OPEN</span>
                                @endif
                            @elseif(isset($bill['period_start_date']) && isset($bill['period_end_date']))
                                {{ \Carbon\Carbon::parse($bill['period_start_date'])->format('d M Y') }}
                                -
                                {{ \Carbon\Carbon::parse($bill['period_end_date'])->format('d M Y') }}
                            @endif
                        </div>
                    </div>
                    @if(isset($bill['usage_status']) || isset($bill['status']))
                        @php
                            $status = $bill['usage_status'] ?? $bill['status'] ?? 'PROVISIONAL';
                            $statusClass = strtolower($status);
                        @endphp
                        <span class="period-status status-{{ $statusClass }}">
                            {{ $status }}
                        </span>
                    @endif
                </div>
                
                <div class="bill-details">
                    @if(isset($bill['consumption']))
                        <div class="detail-row">
                            <span class="detail-label">Consumption</span>
                            <span class="detail-value">{{ number_format($bill['consumption'], 2) }} L</span>
                        </div>
                    @endif
                    
                    @if(isset($bill['usage_charge']) || isset($bill['tiered_charge']))
                        <div class="detail-row">
                            <span class="detail-label">Usage Charge</span>
                            <span class="detail-value">R {{ number_format($bill['usage_charge'] ?? $bill['tiered_charge'] ?? 0, 2) }}</span>
                        </div>
                    @endif
                    
                    @if(isset($bill['fixed_costs_total']) && $bill['fixed_costs_total'] > 0)
                        <div class="detail-row">
                            <span class="detail-label">Fixed Costs</span>
                            <span class="detail-value">R {{ number_format($bill['fixed_costs_total'], 2) }}</span>
                        </div>
                    @endif
                    
                    @if(isset($bill['vat_amount']) && $bill['vat_amount'] > 0)
                        <div class="detail-row">
                            <span class="detail-label">VAT ({{ $bill['vat_rate'] ?? 15 }}%)</span>
                            <span class="detail-value">R {{ number_format($bill['vat_amount'], 2) }}</span>
                        </div>
                    @endif
                    
                    <div class="detail-row total-row">
                        <span class="total-label">Total</span>
                        <span class="total-amount">R {{ number_format($bill['total_amount'] ?? $bill['grand_total'] ?? 0, 2) }}</span>
                    </div>
                </div>
                
                @if(isset($bill['reconciliation']) && !empty($bill['reconciliation']))
                    <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e0e0e0;">
                        <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">Reconciliation</div>
                        <div style="font-size: 14px; color: #0c5460;">
                            Adjusted from: R {{ number_format($bill['reconciliation']['original'] ?? 0, 2) }}
                            to: R {{ number_format($bill['reconciliation']['calculated'] ?? 0, 2) }}
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    @else
        <div class="empty-state">
            <div class="empty-state-icon">📄</div>
            <div class="empty-state-text">No billing history available yet</div>
            <div style="font-size: 12px; color: #999; margin-top: 8px;">
                Add readings to generate bills
            </div>
        </div>
    @endif

    <!-- Navigation Buttons -->
    <a href="{{ route('user-accounts.dashboard', $account->id) }}" class="btn-secondary">
        Back to Dashboard
    </a>
    <a href="{{ route('user-accounts.readings.add', $account->id) }}" class="btn-secondary">
        Add Reading
    </a>
</div>

<script>
let currentPeriodIndex = 0;

function navigatePeriod(direction) {
    // Period navigation logic
    // This would need to be implemented based on the actual billing history structure
    console.log('Navigate period:', direction);
}
</script>
@endsection

