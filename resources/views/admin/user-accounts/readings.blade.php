@extends('admin.layouts.main')
@section('title', 'Readings - ' . $account->account_name)

@section('content')
<style>
    /* Mobile-first constraints - max width matches mobile viewport (414px = iPhone Pro Max) */
    .readings-container {
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
        .readings-container {
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
    
    .period-text {
        font-size: 13px;
        font-weight: 600;
        color: #000;
        text-align: center;
        padding: 8px 0;
    }
    
    .meter-section {
        background: white;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 16px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
    
    .meter-action-links {
        display: flex;
        justify-content: space-between;
        margin-bottom: 12px;
    }
    
    .meter-action-links a {
        color: #1976d2;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
    }
    
    .meter-action-links a:hover {
        text-decoration: underline;
    }
    
    .meter-action-links a.active {
        color: #3294B8;
        font-weight: 600;
    }
    
    .meter-number {
        font-size: 14px;
        color: #666;
        margin-bottom: 16px;
    }
    
    .reading-history {
        background: #f8f9fa;
        border-radius: 6px;
        padding: 12px;
        margin-bottom: 16px;
    }
    
    .history-header {
        font-size: 12px;
        font-weight: 600;
        color: #666;
        text-transform: uppercase;
        margin-bottom: 8px;
    }
    
    .reading-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px 0;
        border-bottom: 1px solid #e0e0e0;
    }
    
    .reading-row:last-child {
        border-bottom: none;
    }
    
    .reading-label {
        flex: 0 0 auto;
        min-width: 100px;
        font-size: 14px;
        color: #000;
    }
    
    .reading-edit {
        flex: 0 0 auto;
        margin-left: auto;
        margin-right: 8px;
    }
    
    .reading-delete {
        flex: 0 0 auto;
        margin-right: 12px;
    }
    
    .reading-value {
        flex: 0 0 auto;
        text-align: right;
        font-size: 14px;
        font-weight: 600;
        color: #3294B8;
        min-width: 120px;
    }
    
    .no-readings {
        text-align: center;
        color: #999;
        font-size: 13px;
        padding: 12px 0;
    }
    
    .add-reading-link {
        text-align: center;
        margin-top: 12px;
    }
    
    .add-reading-link a {
        color: #1976d2;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
    }
    
    .add-reading-link a:hover {
        text-decoration: underline;
    }
    
    .digit-input-section {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 20px;
        margin-top: 16px;
    }
    
    .digit-display-container {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        margin-bottom: 12px;
    }
    
    .digit-group {
        padding: 16px 24px;
        border: 2px solid #ddd;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        background: white;
    }
    
    .digit-group.active {
        border-color: #3294B8;
        background: #e3f2fd;
    }
    
    .digits {
        font-size: 32px;
        font-weight: 700;
        font-family: 'Courier New', monospace;
        letter-spacing: 4px;
    }
    
    .digits.black {
        color: #000;
    }
    
    .digits.red {
        color: #d32f2f;
    }
    
    .digit-separator {
        font-size: 32px;
        font-weight: 700;
        color: #666;
    }
    
    .group-labels {
        display: flex;
        justify-content: space-between;
        margin-top: 8px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        color: #666;
    }
    
    .keypad-container {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: white;
        border-top: 2px solid #3294B8;
        padding: 16px;
        box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
        z-index: 1000;
    }
    
    .keypad-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
        max-width: 100%;
        width: 100%;
        margin: 0 auto 12px;
        padding: 0 16px;
        box-sizing: border-box;
    }
    
    .keypad-btn {
        background: #f5f5f5;
        border: 1px solid #ddd;
        border-radius: 6px;
        padding: 16px;
        font-size: 20px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .keypad-btn:hover {
        background: #e0e0e0;
    }
    
    .keypad-btn:active {
        background: #bdbdbd;
    }
    
    .keypad-actions {
        display: flex;
        gap: 12px;
        max-width: 100%;
        width: 100%;
        margin: 0 auto;
        padding: 0 16px;
        box-sizing: border-box;
    }
    
    /* Date Selector Styles */
    .date-selector-container {
        margin-bottom: 16px;
        padding: 12px;
        background: #f0f0f0;
        border-radius: 6px;
    }
    
    .date-label {
        display: block;
        font-size: 14px;
        font-weight: 600;
        color: #333;
        margin-bottom: 8px;
    }
    
    .date-input {
        width: 100%;
        padding: 10px;
        font-size: 16px;
        border: 1px solid #ddd;
        border-radius: 6px;
        box-sizing: border-box;
    }
    
    /* Keyboard Input Styles */
    .keyboard-input-container {
        margin-bottom: 16px;
        padding: 12px;
        background: #f9f9f9;
        border-radius: 6px;
    }
    
    .keyboard-input-group {
        margin-bottom: 12px;
    }
    
    .keyboard-input-group:last-child {
        margin-bottom: 0;
    }
    
    .keyboard-input-label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #666;
        margin-bottom: 6px;
    }
    
    .keyboard-input-field {
        width: 100%;
        padding: 12px;
        font-size: 18px;
        border: 2px solid #ddd;
        border-radius: 6px;
        box-sizing: border-box;
        text-align: center;
        font-weight: 600;
    }
    
    .keyboard-input-field:focus {
        outline: none;
        border-color: #3294B8;
    }
    
    .action-btn {
        flex: 1;
        padding: 12px;
        border: none;
        border-radius: 6px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .action-btn.cancel {
        background: #f5f5f5;
        color: #666;
    }
    
    .action-btn.cancel:hover {
        background: #e0e0e0;
    }
    
    .action-btn.enter {
        background: #3294B8;
        color: white;
    }
    
    .action-btn.enter:hover {
        background: #2878a0;
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
    }
    
    .nav-tab:hover {
        color: #3294B8;
    }
    
    .nav-tab.active {
        color: #3294B8;
    }
    
    .content-scroll {
        padding-bottom: 200px; /* Space for keypad/nav */
    }
</style>

<div class="readings-container" data-component="readings-container" data-component-id="readings-container-1">
    <!-- Back Button -->
    <a href="{{ route('user-accounts.manager') }}" class="btn btn-secondary mb-3" data-component="back-button" data-component-id="readings-back-button-1">
        <i class="fas fa-arrow-left mr-2"></i> Back to User Manager
    </a>

    @if(isset($error))
        <div class="alert alert-warning" data-component="error-alert" data-component-id="readings-error-1">
            <i class="fas fa-exclamation-triangle mr-2"></i>{{ $error }}
        </div>
    @endif

    @if($meterData)
        <!-- Unified Header -->
        <div class="unified-header" data-component="unified-header" data-component-id="readings-header-1">
            <div class="header-top" data-component="header-top" data-component-id="readings-header-top-1">
                <div>
                    <div class="logo-text" data-component="logo" data-component-id="readings-logo-1">
                        <span class="logo-my">My</span><span class="logo-cities">Cities</span>
                    </div>
                    <div class="user-label" data-component="user-label" data-component-id="readings-user-label-1">User: {{ $meterData['account']['name'] ?? '' }}</div>
                </div>
                <span class="page-label" data-component="page-label" data-component-id="readings-page-label-1">Readings</span>
            </div>
            <div class="header-divider" data-component="header-divider" data-component-id="readings-header-divider-1"></div>
        </div>

        <!-- Title -->
        <div class="page-title" data-component="page-title" data-component-id="readings-page-title-1">READINGS</div>
        <div class="period-text" data-component="period-display" data-component-id="readings-period-display-1">
            Period: {{ \Carbon\Carbon::parse($periodInfo['start_date'] ?? now())->format('d M Y') }} 
            to {{ \Carbon\Carbon::parse($periodInfo['end_date_display'] ?? $periodInfo['end_date'] ?? now())->format('d M Y') }}
        </div>

        <!-- Scrollable Content -->
        <div class="content-scroll" data-component="readings-section" data-component-id="readings-section-1">
            <!-- Water Section -->
            @if(($meterData['water']['enabled'] ?? false) && isset($meterData['water']['meter']) && $meterData['water']['meter'])
                <div class="meter-section" data-component="water-meter-section" data-component-id="readings-water-section-1">
                    <div class="meter-type-header" data-component="meter-type-header" data-component-id="readings-water-header-1">
                        <i class="fas fa-tint meter-icon" style="font-size: 24px;"></i>
                        <span class="meter-type-name">Water</span>
                    </div>

                    <div class="meter-action-links" data-component="meter-action-links" data-component-id="readings-water-actions-1">
                        <a href="#" onclick="activateWaterInput(); return false;" id="waterEnterLink" data-component="action-link" data-component-id="water-enter-link-1">Enter reading</a>
                        <a href="#" onclick="toggleWaterDetails(); return false;" id="waterDetailsLink" data-component="action-link" data-component-id="water-details-link-1">Show Details</a>
                    </div>

                    <div class="meter-number" data-component="meter-number" data-component-id="readings-water-meter-number-1">Meter Number #{{ $meterData['water']['meter']['number'] ?? 'N/A' }}</div>

                    <!-- Reading History -->
                    <div class="reading-history" data-component="reading-history" data-component-id="readings-water-history-1">
                        <div class="history-header" data-component="history-header" data-component-id="water-history-header-1">Readings this Period</div>
                        @if(isset($meterData['water']['readings']) && count($meterData['water']['readings']) > 0)
                            @foreach($meterData['water']['readings'] as $index => $reading)
                                <div class="reading-row" data-component="reading-row" data-component-id="water-reading-row-{{ $index + 1 }}">
                                    <span class="reading-label" data-component="reading-label" data-component-id="water-reading-label-{{ $index + 1 }}">{{ \Carbon\Carbon::parse($reading['date'])->format('d M Y') }}</span>
                                    @if(isset($reading['id']))
                                        <span class="reading-edit" data-component="reading-edit" data-component-id="water-reading-edit-{{ $index + 1 }}">
                                            <button type="button" class="edit-reading-btn" onclick="editReading({{ $reading['id'] }}, 'water', '{{ \Carbon\Carbon::parse($reading['date'])->format('Y-m-d') }}', {{ $reading['value'] ?? 0 }})" title="Edit reading">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </span>
                                        <span class="reading-delete" data-component="reading-delete" data-component-id="water-reading-delete-{{ $index + 1 }}">
                                            <button type="button" class="delete-reading-btn" onclick="deleteReading({{ $reading['id'] }}, 'water', '{{ \Carbon\Carbon::parse($reading['date'])->format('d M Y') }}')" title="Delete reading">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </span>
                                    @endif
                                    <span class="reading-value" data-component="reading-value" data-component-id="water-reading-value-{{ $index + 1 }}">
                                        @php
                                            $value = $reading['value'] ?? 0;
                                            $kiloliters = number_format($value / 1000, 2, '.', '');
                                            echo number_format($value, 0) . ' ( ' . $kiloliters . ' kl )';
                                        @endphp
                                    </span>
                                </div>
                            @endforeach
                        @else
                            <div class="no-readings">No readings recorded yet</div>
                        @endif
                    </div>

                    <!-- Add New Reading Link -->
                    <div class="add-reading-link" id="waterAddLink" data-component="add-reading-link" data-component-id="water-add-link-1">
                        <a href="#" onclick="activateWaterInput(); return false;" data-component="add-reading-button" data-component-id="water-add-button-1">Add new reading</a>
                    </div>

                    <!-- Water Reading Input (hidden by default) -->
                    <div id="waterInputSection" style="display: none;" class="digit-input-section" data-component="water-reading-input" data-component-id="water-reading-input-1">
                        <form id="waterReadingForm" method="POST" action="{{ route('user-accounts.manager.add-reading') }}" onsubmit="submitReading(event, 'water'); return false;" data-component="reading-form" data-component-id="water-reading-form-1">
                            @csrf
                            <input type="hidden" name="meter_id" value="{{ $meterData['water']['meter']['id'] ?? ($meterData['water']['meter_id'] ?? '') }}" data-component="hidden-input" data-component-id="water-meter-id-1">
                            
                            <!-- Date Selector -->
                            <div class="date-selector-container" data-component="date-selector" data-component-id="water-date-selector-1">
                                <label for="waterReadingDateInput" class="date-label">Reading Date:</label>
                                <input type="date" id="waterReadingDateInput" name="reading_date" class="date-input" value="{{ date('Y-m-d') }}" onchange="updateWaterDate(this.value)" data-component="date-input" data-component-id="water-date-input-1">
                            </div>
                            <input type="hidden" name="reading_date" id="waterReadingDate" value="{{ date('Y-m-d') }}" data-component="hidden-input" data-component-id="water-reading-date-1">
                            <input type="hidden" name="reading_value" id="waterReadingValue" data-component="hidden-input" data-component-id="water-reading-value-1">
                            
                            <!-- Previous Reading Display -->
                            @if(isset($meterData['water']['readings']) && count($meterData['water']['readings']) > 0)
                                @php
                                    $lastReading = collect($meterData['water']['readings'])->sortByDesc('date')->first();
                                    $lastValue = $lastReading['value'] ?? 0;
                                    $lastValueKL = number_format($lastValue / 1000, 2, '.', '');
                                @endphp
                                <div class="previous-reading-display" style="margin: 16px 0; padding: 12px; background: #f8f9fa; border-radius: 6px; text-align: center;">
                                    <div style="font-size: 12px; color: #666; margin-bottom: 4px;">Last input:</div>
                                    <div style="font-size: 18px; font-weight: 600; color: #333;">
                                        {{ number_format($lastValue, 0) }} ( {{ $lastValueKL }} kl )
                                    </div>
                                </div>
                            @endif
                            
                            <!-- Simple Decimal Input Field -->
                            <div class="water-reading-input-container" style="margin: 16px 0;">
                                <label for="waterReadingInput" class="keyboard-input-label" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                                    Enter a figure:
                                </label>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <input 
                                        type="number" 
                                        id="waterReadingInput" 
                                        class="form-control" 
                                        step="0.01" 
                                        min="0" 
                                        placeholder="0.00" 
                                        oninput="updateWaterReadingFromInput()" 
                                        style="flex: 1; font-size: 18px; padding: 12px; text-align: center; font-weight: 600;"
                                        data-component="keyboard-input-field" 
                                        data-component-id="water-reading-input-1">
                                    <span style="font-size: 16px; font-weight: 600; color: #666; min-width: 40px;">kl</span>
                                </div>
                                <div style="text-align: center; margin-top: 8px; font-size: 12px; color: #999;">
                                    Format: 00000.00 Kl/litres
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            <!-- Electricity Section -->
            @if(($meterData['electricity']['enabled'] ?? false) && isset($meterData['electricity']['meter']) && $meterData['electricity']['meter'])
                <div class="meter-section" data-component="electricity-meter-section" data-component-id="readings-electricity-section-1">
                    <div class="meter-type-header electricity" data-component="meter-type-header" data-component-id="readings-electricity-header-1">
                        <i class="fas fa-bolt meter-icon" style="font-size: 24px;"></i>
                        <span class="meter-type-name">Electricity</span>
                    </div>

                    <div class="meter-action-links" data-component="meter-action-links" data-component-id="readings-electricity-actions-1">
                        <a href="#" onclick="activateElectricityInput(); return false;" id="electricityEnterLink" data-component="action-link" data-component-id="electricity-enter-link-1">Enter reading</a>
                        <a href="#" onclick="toggleElectricityDetails(); return false;" id="electricityDetailsLink" data-component="action-link" data-component-id="electricity-details-link-1">Show Details</a>
                    </div>

                    <div class="meter-number" data-component="meter-number" data-component-id="readings-electricity-meter-number-1">Meter Number #{{ $meterData['electricity']['meter']['number'] ?? ($meterData['electricity']['meter']['meter_number'] ?? 'N/A') }}</div>

                    <!-- Reading History -->
                    <div class="reading-history" data-component="reading-history" data-component-id="readings-electricity-history-1">
                        <div class="history-header" data-component="history-header" data-component-id="electricity-history-header-1">Readings this Period</div>
                        @if(isset($meterData['electricity']['readings']) && count($meterData['electricity']['readings']) > 0)
                            @foreach($meterData['electricity']['readings'] as $index => $reading)
                                <div class="reading-row" data-component="reading-row" data-component-id="electricity-reading-row-{{ $index + 1 }}">
                                    <span class="reading-label" data-component="reading-label" data-component-id="electricity-reading-label-{{ $index + 1 }}">{{ \Carbon\Carbon::parse($reading['date'])->format('d M Y') }}</span>
                                    @if(isset($reading['id']))
                                        <span class="reading-edit" data-component="reading-edit" data-component-id="electricity-reading-edit-{{ $index + 1 }}">
                                            <button type="button" class="edit-reading-btn" onclick="editReading({{ $reading['id'] }}, 'electricity', '{{ \Carbon\Carbon::parse($reading['date'])->format('Y-m-d') }}', {{ $reading['value'] ?? 0 }})" title="Edit reading">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </span>
                                        <span class="reading-delete" data-component="reading-delete" data-component-id="electricity-reading-delete-{{ $index + 1 }}">
                                            <button type="button" class="delete-reading-btn" onclick="deleteReading({{ $reading['id'] }}, 'electricity', '{{ \Carbon\Carbon::parse($reading['date'])->format('d M Y') }}')" title="Delete reading">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </span>
                                    @endif
                                    <span class="reading-value" data-component="reading-value" data-component-id="electricity-reading-value-{{ $index + 1 }}">{{ number_format($reading['value'], 0) }} kWh</span>
                                </div>
                            @endforeach
                        @else
                            <div class="no-readings" data-component="no-readings" data-component-id="electricity-no-readings-1">No readings recorded yet</div>
                        @endif
                    </div>

                    <!-- Add New Reading Link -->
                    <div class="add-reading-link" id="electricityAddLink" data-component="add-reading-link" data-component-id="electricity-add-link-1">
                        <a href="#" onclick="activateElectricityInput(); return false;" data-component="add-reading-button" data-component-id="electricity-add-button-1">Add new reading</a>
                    </div>

                    <!-- Electricity Digit Input (hidden by default) -->
                    <div id="electricityInputSection" style="display: none;" class="digit-input-section" data-component="electricity-reading-input" data-component-id="electricity-reading-input-1">
                        <form id="electricityReadingForm" method="POST" action="{{ route('user-accounts.manager.add-reading') }}" onsubmit="submitReading(event, 'electricity'); return false;" data-component="reading-form" data-component-id="electricity-reading-form-1">
                            @csrf
                            <input type="hidden" name="meter_id" value="{{ $meterData['electricity']['meter']['id'] ?? ($meterData['electricity']['meter_id'] ?? '') }}" data-component="hidden-input" data-component-id="electricity-meter-id-1">
                            
                            <!-- Date Selector -->
                            <div class="date-selector-container" data-component="date-selector" data-component-id="electricity-date-selector-1">
                                <label for="electricityReadingDateInput" class="date-label">Reading Date:</label>
                                <input type="date" id="electricityReadingDateInput" name="reading_date" class="date-input" value="{{ date('Y-m-d') }}" onchange="updateElectricityDate(this.value)" data-component="date-input" data-component-id="electricity-date-input-1">
                            </div>
                            <input type="hidden" name="reading_date" id="electricityReadingDate" value="{{ date('Y-m-d') }}" data-component="hidden-input" data-component-id="electricity-reading-date-1">
                            <input type="hidden" name="reading_value" id="electricityReadingValue" data-component="hidden-input" data-component-id="electricity-reading-value-1">
                            
                            <!-- Keyboard Input Field -->
                            <div class="keyboard-input-container" data-component="keyboard-input" data-component-id="electricity-keyboard-input-1">
                                <div class="keyboard-input-group">
                                    <label for="electricityInput" class="keyboard-input-label">kWh:</label>
                                    <input type="number" id="electricityInput" class="keyboard-input-field" min="0" max="999999" step="0.01" placeholder="000000" oninput="updateElectricityFromKeyboard()" data-component="keyboard-input-field" data-component-id="electricity-keyboard-1">
                                </div>
                            </div>
                            
                            <div class="digit-display-container" data-component="digit-display" data-component-id="electricity-digit-display-1">
                                <div class="digit-group black-group single" id="electricityGroup" onclick="setActiveGroup('electricity')" data-component="digit-group" data-component-id="electricity-group-1">
                                    <span class="digits black" id="electricityDisplay" data-component="digit-display" data-component-id="electricity-display-1">000000</span>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
            
            @if((!isset($meterData['water']['enabled']) || !$meterData['water']['enabled']) && (!isset($meterData['electricity']['enabled']) || !$meterData['electricity']['enabled']))
                <div class="alert alert-info" data-component="info-alert" data-component-id="readings-no-meters-1">
                    <i class="fas fa-info-circle mr-2"></i>No meters are enabled for this account. Please configure meters in the account settings.
                </div>
            @endif
        </div>

        <!-- Numeric Keypad (shown when input is active) -->
        <div id="keypadContainer" style="display: none;" class="keypad-container" data-component="keypad-container" data-component-id="keypad-container-1">
            <div class="keypad-grid" data-component="keypad-grid" data-component-id="keypad-grid-1">
                <button class="keypad-btn" onclick="handleKeyPress('1')" data-component="keypad-button" data-component-id="keypad-btn-1">1</button>
                <button class="keypad-btn" onclick="handleKeyPress('2')" data-component="keypad-button" data-component-id="keypad-btn-2">2</button>
                <button class="keypad-btn" onclick="handleKeyPress('3')" data-component="keypad-button" data-component-id="keypad-btn-3">3</button>
                <button class="keypad-btn" onclick="handleKeyPress('4')" data-component="keypad-button" data-component-id="keypad-btn-4">4</button>
                <button class="keypad-btn" onclick="handleKeyPress('5')" data-component="keypad-button" data-component-id="keypad-btn-5">5</button>
                <button class="keypad-btn" onclick="handleKeyPress('6')" data-component="keypad-button" data-component-id="keypad-btn-6">6</button>
                <button class="keypad-btn" onclick="handleKeyPress('7')" data-component="keypad-button" data-component-id="keypad-btn-7">7</button>
                <button class="keypad-btn" onclick="handleKeyPress('8')" data-component="keypad-button" data-component-id="keypad-btn-8">8</button>
                <button class="keypad-btn" onclick="handleKeyPress('9')" data-component="keypad-button" data-component-id="keypad-btn-9">9</button>
                <button class="keypad-btn" onclick="handleKeyPress('.')" data-component="keypad-button" data-component-id="keypad-btn-dot">.</button>
                <button class="keypad-btn" onclick="handleKeyPress('0')" data-component="keypad-button" data-component-id="keypad-btn-0">0</button>
                <button class="keypad-btn" onclick="handleKeyPress('backspace')" data-component="keypad-button" data-component-id="keypad-btn-backspace">⌫</button>
            </div>
            <div class="keypad-actions" data-component="keypad-actions" data-component-id="keypad-actions-1">
                <button class="action-btn cancel" onclick="handleCancel()" data-component="action-button" data-component-id="keypad-cancel-1">CANCEL</button>
                <button class="action-btn enter" onclick="handleEnter()" data-component="action-button" data-component-id="keypad-enter-1">ENTER</button>
            </div>
        </div>

        <!-- Fixed Bottom Navigation (shown when keypad not active) -->
        <div id="bottomNav" class="fixed-nav-tabs" data-component="bottom-navigation" data-component-id="readings-bottom-nav-1">
            <a href="{{ route('user-accounts.dashboard', ['accountId' => $account->id]) }}" class="nav-tab" data-component="nav-tab" data-component-id="readings-nav-home-1">
                <i class="fas fa-home" style="font-size: 22px;"></i>
                <span>Home</span>
            </a>
            <a href="{{ route('user-accounts.dashboard', ['accountId' => $account->id]) }}" class="nav-tab" data-component="nav-tab" data-component-id="readings-nav-dashboard-1">
                <i class="fas fa-chart-line" style="font-size: 22px;"></i>
                <span>Dashboard</span>
            </a>
            <a href="{{ route('user-accounts.readings', ['accountId' => $account->id]) }}" class="nav-tab active" data-component="nav-tab" data-component-id="readings-nav-readings-1">
                <i class="fas fa-edit" style="font-size: 22px;"></i>
                <span>Readings</span>
            </a>
            <a href="{{ route('user-accounts.accounts', ['accountId' => $account->id]) }}" class="nav-tab" data-component="nav-tab" data-component-id="readings-nav-accounts-1">
                <i class="fas fa-user-circle" style="font-size: 22px;"></i>
                <span>Accounts</span>
            </a>
        </div>
    @else
        <div class="alert alert-danger" data-component="error-alert" data-component-id="readings-load-error-1">
            <i class="fas fa-exclamation-circle mr-2"></i>Failed to load readings data
        </div>
    @endif
</div>

<!-- Component Debug Script -->
<script src="{{ asset('js/component-debug.js') }}"></script>

<script>
let activeMeter = null;
let activeGroup = null;
let waterKiloliters = '';
let waterLiters = '';
let electricityReading = '';

function formatWaterReading(value) {
    const totalLiters = parseInt(value) || 0;
    const kiloliters = Math.floor(totalLiters / 1000);
    const liters = Math.floor((totalLiters % 1000) / 10);
    return `${kiloliters.toString().padStart(5, '0')}-${liters.toString().padStart(2, '0')}`;
}

function activateWaterInput() {
    activeMeter = 'water';
    document.getElementById('waterInputSection').style.display = 'block';
    document.getElementById('waterAddLink').style.display = 'none';
    document.getElementById('keypadContainer').style.display = 'none'; // Hide keypad for simple input
    document.getElementById('bottomNav').style.display = 'none';
    
    // Reset input field
    const waterInput = document.getElementById('waterReadingInput');
    if (waterInput) {
        waterInput.value = '';
        // Focus on input after a short delay
        setTimeout(() => waterInput.focus(), 100);
    }
    
    // Set default date to today if not set
    const dateInput = document.getElementById('waterReadingDateInput');
    if (dateInput && !dateInput.value) {
        dateInput.value = new Date().toISOString().split('T')[0];
        updateWaterDate(dateInput.value);
    }
}

// New function to handle decimal kiloliter input
function updateWaterReadingFromInput() {
    const waterInput = document.getElementById('waterReadingInput');
    if (!waterInput) return;
    
    // Get the decimal kiloliter value
    const klValue = parseFloat(waterInput.value) || 0;
    
    // Convert to liters (kiloliters * 1000)
    const totalLiters = Math.round(klValue * 1000);
    
    // Update hidden field for form submission
    document.getElementById('waterReadingValue').value = totalLiters;
}

function activateElectricityInput() {
    activeMeter = 'electricity';
    activeGroup = 'electricity';
    electricityReading = '';
    document.getElementById('electricityInputSection').style.display = 'block';
    document.getElementById('electricityAddLink').style.display = 'none';
    document.getElementById('keypadContainer').style.display = 'block';
    document.getElementById('bottomNav').style.display = 'none';
    
    // Reset keyboard input
    const electricityInput = document.getElementById('electricityInput');
    if (electricityInput) electricityInput.value = '';
    
    // Set default date to today if not set
    const dateInput = document.getElementById('electricityReadingDateInput');
    if (dateInput && !dateInput.value) {
        dateInput.value = new Date().toISOString().split('T')[0];
        updateElectricityDate(dateInput.value);
    }
    
    updateElectricityDisplay();
}

function setActiveGroup(group) {
    activeGroup = group;
    // Update visual feedback
    document.querySelectorAll('.digit-group').forEach(el => el.classList.remove('active'));
    if (group === 'kiloliters') {
        document.getElementById('waterKilolitersGroup').classList.add('active');
    } else if (group === 'liters') {
        document.getElementById('waterLitersGroup').classList.add('active');
    } else if (group === 'electricity') {
        document.getElementById('electricityGroup').classList.add('active');
    }
}

function handleKeyPress(key) {
    if (activeMeter === 'water') {
        if (key === 'backspace') {
            if (activeGroup === 'kiloliters') {
                waterKiloliters = waterKiloliters.slice(0, -1);
            } else if (activeGroup === 'liters') {
                waterLiters = waterLiters.slice(0, -1);
            }
        } else if (key === '.') {
            // Switch groups for water
            activeGroup = activeGroup === 'kiloliters' ? 'liters' : 'kiloliters';
            setActiveGroup(activeGroup);
        } else {
            if (activeGroup === 'kiloliters' && waterKiloliters.length < 5) {
                waterKiloliters += key;
            } else if (activeGroup === 'liters' && waterLiters.length < 2) {
                waterLiters += key;
            }
        }
        updateWaterDisplay();
    } else if (activeMeter === 'electricity') {
        if (key === 'backspace') {
            electricityReading = electricityReading.slice(0, -1);
        } else if (key !== '.') {
            if (electricityReading.length < 6) {
                electricityReading += key;
            }
        }
        updateElectricityDisplay();
    }
}

function updateWaterDisplay() {
    document.getElementById('waterKilolitersDisplay').textContent = waterKiloliters.padStart(5, '0');
    document.getElementById('waterLitersDisplay').textContent = waterLiters.padStart(2, '0');
    
    // Update keyboard input fields
    const kilolitersInput = document.getElementById('waterKilolitersInput');
    const litersInput = document.getElementById('waterLitersInput');
    if (kilolitersInput) kilolitersInput.value = waterKiloliters || '';
    if (litersInput) litersInput.value = waterLiters || '';
    
    // Calculate total liters for submission
    const totalLiters = (parseInt(waterKiloliters || '0') * 1000) + (parseInt(waterLiters || '0') * 10);
    document.getElementById('waterReadingValue').value = totalLiters;
}

function updateWaterFromKeyboard() {
    const kilolitersInput = document.getElementById('waterKilolitersInput');
    const litersInput = document.getElementById('waterLitersInput');
    
    if (kilolitersInput) {
        const klValue = kilolitersInput.value.replace(/[^0-9]/g, '').slice(0, 5);
        waterKiloliters = klValue;
        kilolitersInput.value = klValue;
    }
    
    if (litersInput) {
        const lValue = litersInput.value.replace(/[^0-9]/g, '').slice(0, 2);
        waterLiters = lValue;
        litersInput.value = lValue;
    }
    
    updateWaterDisplay();
}

function updateWaterDate(dateValue) {
    document.getElementById('waterReadingDate').value = dateValue;
}

function updateElectricityDate(dateValue) {
    document.getElementById('electricityReadingDate').value = dateValue;
}

function updateElectricityFromKeyboard() {
    const electricityInput = document.getElementById('electricityInput');
    
    if (electricityInput) {
        const value = electricityInput.value.replace(/[^0-9.]/g, '');
        const numValue = parseFloat(value) || 0;
        electricityReading = Math.floor(numValue).toString();
        electricityInput.value = numValue > 0 ? numValue : '';
        updateElectricityDisplay();
    }
}

function updateElectricityDisplay() {
    document.getElementById('electricityDisplay').textContent = electricityReading.padStart(6, '0');
    document.getElementById('electricityReadingValue').value = electricityReading || '0';
    
    // Update keyboard input field
    const electricityInput = document.getElementById('electricityInput');
    if (electricityInput && electricityReading) {
        electricityInput.value = parseInt(electricityReading) || '';
    }
}

function handleCancel() {
    activeMeter = null;
    activeGroup = null;
    waterKiloliters = '';
    waterLiters = '';
    electricityReading = '';
    document.getElementById('waterInputSection').style.display = 'none';
    document.getElementById('electricityInputSection').style.display = 'none';
    document.getElementById('waterAddLink').style.display = 'block';
    document.getElementById('electricityAddLink').style.display = 'block';
    document.getElementById('keypadContainer').style.display = 'none';
    document.getElementById('bottomNav').style.display = 'flex';
}

function handleEnter() {
    if (activeMeter === 'water') {
        submitReading(null, 'water');
    } else if (activeMeter === 'electricity') {
        submitReading(null, 'electricity');
    }
}

function submitReading(event, type) {
    if (event) event.preventDefault();
    
    const form = type === 'water' ? document.getElementById('waterReadingForm') : document.getElementById('electricityReadingForm');
    const formData = new FormData(form);
    
    // Get reading value and date for validation
    let readingValue = 0;
    const readingDate = formData.get('reading_date');
    
    if (type === 'water') {
        // Get decimal kiloliter value from new input field
        const waterInput = document.getElementById('waterReadingInput');
        if (waterInput && waterInput.value) {
            const klValue = parseFloat(waterInput.value);
            if (isNaN(klValue) || klValue < 0) {
                alert('Please enter a valid reading value in kiloliters (e.g., 13.22)');
                return;
            }
            readingValue = Math.round(klValue * 1000); // Convert to liters
        } else {
            alert('Please enter a reading value');
            return;
        }
    } else {
        readingValue = parseFloat(document.getElementById('electricityInput')?.value || electricityReading || '0') || 0;
        if (!readingValue || readingValue <= 0) {
            alert('Please enter a valid reading value');
            return;
        }
    }
    
    // Helper function to normalize date strings
    function normalizeDate(dateStr) {
        if (!dateStr) return '';
        if (dateStr instanceof Date) return dateStr.toISOString().split('T')[0];
        // Handle formats like "24 Dec 2025" or "2025-12-24"
        if (dateStr.includes(' ')) {
            // Parse "24 Dec 2025" format
            const parts = dateStr.split(' ');
            if (parts.length === 3) {
                const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                const monthIndex = months.indexOf(parts[1]);
                if (monthIndex !== -1) {
                    const day = parts[0].padStart(2, '0');
                    const month = String(monthIndex + 1).padStart(2, '0');
                    const year = parts[2];
                    return `${year}-${month}-${day}`;
                }
            }
        }
        // Assume it's already in YYYY-MM-DD format
        return dateStr.split(' ')[0].split('T')[0];
    }
    
    // VALIDATION: Check date uniqueness and value constraints
    const meterData = @json($meterData);
    const readings = type === 'water' 
        ? (meterData.water?.readings || [])
        : (meterData.electricity?.readings || []);
    
    // VALIDATION RULE 1: Date cannot be the same as an existing date
    const existingReading = readings.find(r => {
        const readingDateStr = normalizeDate(r.date);
        return readingDateStr === readingDate;
    });
    
    if (existingReading) {
        alert('A reading with this date already exists. Please delete the existing reading first or choose a different date.');
        return;
    }
    
    // VALIDATION RULE 2: Value must be between previous and next readings
    if (readings.length > 0) {
        // Sort readings by date
        const sortedReadings = [...readings].sort((a, b) => {
            const dateA = normalizeDate(a.date);
            const dateB = normalizeDate(b.date);
            return dateA.localeCompare(dateB);
        });
        
        // Find where this date would fit
        let previousReading = null;
        let nextReading = null;
        
        for (let i = 0; i < sortedReadings.length; i++) {
            const r = sortedReadings[i];
            const rDate = normalizeDate(r.date);
            
            if (rDate < readingDate) {
                previousReading = r;
            } else if (rDate > readingDate) {
                nextReading = r;
                break;
            }
        }
        
        // Check value is not lower than previous reading
        if (previousReading) {
            const prevValue = parseFloat(previousReading.value || 0);
            if (readingValue < prevValue) {
                const prevDate = previousReading.date.includes(' ') ? previousReading.date : normalizeDate(previousReading.date);
                const prevValueKL = (prevValue / 1000).toFixed(2);
                const readingValueKL = (readingValue / 1000).toFixed(2);
                if (type === 'water') {
                    alert('Reading value cannot be lower than the previous reading.\n\n' +
                          'Previous: ' + prevValue + ' litres ( ' + prevValueKL + ' kl ) on ' + prevDate + '\n' +
                          'Your input: ' + readingValue + ' litres ( ' + readingValueKL + ' kl )');
                } else {
                    alert('Reading value cannot be lower than the previous reading value (' + prevValue + ' on ' + prevDate + ')');
                }
                return;
            }
        }
        
        // Check value is not higher than next reading
        if (nextReading) {
            const nextValue = parseFloat(nextReading.value || 0);
            if (readingValue > nextValue) {
                const nextDate = nextReading.date.includes(' ') ? nextReading.date : normalizeDate(nextReading.date);
                const nextValueKL = (nextValue / 1000).toFixed(2);
                const readingValueKL = (readingValue / 1000).toFixed(2);
                if (type === 'water') {
                    alert('Reading value cannot be higher than the next reading.\n\n' +
                          'Next: ' + nextValue + ' litres ( ' + nextValueKL + ' kl ) on ' + nextDate + '\n' +
                          'Your input: ' + readingValue + ' litres ( ' + readingValueKL + ' kl )');
                } else {
                    alert('Reading value cannot be higher than the next reading value (' + nextValue + ' on ' + nextDate + ')');
                }
                return;
            }
        }
    }
    
    fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 200) {
            alert('Reading saved successfully!');
            location.reload(); // Reload to show new reading
        } else {
            alert('Error: ' + (data.message || 'Failed to save reading'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error saving reading. Please try again.');
    });
}

function toggleWaterDetails() {
    // Implementation for showing/hiding water details
    alert('Water details toggle - to be implemented');
}

function toggleElectricityDetails() {
    // Implementation for showing/hiding electricity details
    alert('Electricity details toggle - to be implemented');
}

function deleteReading(readingId, meterType, readingDate) {
    if (!confirm(`Are you sure you want to delete the ${meterType} reading from ${readingDate}?\n\nThis action cannot be undone.`)) {
        return;
    }
    
    const reason = prompt('Please provide a reason for deleting this reading (minimum 5 characters):');
    if (!reason || reason.trim().length < 5) {
        alert('A reason of at least 5 characters is required to delete a reading.');
        return;
    }
    
    // Get CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                     document.querySelector('input[name="_token"]')?.value;
    
    fetch(`/api/v1/admin/readings/${readingId}/delete`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
            'Authorization': 'Bearer ' + (localStorage.getItem('auth_token') || '')
        },
        body: JSON.stringify({
            reason: reason.trim()
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === true || data.code === 200) {
            const message = data.deleted_bills_count > 0 
                ? `Reading deleted successfully. ${data.deleted_bills_count} related bill(s) were also deleted.`
                : 'Reading deleted successfully.';
            alert(message);
            location.reload(); // Reload to reflect changes
        } else {
            alert('Error: ' + (data.msg || data.message || 'Failed to delete reading'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deleting reading. Please try again.');
    });
}

// Add keyboard event listeners for direct input
document.addEventListener('DOMContentLoaded', function() {
    // Water keyboard inputs
    const waterKilolitersInput = document.getElementById('waterKilolitersInput');
    const waterLitersInput = document.getElementById('waterLitersInput');
    
    if (waterKilolitersInput) {
        waterKilolitersInput.addEventListener('keydown', function(e) {
            // Allow: backspace, delete, tab, escape, enter, decimal point
            if ([46, 8, 9, 27, 13, 110, 190].indexOf(e.keyCode) !== -1 ||
                // Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                (e.keyCode === 65 && e.ctrlKey === true) ||
                (e.keyCode === 67 && e.ctrlKey === true) ||
                (e.keyCode === 86 && e.ctrlKey === true) ||
                (e.keyCode === 88 && e.ctrlKey === true) ||
                // Allow: home, end, left, right
                (e.keyCode >= 35 && e.keyCode <= 39)) {
                return;
            }
            // Ensure that it is a number and stop the keypress
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        });
    }
    
    if (waterLitersInput) {
        waterLitersInput.addEventListener('keydown', function(e) {
            if ([46, 8, 9, 27, 13, 110, 190].indexOf(e.keyCode) !== -1 ||
                (e.keyCode === 65 && e.ctrlKey === true) ||
                (e.keyCode === 67 && e.ctrlKey === true) ||
                (e.keyCode === 86 && e.ctrlKey === true) ||
                (e.keyCode === 88 && e.ctrlKey === true) ||
                (e.keyCode >= 35 && e.keyCode <= 39)) {
                return;
            }
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        });
    }
    
    // Electricity keyboard input
    const electricityInput = document.getElementById('electricityInput');
    if (electricityInput) {
        electricityInput.addEventListener('keydown', function(e) {
            // Allow decimal point for electricity
            if ([46, 8, 9, 27, 13, 110, 190].indexOf(e.keyCode) !== -1 ||
                (e.keyCode === 65 && e.ctrlKey === true) ||
                (e.keyCode === 67 && e.ctrlKey === true) ||
                (e.keyCode === 86 && e.ctrlKey === true) ||
                (e.keyCode === 88 && e.ctrlKey === true) ||
                (e.keyCode >= 35 && e.keyCode <= 39)) {
                return;
            }
            // Ensure that it is a number and stop the keypress
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        });
    }
});

// Edit Reading Functions
let currentEditingReading = {
    id: null,
    meterType: null,
    originalValue: null,
    originalDate: null,
    allReadings: []
};

function editReading(readingId, meterType, readingDate, readingValue) {
    currentEditingReading.id = readingId;
    currentEditingReading.meterType = meterType;
    currentEditingReading.originalValue = readingValue;
    currentEditingReading.originalDate = readingDate;
    
    // Store all readings for this meter for validation
    const meterData = @json($meterData);
    const readings = meterType === 'water' 
        ? (meterData.water?.readings || [])
        : (meterData.electricity?.readings || []);
    currentEditingReading.allReadings = readings;
    
    // Show/hide appropriate fields based on meter type
    const waterFields = document.getElementById('editWaterFields');
    const electricityFields = document.getElementById('editElectricityFields');
    
    if (meterType === 'water') {
        waterFields.style.display = 'block';
        electricityFields.style.display = 'none';
        
        // Parse reading value: convert total liters to kiloliters and liters
        const totalLiters = parseFloat(readingValue) || 0;
        const kiloliters = Math.floor(totalLiters / 1000);
        const liters = Math.floor((totalLiters % 1000) / 10); // Convert to 2-digit liters (0-99)
        
        document.getElementById('editWaterKiloliters').value = kiloliters.toString().padStart(5, '0');
        document.getElementById('editWaterLiters').value = liters.toString().padStart(2, '0');
        
        // Add input formatting for water fields
        const klInput = document.getElementById('editWaterKiloliters');
        const lInput = document.getElementById('editWaterLiters');
        
        // Remove any existing event listeners by cloning
        const klInputNew = klInput.cloneNode(true);
        klInput.parentNode.replaceChild(klInputNew, klInput);
        const lInputNew = lInput.cloneNode(true);
        lInput.parentNode.replaceChild(lInputNew, lInput);
        
        // Format kiloliters input (5 digits, numeric only)
        // During typing: only clean non-numeric, don't pad
        klInputNew.addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^0-9]/g, '').slice(0, 5);
            e.target.value = value; // Allow empty or partial values during typing
        });
        
        // On blur: pad with zeros
        klInputNew.addEventListener('blur', function(e) {
            let value = e.target.value.replace(/[^0-9]/g, '');
            if (value === '') {
                value = '0';
            }
            e.target.value = value.padStart(5, '0');
        });
        
        // Format liters input (2 digits, numeric only)
        // During typing: only clean non-numeric, don't pad
        lInputNew.addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^0-9]/g, '').slice(0, 2);
            e.target.value = value; // Allow empty or partial values during typing
        });
        
        // On blur: pad with zeros
        lInputNew.addEventListener('blur', function(e) {
            let value = e.target.value.replace(/[^0-9]/g, '');
            if (value === '') {
                value = '0';
            }
            e.target.value = value.padStart(2, '0');
        });
    } else {
        waterFields.style.display = 'none';
        electricityFields.style.display = 'block';
        document.getElementById('editElectricityValue').value = readingValue;
    }
    
    // Populate common fields
    document.getElementById('editReadingDate').value = readingDate;
    document.getElementById('editReadingReason').value = '';
    
    // Show modal
    document.getElementById('editReadingModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editReadingModal').style.display = 'none';
    currentEditingReading = {
        id: null,
        meterType: null,
        originalValue: null,
        originalDate: null,
        allReadings: []
    };
}

