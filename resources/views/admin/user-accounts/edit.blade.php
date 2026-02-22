@extends('admin.layouts.main')
@section('title', 'Edit Account - ' . ($account->account_name ?? 'Unknown Account'))

@section('content')
<style>
    /* Mobile-first constraints - max width matches mobile viewport (414px = iPhone Pro Max) */
    .edit-account-container {
        max-width: 414px;
        width: 100%;
        margin: 0 auto;
        padding: 16px;
        background: #f8f9fa;
        min-height: 100vh;
        box-sizing: border-box;
    }
    
    @media (max-width: 414px) {
        .edit-account-container {
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
    
    .info-section {
        background: white;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 16px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .info-label {
        font-size: 12px;
        color: #6b7280;
        margin-bottom: 4px;
    }
    
    .info-value {
        font-size: 14px;
        font-weight: 600;
        color: #000;
        margin-bottom: 12px;
    }
    
    .info-value.locked {
        color: #6b7280;
        font-style: italic;
    }
    
    .form-section {
        background: white;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 16px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .form-group {
        margin-bottom: 16px;
    }
    
    .form-label {
        font-size: 14px;
        font-weight: 600;
        color: #000;
        margin-bottom: 8px;
        display: block;
    }
    
    .form-label.required::after {
        content: ' *';
        color: #dc3545;
    }
    
    .form-control {
        width: 100%;
        padding: 12px;
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        font-size: 14px;
        color: #000;
        background: white;
    }
    
    .form-control:focus {
        outline: none;
        border-color: #3294B8;
        box-shadow: 0 0 0 3px rgba(50, 148, 184, 0.1);
    }
    
    .form-control:disabled,
    .form-control[readonly] {
        background: #f5f5f5;
        color: #6b7280;
        cursor: not-allowed;
    }
    
    .form-help {
        font-size: 12px;
        color: #6b7280;
        margin-top: 4px;
    }
    
    .btn-primary {
        width: 100%;
        padding: 12px;
        background: #3294B8;
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: opacity 0.2s;
    }
    
    .btn-primary:hover:not(:disabled) {
        opacity: 0.9;
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
    
    .alert {
        padding: 12px;
        border-radius: 6px;
        margin-bottom: 16px;
        font-size: 14px;
    }
    
    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .alert-danger {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
</style>

<div class="edit-account-container">
    <!-- Unified Header -->
    <div class="unified-header">
        <div class="header-top">
            <div>
                <div class="logo-text">
                    <span class="logo-my">My</span><span class="logo-cities">Cities</span>
                </div>
                <div class="user-label">{{ $account->site->user->name ?? 'Unknown User' }}</div>
            </div>
            <div class="page-label">Edit Account</div>
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

    <!-- Billing Mode Info (Locked) -->
    @if($tariff)
        <div class="info-section">
            <div class="info-label">Billing Mode (Cannot be changed)</div>
            <div class="info-value locked">
                {{ $tariff->isDateToDateBilling() ? 'Date to Date' : 'Period to Period (Monthly)' }}
            </div>
            <div class="info-label">Tariff Template</div>
            <div class="info-value locked">{{ $tariff->template_name ?? 'Unknown' }}</div>
        </div>
    @endif

    <!-- Edit Account Form -->
    <div class="form-section">
        <form method="POST" action="{{ route('user-accounts.update', $account->id) }}">
            @csrf
            @method('PUT')
            
            <!-- Account Name -->
            <div class="form-group">
                <label class="form-label" for="account_name">Account Name</label>
                <input 
                    type="text" 
                    name="account_name" 
                    id="account_name" 
                    class="form-control" 
                    value="{{ old('account_name', $account->account_name) }}"
                >
                @error('account_name')
                    <div style="color: #dc3545; font-size: 12px; margin-top: 4px;">{{ $message }}</div>
                @enderror
            </div>

            <!-- Name on Bill (Required) -->
            <div class="form-group">
                <label class="form-label required" for="name_on_bill">Name on Bill</label>
                <input 
                    type="text" 
                    name="name_on_bill" 
                    id="name_on_bill" 
                    class="form-control" 
                    value="{{ old('name_on_bill', $account->name_on_bill) }}"
                    required
                >
                @error('name_on_bill')
                    <div style="color: #dc3545; font-size: 12px; margin-top: 4px;">{{ $message }}</div>
                @enderror
            </div>

            <!-- Bill Day (Only for MONTHLY billing) -->
            @if($tariff && $tariff->isMonthlyBilling())
                <div class="form-group">
                    <label class="form-label" for="bill_day">Bill Day</label>
                    <input 
                        type="number" 
                        name="bill_day" 
                        id="bill_day" 
                        class="form-control" 
                        value="{{ old('bill_day', $account->bill_day) }}"
                        min="1"
                        max="31"
                        placeholder="Day of month (1-31)"
                    >
                    <div class="form-help">Required for Period to Period (Monthly) billing</div>
                    @error('bill_day')
                        <div style="color: #dc3545; font-size: 12px; margin-top: 4px;">{{ $message }}</div>
                    @enderror
                </div>
            @else
                <!-- Hidden field to preserve value if switching from MONTHLY -->
                <input type="hidden" name="bill_day" value="{{ $account->bill_day }}">
            @endif

            <!-- Read Day -->
            <div class="form-group">
                <label class="form-label" for="read_day">Read Day</label>
                <input 
                    type="number" 
                    name="read_day" 
                    id="read_day" 
                    class="form-control" 
                    value="{{ old('read_day', $account->read_day) }}"
                    min="1"
                    max="31"
                    placeholder="Day of month (1-31)"
                >
                @error('read_day')
                    <div style="color: #dc3545; font-size: 12px; margin-top: 4px;">{{ $message }}</div>
                @enderror
            </div>

            <!-- Water Email -->
            <div class="form-group">
                <label class="form-label" for="water_email">Water Email</label>
                <input 
                    type="email" 
                    name="water_email" 
                    id="water_email" 
                    class="form-control" 
                    value="{{ old('water_email', $account->water_email) }}"
                    placeholder="water@example.com"
                >
                @error('water_email')
                    <div style="color: #dc3545; font-size: 12px; margin-top: 4px;">{{ $message }}</div>
                @enderror
            </div>

            <!-- Electricity Email -->
            <div class="form-group">
                <label class="form-label" for="electricity_email">Electricity Email</label>
                <input 
                    type="email" 
                    name="electricity_email" 
                    id="electricity_email" 
                    class="form-control" 
                    value="{{ old('electricity_email', $account->electricity_email) }}"
                    placeholder="electricity@example.com"
                >
                @error('electricity_email')
                    <div style="color: #dc3545; font-size: 12px; margin-top: 4px;">{{ $message }}</div>
                @enderror
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn-primary" style="margin-top: 16px;">
                Save Changes
            </button>
        </form>
    </div>

    <!-- Navigation Buttons -->
    <a href="{{ route('user-accounts.dashboard', $account->id) }}" class="btn-secondary">
        Back to Dashboard
    </a>
    <a href="{{ route('user-accounts.meters', $account->id) }}" class="btn-secondary">
        Manage Meters
    </a>
</div>
@endsection











