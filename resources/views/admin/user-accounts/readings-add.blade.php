@extends('admin.layouts.main')
@section('title', 'Add Reading - ' . ($account->account_name ?? 'Unknown Account'))

@section('content')
<style>
    /* Mobile-first constraints - max width matches mobile viewport (414px = iPhone Pro Max) */
    .readings-add-container {
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
        .readings-add-container {
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
    
    .period-info {
        background: white;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 16px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .period-label {
        font-size: 14px;
        color: #6b7280;
        margin-bottom: 8px;
    }
    
    .period-value {
        font-size: 16px;
        font-weight: 600;
        color: #000;
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
    
    .btn-primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
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
    
    .meter-select {
        width: 100%;
        padding: 12px;
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        font-size: 14px;
        background: white;
        color: #000;
    }
</style>

<div class="readings-add-container">
    <!-- Unified Header -->
    <div class="unified-header">
        <div class="header-top">
            <div>
                <div class="logo-text">
                    <span class="logo-my">My</span><span class="logo-cities">Cities</span>
                </div>
                <div class="user-label">{{ $account->site->user->name ?? 'Unknown User' }}</div>
            </div>
            <div class="page-label">Add Reading</div>
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

    <!-- Period Info -->
    @if($periodInfo)
        <div class="period-info">
            <div class="period-label">Current Period</div>
            @if($isDateToDate)
                <div class="period-value">
                    Period {{ $periodInfo['period_number'] ?? 1 }}: 
                    {{ \Carbon\Carbon::parse($periodInfo['start_date'])->format('d M Y') }}
                    @if($periodInfo['end_date'])
                        - {{ \Carbon\Carbon::parse($periodInfo['end_date'])->format('d M Y') }}
                    @else
                        - OPEN
                    @endif
                    ({{ $periodInfo['days'] ?? 0 }} days)
                </div>
            @else
                <div class="period-value">
                    {{ \Carbon\Carbon::parse($periodInfo['start_date'])->format('d M Y') }}
                    -
                    {{ \Carbon\Carbon::parse($periodInfo['end_date'])->format('d M Y') }}
                    ({{ $periodInfo['days_in_period'] ?? 0 }} days)
                </div>
            @endif
            @if(isset($periodInfo['status']))
                <div class="period-label" style="margin-top: 8px;">
                    Status: <strong>{{ $periodInfo['status'] }}</strong>
                </div>
            @endif
        </div>
    @endif

    <!-- Add Reading Form -->
    <div class="form-section">
        <form method="POST" action="{{ route('user-accounts.readings.store', $account->id) }}">
            @csrf
            
            <!-- Meter Selection -->
            <div class="form-group">
                <label class="form-label" for="meter_id">Select Meter</label>
                <select name="meter_id" id="meter_id" class="meter-select" required>
                    <option value="">-- Select Meter --</option>
                    @foreach($meters as $meter)
                        <option value="{{ $meter->id }}">
                            {{ $meter->meterTypes->title ?? 'Unknown' }} - {{ $meter->meter_number }}
                            ({{ $meter->meter_title }})
                        </option>
                    @endforeach
                </select>
                @error('meter_id')
                    <div style="color: #dc3545; font-size: 12px; margin-top: 4px;">{{ $message }}</div>
                @enderror
            </div>

            <!-- Reading Date -->
            <div class="form-group">
                <label class="form-label" for="reading_date">Reading Date</label>
                <input 
                    type="date" 
                    name="reading_date" 
                    id="reading_date" 
                    class="form-control" 
                    value="{{ old('reading_date', date('Y-m-d')) }}"
                    required
                >
                @error('reading_date')
                    <div style="color: #dc3545; font-size: 12px; margin-top: 4px;">{{ $message }}</div>
                @enderror
            </div>

            <!-- Reading Value -->
            <div class="form-group">
                <label class="form-label" for="reading_value">Reading Value</label>
                <input 
                    type="text" 
                    name="reading_value" 
                    id="reading_value" 
                    class="form-control" 
                    value="{{ old('reading_value') }}"
                    placeholder="Enter reading value"
                    required
                    pattern="[0-9]*"
                    inputmode="numeric"
                >
                <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">
                    Enter the meter reading value (leading zeros preserved for water meters)
                </div>
                @error('reading_value')
                    <div style="color: #dc3545; font-size: 12px; margin-top: 4px;">{{ $message }}</div>
                @enderror
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn-primary" style="margin-top: 16px;">
                Add Reading & Calculate Billing
            </button>
        </form>
    </div>

    <!-- Back Button -->
    <a href="{{ route('user-accounts.dashboard', $account->id) }}" class="btn-secondary">
        Back to Dashboard
    </a>
</div>

<script>
    // Set minimum date to today or period start date
    document.addEventListener('DOMContentLoaded', function() {
        const dateInput = document.getElementById('reading_date');
        const today = new Date().toISOString().split('T')[0];
        dateInput.setAttribute('max', today);
        
        @if($periodInfo && isset($periodInfo['start_date']))
            const periodStart = '{{ $periodInfo['start_date'] }}';
            if (periodStart) {
                dateInput.setAttribute('min', periodStart);
            }
        @endif
    });
</script>
@endsection