function saveEditedReading() {
    const date = document.getElementById('editReadingDate').value;
    const reason = document.getElementById('editReadingReason').value.trim();
    
    // Get value based on meter type
    let value = '';
    let numValue = 0;
    
    if (currentEditingReading.meterType === 'water') {
        // Get values and ensure they're padded for parsing
        let klValue = document.getElementById('editWaterKiloliters').value.replace(/[^0-9]/g, '');
        let lValue = document.getElementById('editWaterLiters').value.replace(/[^0-9]/g, '');
        
        // If empty, default to 0
        if (klValue === '') klValue = '0';
        if (lValue === '') lValue = '0';
        
        const kl = parseInt(klValue) || 0;
        const l = parseInt(lValue) || 0;
        numValue = (kl * 1000) + (l * 10); // Convert to total liters
        value = numValue.toString();
    } else {
        value = document.getElementById('editElectricityValue').value.trim();
        numValue = parseFloat(value);
    }
    
    // Basic validation
    if (!value) {
        alert('Please enter a reading value.');
        return;
    }
    
    if (isNaN(numValue) || numValue < 0) {
        alert('Please enter a valid positive number for the reading value.');
        return;
    }
    
    if (!reason || reason.length < 5) {
        alert('Please provide a reason of at least 5 characters.');
        return;
    }
    
    // Helper function to normalize date strings
    function normalizeDate(dateStr) {
        if (!dateStr) return '';
        if (dateStr instanceof Date) return dateStr.toISOString().split('T')[0];
        // Handle formats like "24 Dec 2025" or "2025-12-24"
        if (dateStr.includes(' ')) {
            // Parse "24 Dec 2025" format
            const parts = dateStr.split(' ');
            if (parts.length === 3) {
                const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                const monthIndex = months.indexOf(parts[1]);
                if (monthIndex !== -1) {
                    const day = parts[0].padStart(2, '0');
                    const month = String(monthIndex + 1).padStart(2, '0');
                    const year = parts[2];
                    return `${year}-${month}-${day}`;
                }
            }
        }
        // Assume it's already in YYYY-MM-DD format
        return dateStr.split(' ')[0].split('T')[0];
    }
    
    // VALIDATION RULE 1: If date is changed, it cannot be the same as an existing date
    if (date !== currentEditingReading.originalDate) {
        const existingReading = currentEditingReading.allReadings.find(r => {
            const readingDate = normalizeDate(r.date);
            return readingDate === date && r.id !== currentEditingReading.id;
        });
        
        if (existingReading) {
            alert('A reading with this date already exists. Please delete the existing reading first or choose a different date.');
            return;
        }
    }
    
    // VALIDATION RULE 2: If date remains the same, value must be between previous and next readings
    if (date === currentEditingReading.originalDate) {
        // Sort readings by date
        const sortedReadings = [...currentEditingReading.allReadings].sort((a, b) => {
            const dateA = normalizeDate(a.date);
            const dateB = normalizeDate(b.date);
            return dateA.localeCompare(dateB);
        });
        
        // Find current reading index
        const currentIndex = sortedReadings.findIndex(r => r.id === currentEditingReading.id);
        
        // Get previous reading (earlier date)
        if (currentIndex > 0) {
            const prevReading = sortedReadings[currentIndex - 1];
            const prevValue = parseFloat(prevReading.value || 0);
            if (numValue < prevValue) {
                const prevDate = prevReading.date.includes(' ') ? prevReading.date : normalizeDate(prevReading.date);
                alert('Reading value cannot be lower than the previous reading value (' + prevValue + ' on ' + prevDate + ')');
                return;
            }
        }
        
        // Get next reading (later date)
        if (currentIndex < sortedReadings.length - 1) {
            const nextReading = sortedReadings[currentIndex + 1];
            const nextValue = parseFloat(nextReading.value || 0);
            if (numValue > nextValue) {
                const nextDate = nextReading.date.includes(' ') ? nextReading.date : normalizeDate(nextReading.date);
                alert('Reading value cannot be higher than the next reading value (' + nextValue + ' on ' + nextDate + ')');
                return;
            }
        }
    }
    
    // Get CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                     document.querySelector('input[name="_token"]')?.value;
    
    // Prepare request body
    const requestBody = {
        value: value,
        reading_date: date,
        reason: reason
    };
    
    // Show loading state
    const saveBtn = document.getElementById('saveEditReadingBtn');
    const originalText = saveBtn.textContent;
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';
    
    fetch(`/api/v1/admin/readings/${currentEditingReading.id}/edit`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
            'Authorization': 'Bearer ' + (localStorage.getItem('auth_token') || '')
        },
        body: JSON.stringify(requestBody)
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === true || data.code === 200) {
            alert('Reading updated successfully' + (data.regenerated_bills_count > 0 ? ` (${data.regenerated_bills_count} bill(s) recalculated)` : ''));
            closeEditModal();
            // Reload page to show updated reading
            window.location.reload();
        } else {
            alert('Failed to update reading: ' + (data.msg || 'Unknown error'));
            saveBtn.disabled = false;
            saveBtn.textContent = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update reading: ' + error.message);
        saveBtn.disabled = false;
        saveBtn.textContent = originalText;
    });
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editReadingModal');
    if (event.target === modal) {
        closeEditModal();
    }
}
</script>

