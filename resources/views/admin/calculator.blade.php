@extends('admin.layouts.main')

@section('title', 'Billing Calculator - Clean Implementation')

@section('page-level-styles')
<style>
    /* Mode Toggle Styles */
    .mode-toggle {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }
    .mode-btn {
        padding: 10px 20px;
        border: 2px solid #007bff;
        background: white;
        cursor: pointer;
        border-radius: 5px;
    }
    .mode-btn.active {
        background: #007bff;
        color: white;
    }
    .mode-panel {
        display: none;
    }
    .mode-panel.active {
        display: block;
    }
    
    /* Console Log Panel */
    .console-panel {
        background: #1e1e1e;
        color: #d4d4d4;
        padding: 15px;
        border-radius: 5px;
        font-family: monospace;
        font-size: 12px;
        max-height: 300px;
        overflow-y: auto;
        margin-top: 20px;
    }
    .console-log { color: #d4d4d4; }
    .console-info { color: #3794ff; }
    .console-warn { color: #cca700; }
    .console-error { color: #f48771; }
    .console-success { color: #89d185; }
    
    /* Form Styles */
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
    .form-control { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
    
    /* Period Table */
    .period-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    .period-table th, .period-table td {
        border: 1px solid #ddd;
        padding: 8px;
        text-align: left;
    }
    .period-table th { background: #f5f5f5; }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <h1>Billing Calculator <small class="text-muted">(Clean Implementation)</small></h1>
    
    <!-- ============================================ -->
    <!-- MODE TOGGLE -->
    <!-- ============================================ -->
    <div class="mode-toggle">
        <button class="mode-btn active" data-mode="test" id="btn-test-mode">Test Mode</button>
        <button class="mode-btn" data-mode="user" id="btn-user-mode">User Mode</button>
    </div>
    
    <!-- ============================================ -->
    <!-- TEST MODE PANEL -->
    <!-- ============================================ -->
    <div class="mode-panel active" id="panel-test-mode">
        <div class="row">
            <div class="col-md-6">
                <h3>Tariff Selection</h3>
                <div class="form-group">
                    <label for="tariff-template">Tariff Template:</label>
                    <select class="form-control" id="tariff-template">
                        <option value="">-- Select Tariff --</option>
                    </select>
                </div>
                
                <h3>Period Configuration</h3>
                <div class="form-group">
                    <label for="period-start">Period Start Date:</label>
                    <input type="date" class="form-control" id="period-start">
                </div>
                <div class="form-group">
                    <label for="period-end">Period End Date:</label>
                    <input type="date" class="form-control" id="period-end">
                </div>
                <div class="form-group">
                    <label for="billing-day">Billing Day:</label>
                    <input type="number" class="form-control" id="billing-day" min="1" max="31" value="1">
                </div>
            </div>
            
            <div class="col-md-6">
                <h3>Start Reading (Genesis Anchor)</h3>
                <div class="form-group">
                    <label for="start-reading">Start Reading (Litres):</label>
                    <input type="number" class="form-control" id="start-reading" min="0" value="0">
                </div>
                <div class="form-group">
                    <label for="start-reading-date">Start Reading Date:</label>
                    <input type="date" class="form-control" id="start-reading-date">
                </div>
                
                <h3>Readings</h3>
                <div id="readings-container">
                    <!-- Readings will be added here dynamically -->
                </div>
                <button type="button" class="btn btn-secondary" id="add-reading-btn">+ Add Reading</button>
            </div>
        </div>
        
        <hr>
        
        <div class="row">
            <div class="col-md-12">
                <button type="button" class="btn btn-primary" id="calculate-btn">Calculate</button>
                <button type="button" class="btn btn-info" id="add-period-btn">+ Add Period</button>
            </div>
        </div>
        
        <!-- Periods Table -->
        <h3>Periods</h3>
        <table class="period-table" id="periods-table">
            <thead>
                <tr>
                    <th>Period</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Opening Reading</th>
                    <th>Closing Reading</th>
                    <th>Usage (L)</th>
                    <th>Daily Rate</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="periods-tbody">
                <!-- Periods will be rendered here -->
            </tbody>
        </table>
    </div>
    
    <!-- ============================================ -->
    <!-- USER MODE PANEL -->
    <!-- ============================================ -->
    <div class="mode-panel" id="panel-user-mode">
        <div class="row">
            <div class="col-md-6">
                <h3>User Selection</h3>
                <div class="form-group">
                    <label for="user-search">Search User:</label>
                    <input type="text" class="form-control" id="user-search" placeholder="Enter email or name...">
                </div>
                <div class="form-group">
                    <label for="user-results">Results:</label>
                    <select class="form-control" id="user-results" size="5">
                        <!-- User results will appear here -->
                    </select>
                </div>
            </div>
            
            <div class="col-md-6">
                <h3>Account Details</h3>
                <div id="account-details">
                    <p class="text-muted">Select a user to view account details</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ============================================ -->
    <!-- CONSOLE OUTPUT (Watchdog) -->
    <!-- ============================================ -->
    <h3>Console (Watchdog Monitor)</h3>
    <div class="console-panel" id="console-panel">
        <div class="console-info">[INIT] Calculator loaded. Monitor active.</div>
    </div>
</div>

<!-- Load the clean JS implementation -->
<script src="{{ asset('js/calculator.js') }}"></script>
<!-- Load the watchdog monitor (separate) -->
<script src="{{ asset('js/calculator-monitor.js') }}"></script>
@endsection