<!-- Edit Reading Modal -->
<div id="editReadingModal" class="edit-reading-modal" style="display: none;">
    <div class="edit-reading-modal-content">
        <div class="edit-reading-modal-header">
            <h2>Edit Meter Reading</h2>
            <span class="edit-reading-modal-close" onclick="closeEditModal()">&times;</span>
        </div>
        <div class="edit-reading-modal-body">
            <!-- Water Reading Fields (Kiloliters and Liters) -->
            <div id="editWaterFields" style="display: none;">
                <div class="form-group">
                    <label>Reading Value (Water):</label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="flex: 1;">
                            <label for="editWaterKiloliters" style="font-size: 12px; color: #666;">Kiloliters (00000-99)</label>
                            <input type="text" id="editWaterKiloliters" class="form-control" 
                                   pattern="[0-9]*" inputmode="numeric" maxlength="5" 
                                   placeholder="00000" style="text-align: center; font-family: monospace; font-size: 16px;">
                        </div>
                        <span style="font-size: 20px; font-weight: bold; color: #333; margin-top: 25px;">-</span>
                        <div style="flex: 1;">
                            <label for="editWaterLiters" style="font-size: 12px; color: #666;">Liters (00-99)</label>
                            <input type="text" id="editWaterLiters" class="form-control" 
                                   pattern="[0-9]*" inputmode="numeric" maxlength="2" 
                                   placeholder="00" style="text-align: center; font-family: monospace; font-size: 16px;">
                        </div>
                    </div>
                    <div style="margin-top: 5px; font-size: 11px; color: #666;">
                        Format: 00020-55 means 20 kiloliters and 55 liters
                    </div>
                </div>
            </div>
            
            <!-- Electricity Reading Field -->
            <div id="editElectricityFields" style="display: none;">
                <div class="form-group">
                    <label for="editElectricityValue">Reading Value (Electricity - kWh):</label>
                    <input type="number" id="editElectricityValue" class="form-control" 
                           step="0.01" min="0" placeholder="Enter reading value">
                </div>
            </div>
            
            <div class="form-group">
                <label for="editReadingDate">Reading Date:</label>
                <input type="date" id="editReadingDate" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="editReadingReason">Reason for Edit (minimum 5 characters):</label>
                <textarea id="editReadingReason" class="form-control" rows="3" required minlength="5"></textarea>
            </div>
        </div>
        <div class="edit-reading-modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
            <button type="button" class="btn btn-primary" id="saveEditReadingBtn" onclick="saveEditedReading()">Save</button>
        </div>
    </div>
</div>

<style>
.reading-row {
    display: flex;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e0e0e0;
    gap: 12px;
}

.reading-label {
    flex: 0 0 auto;
    min-width: 100px;
    color: #333;
    font-size: 14px;
}

.reading-edit {
    flex: 0 0 auto;
    margin-left: auto;
    margin-right: 8px;
}

.reading-delete {
    flex: 0 0 auto;
    margin-right: 12px;
}

.reading-value {
    flex: 0 0 auto;
    text-align: right;
    color: #3294B8;
    font-weight: 600;
    font-size: 14px;
    min-width: 120px;
}

.delete-reading-btn {
    background: none;
    border: none;
    color: #dc3545;
    cursor: pointer;
    padding: 4px 8px;
    font-size: 14px;
    transition: color 0.2s;
}

.delete-reading-btn:hover {
    color: #c82333;
}

.delete-reading-btn:focus {
    outline: none;
}

.delete-reading-btn i {
    font-size: 16px;
}

.edit-reading-btn {
    background: none;
    border: none;
    color: #3294B8;
    cursor: pointer;
    padding: 4px 8px;
    font-size: 14px;
    transition: color 0.2s;
}

.edit-reading-btn:hover {
    color: #267a9e;
}

.edit-reading-btn:focus {
    outline: none;
}

.edit-reading-btn i {
    font-size: 16px;
}

/* Edit Reading Modal */
.edit-reading-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.5);
    align-items: center;
    justify-content: center;
}

.edit-reading-modal-content {
    background-color: #fefefe;
    margin: auto;
    padding: 0;
    border: 1px solid #888;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.edit-reading-modal-header {
    padding: 20px;
    background-color: #3294B8;
    color: white;
    border-radius: 8px 8px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.edit-reading-modal-header h2 {
    margin: 0;
    font-size: 20px;
}

.edit-reading-modal-close {
    color: white;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    line-height: 20px;
}

.edit-reading-modal-close:hover,
.edit-reading-modal-close:focus {
    opacity: 0.7;
}

.edit-reading-modal-body {
    padding: 20px;
}

.edit-reading-modal-body .form-group {
    margin-bottom: 15px;
}

.edit-reading-modal-body label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #333;
}

.edit-reading-modal-body .form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    box-sizing: border-box;
}

.edit-reading-modal-body textarea.form-control {
    resize: vertical;
    min-height: 80px;
}

.edit-reading-modal-body input[type="text"][pattern="[0-9]*"] {
    font-family: 'Courier New', monospace;
    font-size: 16px;
    letter-spacing: 2px;
}

.edit-reading-modal-body #editWaterKiloliters,
.edit-reading-modal-body #editWaterLiters {
    text-align: center;
}

.edit-reading-modal-footer {
    padding: 15px 20px;
    background-color: #f8f9fa;
    border-top: 1px solid #ddd;
    border-radius: 0 0 8px 8px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.edit-reading-modal-footer .btn {
    padding: 8px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: background-color 0.2s;
}

.edit-reading-modal-footer .btn-secondary {
    background-color: #6c757d;
    color: white;
}

.edit-reading-modal-footer .btn-secondary:hover {
    background-color: #5a6268;
}

.edit-reading-modal-footer .btn-primary {
    background-color: #3294B8;
    color: white;
}

.edit-reading-modal-footer .btn-primary:hover {
    background-color: #267a9e;
}

.edit-reading-modal-footer .btn-primary:disabled {
    background-color: #ccc;
    cursor: not-allowed;
}
</style>
@endsection




