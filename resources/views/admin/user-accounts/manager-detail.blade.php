@extends('admin.layouts.main')
@section('title', 'User Accounts - Manager')

@section('page-level-styles')
<style>
/* Billing Calculator CSS - Identical to billing-calculator.blade.php */
:root{
  --bg:#f6f7f9;
  --card:#ffffff;
  --text:#111827;
  --muted:#6b7280;
  --border:#e5e7eb;
  --green:#16a34a;
  --amber:#f59e0b;
  --blue:#2563eb;
  --red:#dc2626;
  --gray:#e5e7eb;
  --green-light:#dcfce7;
  --blue-light:#dbeafe;
  --amber-light:#fef3c7;
  --purple-light:#f3e8f7;
  --teal-light:#e6f5f3;
}

/* SECTIONS - Identical to billing calculator */
.billing-section{
  background:var(--card);
  border:1px solid var(--border);
  border-radius:12px;
  margin-bottom:20px;
  overflow:hidden;
}
.billing-section-header{
  padding:14px 18px;
  font-size:18px;
  font-weight:700;
  cursor:pointer;
  background:#f9fafb;
  display:flex;
  align-items:center;
  justify-content:space-between;
}
.billing-section-header::after{
  content:'▼';
  font-size:12px;
  color:var(--muted);
  transition:transform 0.3s ease;
}
.billing-section.collapsed .billing-section-header::after{
  transform:rotate(-90deg);
}
.billing-section-content{ 
  padding:18px;
}
.billing-section.collapsed .billing-section-content{ 
  display:none;
}

/* TABLES */
.billing-table{ width:100%; border-collapse:collapse }
.billing-table th{
  text-align:left;
  font-size:14px;
  color:var(--muted);
  font-weight:700;
  padding:10px 8px;
  border-bottom:1px solid var(--border);
}
.billing-table td{
  padding:12px 8px;
  border-bottom:1px solid var(--border);
  font-size:16px;
}
.billing-table tbody tr.active{ background:#eef2ff }
.billing-table tbody tr:hover{ background:#f9fafb }

/* BADGES */
.badge{
  padding:4px 12px;
  border-radius:999px;
  font-size:14px;
  font-weight:700;
}
.CALCULATED{ background:#dcfce7; color:#166534 }
.PROVISIONAL{ background:#fef3c7; color:#92400e }
.ACTUAL{ background:#e9d5ff; color:#6b21a8 }

/* INPUTS */
.billing-input, .billing-select{
  padding:8px 10px;
  font-size:16px;
  border-radius:8px;
  border:1px solid var(--border);
}
.input-date{ width:150px }
.input-reading{
  width:140px;
  text-align:right;
  font-family:ui-monospace,monospace;
  font-size:18px;
  font-weight:600;
  padding:10px 12px;
}
input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
  -webkit-appearance: none;
  appearance: none;
  margin: 0;
}
input[type="number"] {
  -moz-appearance: textfield;
  appearance: textfield;
}

/* BUTTONS */
.billing-btn{
  border:none;
  cursor:pointer;
  font-weight:700;
  border-radius:8px;
  transition:all 0.2s ease;
}
.btn-primary{
  background:var(--green);
  color:#fff;
  font-size:20px;
  padding:16px 42px;
}
.btn-primary:hover{
  background:#15803d;
  transform:translateY(-1px);
  box-shadow:0 4px 8px rgba(0,0,0,0.1);
}
.btn-secondary{
  background:#e5e7eb;
  color:#374151;
  padding:10px 16px;
  font-size:15px;
}
.btn-secondary:hover{
  background:#d1d5db;
}
.btn-tertiary{
  background:var(--gray);
  color:#000;
  padding:8px 14px;
  font-size:14px;
}
.btn-tertiary:hover{
  background:#d1d5db;
}
.btn-calculate {
  background:var(--green);
  color:white;
  padding:16px 42px;
  font-size:20px;
  font-weight:700;
  display:block;
  margin:30px auto;
  width:fit-content;
  min-width:200px;
}
.btn-calculate:hover{
  background:#15803d;
  transform:translateY(-1px);
  box-shadow:0 4px 8px rgba(0,0,0,0.1);
}

/* ACTION ROW */
.action-row{
  display:flex;
  gap:12px;
  flex-wrap:wrap;
}

/* MODE SELECTOR */
.mode-selector{
  display:flex;
  flex-direction:column;
  gap:8px;
  margin-bottom:20px;
  padding:12px;
  background:#f9fafb;
  border-radius:8px;
}
.mode-tab{
  width:100%;
  padding:12px 16px;
  border:none;
  background:#e5e7eb;
  color:#374151;
  font-size:14px;
  font-weight:600;
  cursor:pointer;
  border-radius:6px;
  transition:all 0.2s ease;
  text-align:center;
}
.mode-tab:hover{
  background:#d1d5db;
}
.mode-tab.active{
  background:var(--blue);
  color:#fff;
}
.mode-tab.active:hover{
  background:#1d4ed8;
}

/* REVISION HISTORY */
.revision-section{
  background:#f9fafb;
  border:1px solid var(--border);
  border-radius:8px;
  padding:12px;
  max-height:200px;
  overflow-y:auto;
}
.revision-item{
  padding:8px;
  margin-bottom:6px;
  background:white;
  border-left:3px solid var(--blue);
  border-radius:4px;
  font-size:12px;
}

/* TABS */
.tab-navigation {
  border-bottom: 2px solid #dee2e6;
  margin-bottom: 20px;
}
.tab-button {
  display: inline-block;
  padding: 12px 24px;
  margin-right: 4px;
  background: #f8f9fa;
  border: none;
  border-bottom: 3px solid transparent;
  cursor: pointer;
  font-weight: 600;
  color: #495057;
  transition: all 0.2s;
}
.tab-button:hover {
  background: #e9ecef;
  color: #212529;
}
.tab-button.active {
  background: #fff;
  color: #2563eb;
  border-bottom-color: #2563eb;
}
.tab-content {
  display: none;
}
.tab-content.active {
  display: block;
}

/* User/Account Selection */
.user-account-selector {
  background: #fff;
  border: 1px solid #dee2e6;
  border-radius: 8px;
  padding: 20px;
  margin-bottom: 20px;
}
.search-container {
  display: flex;
  gap: 12px;
  margin-bottom: 16px;
}
.search-input {
  flex: 1;
  padding: 10px 16px;
  border: 1px solid #dee2e6;
  border-radius: 6px;
  font-size: 16px;
}
.search-button {
  padding: 10px 24px;
  background: #2563eb;
  color: #fff;
  border: none;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
}
.search-button:hover {
  background: #1d4ed8;
}
.selected-info {
  padding: 16px;
  background: #f8f9fa;
  border-radius: 6px;
  margin-top: 16px;
}
.selected-info h4 {
  margin: 0 0 8px 0;
  font-size: 18px;
  font-weight: 700;
}
.selected-info p {
  margin: 4px 0;
  color: #6c757d;
}
</style>
@endsection

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-2 custom-text-heading">User Accounts - Manager</h1>
    
    <!-- User/Account Selection -->
    <div class="user-account-selector">
        <h3 style="margin-top:0; margin-bottom:16px;">Select User & Account</h3>
        <div class="search-container">
            <input 
                type="text" 
                id="user_search_input" 
                class="search-input" 
                placeholder="Search by email, phone, name, or account number..."
                onkeypress="if(event.key==='Enter') searchUsers()"
            >
            <button class="search-button" onclick="searchUsers()">Search</button>
        </div>
        
        <div id="user_search_results" style="display:none; margin-top:16px;">
            <div style="font-weight:700; margin-bottom:12px;">Search Results:</div>
            <div id="user_results_list" style="max-height:300px; overflow-y:auto; border:1px solid #dee2e6; border-radius:6px;">
                <!-- Populated by JavaScript -->
            </div>
        </div>
        
        <div id="selected_user_display" style="display:none;" class="selected-info">
            <h4 id="selected_user_name">—</h4>
            <p id="selected_user_details">—</p>
            
            <div id="account_selection" style="margin-top:16px; display:none;">
                <label style="font-weight:600; margin-bottom:8px; display:block;">Select Account:</label>
                <select id="account_select" class="billing-select" style="width:100%; max-width:400px;" onchange="selectAccountFromDropdown()">
                    <option value="">-- Select Account --</option>
                </select>
            </div>
            
            <div id="selected_account_display" style="margin-top:16px; padding:12px; background:#e7f3ff; border-radius:6px; display:none;">
                <strong>Selected Account:</strong> <span id="selected_account_name">—</span>
            </div>
        </div>
    </div>
    
    <!-- Tabs -->
    <div class="tab-navigation">
        <button class="tab-button" onclick="switchTab('user-details')">User Details</button>
        <button class="tab-button" onclick="switchTab('accounts-meters')">Accounts & Meters</button>
        <button class="tab-button" onclick="switchTab('edit-account')">Edit Account</button>
        <button class="tab-button active" onclick="switchTab('period-readings')">Period and Readings</button>
        <button class="tab-button" onclick="switchTab('billing-payments')">Billing & Payments</button>
    </div>
    
    <!-- Tab Contents -->
    <!-- User Details Tab -->
    <div id="tab-user-details" class="tab-content">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">User Details</h6>
            </div>
            <div class="card-body">
                <div id="user_details_content">
                    <p class="text-muted">Please select a user to view details.</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Accounts & Meters Tab -->
    <div id="tab-accounts-meters" class="tab-content">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Accounts & Meters</h6>
            </div>
            <div class="card-body">
                <div id="accounts_meters_content">
                    <p class="text-muted">Please select a user to view accounts and meters.</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Account Tab -->
    <div id="tab-edit-account" class="tab-content">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Edit Account</h6>
            </div>
            <div class="card-body">
                <div id="edit_account_content">
                    <p class="text-muted">Please select an account to edit.</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Period and Readings Tab - Embedded Billing Calculator UI -->
    <div id="tab-period-readings" class="tab-content active">
        <!-- Billing Mode Selector -->
        <div class="mode-selector" style="margin-bottom:20px;">
            <button class="mode-tab active" data-mode="period" onclick="switchBillingMode('period')">Period to Period</button>
            <button class="mode-tab" data-mode="sector" onclick="switchBillingMode('sector')">Date to Date</button>
        </div>
        
        <!-- PERIOD MODE CONTAINER (Identical to billing calculator) -->
        <div id="period-mode-container">
            <!-- 1️⃣ TARIFF TEMPLATE -->
            <div class="billing-section">
                <div class="billing-section-header" onclick="this.parentElement.classList.toggle('collapsed')">
                    1️⃣ Tariff Template
                </div>
                <div class="billing-section-content">
                    <div style="margin-bottom:16px;">
                        <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
                            <label style="flex:1; font-weight:600; color:var(--text);">Select Tariff Template: <span style="color:var(--red);">*</span></label>
                            <button id="reset_template_btn" onclick="resetTariffTemplate()" style="padding:6px 12px; font-size:14px; background:var(--red); color:#fff; border:none; border-radius:6px; cursor:pointer; display:none;">Reset</button>
                        </div>
                        <select id="tariff_template_select" class="billing-select" style="width:100%;" required>
                            <option value="">-- Please Select a Tariff Template --</option>
                        </select>
                        <div id="tariff_template_error" style="color:var(--red); font-size:14px; margin-top:8px; display:none;">Please select a tariff template to continue.</div>
                    </div>
                    
                    <!-- Tariff Details Display -->
                    <div id="tariff_details_display" style="display:none;">
                        <div style="background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:16px; margin-top:16px;">
                            <div style="font-size:18px; font-weight:700; color:var(--text); margin-bottom:12px;" id="tariff_template_name_display">—</div>
                            <div style="font-size:14px; color:var(--muted); margin-bottom:16px;">
                                <span id="tariff_billing_type_display">Billing Type: —</span> | 
                                <span id="tariff_billing_day_display">Billing Day: —</span> | 
                                <span id="tariff_vat_rate_display">VAT: —%</span>
                            </div>
                            
                            <!-- Collapsible Tariff Charges -->
                            <div class="billing-section collapsed" style="margin-top:16px; margin-bottom:0;">
                                <div class="billing-section-header" onclick="this.parentElement.classList.toggle('collapsed')" style="font-size:16px;">
                                    📋 View Complete Tariff Charges
                                </div>
                                <div class="billing-section-content">
                                    <!-- Tiers -->
                                    <div style="margin-bottom:20px;">
                                        <div style="font-size:16px; font-weight:700; color:var(--text); margin-bottom:12px;">Water Tiers</div>
                                        <table class="billing-table">
                                            <thead>
                                                <tr style="background:var(--bg);">
                                                    <th style="text-align:left;">Tier</th>
                                                    <th style="text-align:right;">Max (L)</th>
                                                    <th style="text-align:right;">Rate (R/kL)</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tariff_tiers_display">
                                                <tr><td colspan="3" style="text-align:center; padding:12px; color:var(--muted);">No tiers available</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Fixed Costs -->
                                    <div style="margin-bottom:20px;">
                                        <div style="font-size:16px; font-weight:700; color:var(--text); margin-bottom:12px;">Fixed Costs</div>
                                        <table class="billing-table">
                                            <tbody id="tariff_fixed_costs_display">
                                                <tr><td colspan="2" style="text-align:center; padding:12px; color:var(--muted);">No fixed costs</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Customer Costs -->
                                    <div style="margin-bottom:20px;">
                                        <div style="font-size:16px; font-weight:700; color:var(--text); margin-bottom:12px;">Customer Costs</div>
                                        <table class="billing-table">
                                            <tbody id="tariff_customer_costs_display">
                                                <tr><td colspan="2" style="text-align:center; padding:12px; color:var(--muted);">No customer costs</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Additional Charges -->
                                    <div style="margin-bottom:20px;">
                                        <div style="font-size:16px; font-weight:700; color:var(--text); margin-bottom:12px;">Additional Charges</div>
                                        <div id="tariff_additional_charges_display" style="color:var(--muted);">No additional charges</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2️⃣ PERIOD GENERATOR -->
            <div class="billing-section">
                <div class="billing-section-header">2️⃣ Period Generator</div>
                <div class="billing-section-content">
                    Bill Day:
                    <select id="bill_day" class="input-date billing-select">
                        <option>1</option>
                        <option>10</option>
                        <option selected>20</option>
                    </select>
                    &nbsp; Start Month:
                    <input type="month" id="start_month" class="input-date billing-input" value="2026-01">
                    &nbsp;
                    <button onclick="add_period()" class="btn-secondary">Add Period</button>
                </div>
            </div>

            <!-- 3️⃣ PERIODS & READINGS -->
            <div class="billing-section">
                <div class="billing-section-header">3️⃣ Periods and Readings</div>
                <div class="billing-section-content">
                    <table id="period_table" class="billing-table">
                        <thead>
                            <tr>
                                <th>#</th><th>Billing Period</th><th>Status</th><th>Period_Total_Usage (L)</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Populated by JavaScript -->
                        </tbody>
                    </table>

                    <h3 style="margin-top:20px; font-size:16px; font-weight:700;">Meter Readings (active period)</h3>

                    <table id="reading_table" class="billing-table">
                        <tr>
                            <th>Date</th><th>Reading (L)</th><th></th>
                        </tr>
                        <!-- Populated by JavaScript -->
                    </table>

                    <div class="action-row" style="margin-top:12px;">
                        <button onclick="add_reading()" class="btn-secondary">➕ Add Reading</button>
                    </div>
                </div>
            </div>

            <!-- CALCULATE -->
            <div style="text-align:center; margin:30px 0;">
                <button onclick="calculate()" class="btn-calculate">Calculate</button>
            </div>

            <!-- 5️⃣ OUTPUT -->
            <div class="billing-section">
                <div class="billing-section-header">5️⃣ Calculation Output</div>
                <div class="billing-section-content">
                    <div class="action-row" style="margin-bottom:16px;">
                        <button id="copy_output" onclick="copy_output_to_clipboard()" class="btn-tertiary">📋 Copy Period</button>
                        <button id="copy_all_periods" onclick="copy_all_periods_to_clipboard()" class="btn-tertiary">📋 Copy All Periods</button>
                    </div>

                    <div id="output_container" style="max-width: 1100px; margin: auto; font-family: 'Inter', 'Segoe UI', Arial, sans-serif;"></div>
                    <div id="errors"></div>
                </div>
            </div>

            <!-- 7️⃣ INPUT HISTORY -->
            <div class="billing-section">
                <div class="billing-section-header">7️⃣ Input History</div>
                <div class="billing-section-content">
                    <div id="revision_history" class="revision-section"></div>
                    <div class="action-row" style="margin-top:12px;">
                        <button onclick="copy_input_history()" class="btn-tertiary">📋 Copy Input History</button>
                        <button onclick="clear_revision_history()" class="btn-tertiary">Clear History</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- END PERIOD MODE CONTAINER -->

        <!-- SECTOR MODE CONTAINER (Identical to billing calculator) -->
        <div id="sector-mode-container" style="display:none;">
            <!-- 1️⃣ SECTOR TIERS -->
            <div class="billing-section collapsed">
                <div class="billing-section-header" onclick="this.parentElement.classList.toggle('collapsed')">
                    1️⃣ Tariff Template
                </div>
                <div class="billing-section-content">
                    <div style="margin-bottom:16px;">
                        <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
                            <label style="flex:1; font-weight:600; color:var(--text);">Select Tariff Template: <span style="color:var(--red);">*</span></label>
                            <button id="sector_reset_template_btn" onclick="resetTariffTemplate()" style="padding:6px 12px; font-size:14px; background:var(--red); color:#fff; border:none; border-radius:6px; cursor:pointer; display:none;">Reset</button>
                        </div>
                        <select id="sector_tariff_template_select" class="billing-select" style="width:100%;" required>
                            <option value="">-- Please Select a Tariff Template --</option>
                        </select>
                        <div id="sector_tariff_template_error" style="color:var(--red); font-size:14px; margin-top:8px; display:none;">Please select a tariff template to continue.</div>
                    </div>
                    
                    <!-- Tariff Details Display (same structure as period mode) -->
                    <div id="sector_tariff_details_display" style="display:none;">
                        <div style="background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:16px; margin-top:16px;">
                            <div style="font-size:18px; font-weight:700; color:var(--text); margin-bottom:12px;" id="sector_tariff_template_name_display">—</div>
                            <div style="font-size:14px; color:var(--muted); margin-bottom:16px;">
                                <span id="sector_tariff_billing_type_display">Billing Type: —</span> | 
                                <span id="sector_tariff_billing_day_display">Billing Day: —</span> | 
                                <span id="sector_tariff_vat_rate_display">VAT: —%</span>
                            </div>
                            
                            <!-- Collapsible Tariff Charges -->
                            <div class="billing-section collapsed" style="margin-top:16px; margin-bottom:0;">
                                <div class="billing-section-header" onclick="this.parentElement.classList.toggle('collapsed')" style="font-size:16px;">
                                    📋 View Complete Tariff Charges
                                </div>
                                <div class="billing-section-content">
                                    <!-- Tiers -->
                                    <div style="margin-bottom:20px;">
                                        <div style="font-size:16px; font-weight:700; color:var(--text); margin-bottom:12px;">Water Tiers</div>
                                        <table class="billing-table">
                                            <thead>
                                                <tr style="background:var(--bg);">
                                                    <th style="text-align:left;">Tier</th>
                                                    <th style="text-align:right;">Max (L)</th>
                                                    <th style="text-align:right;">Rate (R/kL)</th>
                                                </tr>
                                            </thead>
                                            <tbody id="sector_tariff_tiers_display">
                                                <tr><td colspan="3" style="text-align:center; padding:12px; color:var(--muted);">No tiers available</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Fixed Costs -->
                                    <div style="margin-bottom:20px;">
                                        <div style="font-size:16px; font-weight:700; color:var(--text); margin-bottom:12px;">Fixed Costs</div>
                                        <table class="billing-table">
                                            <tbody id="sector_tariff_fixed_costs_display">
                                                <tr><td colspan="2" style="text-align:center; padding:12px; color:var(--muted);">No fixed costs</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Customer Costs -->
                                    <div style="margin-bottom:20px;">
                                        <div style="font-size:16px; font-weight:700; color:var(--text); margin-bottom:12px;">Customer Costs</div>
                                        <table class="billing-table">
                                            <tbody id="sector_tariff_customer_costs_display">
                                                <tr><td colspan="2" style="text-align:center; padding:12px; color:var(--muted);">No customer costs</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Additional Charges -->
                                    <div style="margin-bottom:20px;">
                                        <div style="font-size:16px; font-weight:700; color:var(--text); margin-bottom:12px;">Additional Charges</div>
                                        <div id="sector_tariff_additional_charges_display" style="color:var(--muted);">No additional charges</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2️⃣ DATE SELECTOR -->
            <div class="billing-section">
                <div class="billing-section-header">2️⃣ Date Selector</div>
                <div class="billing-section-content">
                    <label>Select Date:</label>
                    <input type="date" id="sector_date_picker" class="input-date billing-input" style="margin-top:8px;">
                </div>
            </div>

            <!-- 3️⃣ SECTORS & READINGS -->
            <div class="billing-section">
                <div class="billing-section-header">3️⃣ Sectors and Readings</div>
                <div class="billing-section-content">
                    <div id="periods_list" style="margin-bottom:20px;">
                        <!-- Populated by JavaScript - Period headers -->
                    </div>

                    <h3 style="margin-top:20px; font-size:16px; font-weight:700;">Meter Readings (active period)</h3>

                    <table id="sector_reading_table" class="billing-table">
                        <thead>
                            <tr>
                                <th>Date</th><th>Reading (L)</th><th>Difference (L)</th><th>Cost (R)</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Populated by JavaScript -->
                        </tbody>
                    </table>

                    <div id="sector_dashboard" style="margin-top:20px;">
                        <!-- Total Cost with Daily Stats (Blue Area) -->
                        <div style="background:var(--blue); border-radius:12px; padding:20px;">
                            <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
                                <div style="color:#fff; font-size:24px;">→</div>
                                <div style="flex:1;">
                                    <div style="font-size:14px; color:rgba(255,255,255,0.8); margin-bottom:4px;">Total Cost</div>
                                    <div style="font-size:32px; font-weight:700; color:#fff;" id="dashboard_total_cost">R 0.00</div>
                                </div>
                            </div>
                            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; margin-top:16px; padding-top:16px; border-top:1px solid rgba(255,255,255,0.2);">
                                <div>
                                    <div style="font-size:14px; color:rgba(255,255,255,0.8); margin-bottom:4px;">Daily Usage</div>
                                    <div style="font-size:24px; font-weight:700; color:#fff;" id="dashboard_daily_usage">—</div>
                                </div>
                                <div>
                                    <div style="font-size:14px; color:rgba(255,255,255,0.8); margin-bottom:4px;">Daily Cost</div>
                                    <div style="font-size:24px; font-weight:700; color:#fff;" id="dashboard_daily_cost">—</div>
                                </div>
                                <div>
                                    <div style="font-size:14px; color:rgba(255,255,255,0.8); margin-bottom:4px;">Usage Days</div>
                                    <div style="font-size:24px; font-weight:700; color:#fff;" id="dashboard_usage_days">—</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="action-row" style="margin-top:12px;">
                        <button onclick="add_sector_reading()" class="btn-secondary">➕ Add Reading</button>
                    </div>
                </div>
            </div>

            <!-- CALCULATE -->
            <div style="text-align:center; margin:30px 0;">
                <button onclick="calculate_sector()" class="btn-calculate">Calculate Sector</button>
            </div>

            <!-- 5️⃣ OUTPUT -->
            <div class="billing-section">
                <div class="billing-section-header">5️⃣ Sector Calculation Output</div>
                <div class="billing-section-content">
                    <div class="action-row" style="margin-bottom:16px;">
                        <button id="copy_sector_output" onclick="copy_sector_output_to_clipboard()" class="btn-tertiary">📋 Copy Sector</button>
                        <button id="copy_all_sectors" onclick="copy_all_sectors_to_clipboard()" class="btn-tertiary">📋 Copy All Sectors</button>
                    </div>

                    <div id="sector_output_container" style="max-width: 1100px; margin: auto; font-family: 'Inter', 'Segoe UI', Arial, sans-serif;"></div>
                    <div id="sector_errors"></div>
                </div>
            </div>

            <!-- 7️⃣ INPUT HISTORY -->
            <div class="billing-section">
                <div class="billing-section-header">7️⃣ Sector Input History</div>
                <div class="billing-section-content">
                    <div id="sector_revision_history" class="revision-section"></div>
                    <div class="action-row" style="margin-top:12px;">
                        <button onclick="copy_sector_input_history()" class="btn-tertiary">📋 Copy Input History</button>
                        <button onclick="clear_sector_revision_history()" class="btn-tertiary">Clear History</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- END SECTOR MODE CONTAINER -->

        <!-- BILL PREVIEW CONTAINER (Identical to billing calculator) -->
        <div id="bill-preview-container" style="display:none;">
            <div class="billing-section">
                <div class="billing-section-header">Bill Preview</div>
                <div class="billing-section-content">
                    <!-- Bill Header -->
                    <div id="bill_header" style="margin-bottom:24px; padding-bottom:16px; border-bottom:2px solid var(--border);">
                        <div style="font-size:24px; font-weight:700; color:var(--text); margin-bottom:8px;" id="bill_template_name">No Template Selected</div>
                        <div style="font-size:14px; color:var(--muted);" id="bill_billing_type">Billing Type: —</div>
                        <div style="font-size:14px; color:var(--muted); margin-top:4px;" id="bill_period">Billing Period: —</div>
                    </div>

                    <!-- Usage Summary -->
                    <div id="bill_usage_section" style="margin-bottom:24px;">
                        <div style="font-size:18px; font-weight:700; color:var(--text); margin-bottom:12px;">Usage Summary</div>
                        <div style="background:var(--card); border:1px solid var(--border); border-radius:8px; padding:16px;">
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                                <div>
                                    <div style="font-size:14px; color:var(--muted); margin-bottom:4px;">Total Usage</div>
                                    <div style="font-size:20px; font-weight:700; color:var(--text);" id="bill_total_usage">— L</div>
                                </div>
                                <div>
                                    <div style="font-size:14px; color:var(--muted); margin-bottom:4px;">Daily Usage</div>
                                    <div style="font-size:20px; font-weight:700; color:var(--text);" id="bill_daily_usage">— L/day</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Consumption Charges -->
                    <div id="bill_tier_charges_section" style="margin-bottom:24px; display:none;">
                        <div style="font-size:18px; font-weight:700; color:var(--text); margin-bottom:12px;">Consumption Charges</div>
                        <table class="billing-table" style="width:100%;">
                            <thead>
                                <tr style="background:var(--bg);">
                                    <th style="text-align:left; padding:10px; border-bottom:2px solid var(--border);">Tier</th>
                                    <th style="text-align:right; padding:10px; border-bottom:2px solid var(--border);">Usage (L)</th>
                                    <th style="text-align:right; padding:10px; border-bottom:2px solid var(--border);">Rate (R/kL)</th>
                                    <th style="text-align:right; padding:10px; border-bottom:2px solid var(--border);">Charge (R)</th>
                                </tr>
                            </thead>
                            <tbody id="bill_tier_breakdown">
                                <tr><td colspan="4" style="text-align:center; padding:20px; color:var(--muted);">No tier data available</td></tr>
                            </tbody>
                            <tfoot>
                                <tr style="border-top:2px solid var(--border);">
                                    <td colspan="3" style="text-align:right; padding:10px; font-weight:700;">Consumption Charges Subtotal:</td>
                                    <td style="text-align:right; padding:10px; font-weight:700;" id="bill_tier_subtotal">R 0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Fixed Costs -->
                    <div id="bill_fixed_costs_section" style="margin-bottom:24px; display:none;">
                        <div style="font-size:18px; font-weight:700; color:var(--text); margin-bottom:12px;">Fixed Costs</div>
                        <table class="billing-table" style="width:100%;">
                            <tbody id="bill_fixed_costs">
                                <tr><td colspan="2" style="text-align:center; padding:20px; color:var(--muted);">No fixed costs</td></tr>
                            </tbody>
                            <tfoot>
                                <tr style="border-top:2px solid var(--border);">
                                    <td style="text-align:right; padding:10px; font-weight:700;">Fixed Costs Subtotal:</td>
                                    <td style="text-align:right; padding:10px; font-weight:700;" id="bill_fixed_costs_subtotal">R 0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Customer Costs -->
                    <div id="bill_customer_costs_section" style="margin-bottom:24px; display:none;">
                        <div style="font-size:18px; font-weight:700; color:var(--text); margin-bottom:12px;">Customer Costs</div>
                        <table class="billing-table" style="width:100%;">
                            <tbody id="bill_customer_costs">
                                <tr><td colspan="2" style="text-align:center; padding:20px; color:var(--muted);">No customer costs</td></tr>
                            </tbody>
                            <tfoot>
                                <tr style="border-top:2px solid var(--border);">
                                    <td style="text-align:right; padding:10px; font-weight:700;">Customer Costs Subtotal:</td>
                                    <td style="text-align:right; padding:10px; font-weight:700;" id="bill_customer_costs_subtotal">R 0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Water Out Charges -->
                    <div id="bill_water_out_section" style="margin-bottom:24px; display:none;">
                        <div style="font-size:18px; font-weight:700; color:var(--text); margin-bottom:12px;">Water Out Charges</div>
                        <table class="billing-table" style="width:100%;">
                            <thead>
                                <tr style="background:var(--bg);">
                                    <th style="text-align:left; padding:10px; border-bottom:2px solid var(--border);">Tier</th>
                                    <th style="text-align:right; padding:10px; border-bottom:2px solid var(--border);">Min (L)</th>
                                    <th style="text-align:right; padding:10px; border-bottom:2px solid var(--border);">Max (L)</th>
                                    <th style="text-align:right; padding:10px; border-bottom:2px solid var(--border);">Usage (L)</th>
                                    <th style="text-align:right; padding:10px; border-bottom:2px solid var(--border);">%</th>
                                    <th style="text-align:right; padding:10px; border-bottom:2px solid var(--border);">Rate (R/kL)</th>
                                    <th style="text-align:right; padding:10px; border-bottom:2px solid var(--border);">Charge (R)</th>
                                </tr>
                            </thead>
                            <tbody id="bill_water_out_breakdown">
                                <tr><td colspan="7" style="text-align:center; padding:20px; color:var(--muted);">No water out data available</td></tr>
                            </tbody>
                            <tfoot>
                                <tr style="border-top:2px solid var(--border);">
                                    <td colspan="6" style="text-align:right; padding:10px; font-weight:700;">Water Out Total:</td>
                                    <td style="text-align:right; padding:10px; font-weight:700;" id="bill_water_out_subtotal">R 0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Water Out Related Charges -->
                    <div id="bill_water_out_related_section" style="margin-bottom:24px; display:none;">
                        <div style="font-size:18px; font-weight:700; color:var(--text); margin-bottom:12px;">Water Out Related Charges</div>
                        <table class="billing-table" style="width:100%;">
                            <tbody id="bill_water_out_related">
                                <tr><td colspan="2" style="text-align:center; padding:20px; color:var(--muted);">No water out related charges</td></tr>
                            </tbody>
                            <tfoot>
                                <tr style="border-top:2px solid var(--border);">
                                    <td style="text-align:right; padding:10px; font-weight:700;">Water Out Related Charges Subtotal:</td>
                                    <td style="text-align:right; padding:10px; font-weight:700;" id="bill_water_out_related_subtotal">R 0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Additional Charges -->
                    <div id="bill_additional_charges_section" style="margin-bottom:24px; display:none;">
                        <div style="font-size:18px; font-weight:700; color:var(--text); margin-bottom:12px;">Additional Charges</div>
                        <table class="billing-table" style="width:100%;">
                            <tbody id="bill_additional_charges">
                                <tr><td colspan="2" style="text-align:center; padding:20px; color:var(--muted);">No additional charges</td></tr>
                            </tbody>
                            <tfoot>
                                <tr style="border-top:2px solid var(--border);">
                                    <td style="text-align:right; padding:10px; font-weight:700;">Additional Charges Subtotal:</td>
                                    <td style="text-align:right; padding:10px; font-weight:700;" id="bill_additional_charges_subtotal">R 0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Totals -->
                    <div id="bill_totals_section" style="background:var(--blue-light); border:2px solid var(--blue); border-radius:12px; padding:24px; margin-top:32px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                            <div style="font-size:18px; font-weight:700; color:var(--text);">Subtotal (Before VAT):</div>
                            <div style="font-size:18px; font-weight:700; color:var(--text);" id="bill_subtotal">R 0.00</div>
                        </div>
                        <div id="bill_vat_section" style="display:none; flex-direction:row; justify-content:space-between; align-items:center; margin-bottom:12px; padding-top:12px; border-top:1px solid var(--border);">
                            <div style="font-size:18px; font-weight:700; color:var(--text);">
                                VAT (<span id="bill_vat_rate">0</span>%):
                            </div>
                            <div style="font-size:18px; font-weight:700; color:var(--text);" id="bill_vat_amount">R 0.00</div>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; padding-top:12px; border-top:2px solid var(--blue);">
                            <div style="font-size:24px; font-weight:700; color:var(--blue);">Total:</div>
                            <div style="font-size:24px; font-weight:700; color:var(--blue);" id="bill_total">R 0.00</div>
                        </div>
                    </div>

                    <!-- No Bill Data Message -->
                    <div id="bill_no_data" style="text-align:center; padding:40px; color:var(--muted); display:none;">
                        <div style="font-size:18px; margin-bottom:8px;">No bill data available</div>
                        <div style="font-size:14px;">Add readings and calculate to see bill preview</div>
                    </div>
                </div>
            </div>
        </div>
        <!-- END BILL PREVIEW CONTAINER -->
    </div>
    
    <!-- Billing & Payments Tab -->
    <div id="tab-billing-payments" class="tab-content">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Billing & Payments</h6>
            </div>
            <div class="card-body">
                <p class="text-muted">Billing & Payments section - to be implemented.</p>
            </div>
        </div>
    </div>
</div>

<!-- Context Menu -->
<div id="context_menu" class="context-menu" style="position:fixed; background:white; border:1px solid var(--border); border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.15); padding:8px 0; z-index:10000; min-width:300px; display:none;">
    <div class="context-menu-header" id="context_menu_header" style="padding:8px 16px; font-weight:700; border-bottom:1px solid var(--border); background:#f9fafb; font-size:13px;">Calculation Explanation</div>
    <div class="context-menu-explanation" id="context_menu_explanation" style="padding:12px 16px; font-size:13px; line-height:1.6; color:var(--text);"></div>
</div>
@endsection

@section('script')
{!! vite(['resources/js/app.js']) !!}
<script>
// Global state
let selectedUser = null;
let selectedAccount = null;
let selectedMeter = null;
let currentTariffTemplate = null;
let currentTemplateTiers = null;
let billingMode = 'period'; // 'period' or 'sector'

// API Base URL
const apiBaseUrl = '{{ url("/admin/user-accounts/manager") }}';
const billingApiBaseUrl = '{{ url("/admin/billing-calculator") }}';
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

// Tab Management
function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById('tab-' + tabName).classList.add('active');
    event.target.classList.add('active');
}

// User Search Functions
async function searchUsers() {
    const query = document.getElementById('user_search_input').value.trim();
    if (!query) {
        alert('Please enter a search term');
        return;
    }
    
    try {
        const response = await fetch(`${apiBaseUrl}/search?query=${encodeURIComponent(query)}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        });
        
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const data = await response.json();
        if (!data.status || data.status !== 200) throw new Error(data.message || 'Search failed');
        
        displayUserResults(data.data || []);
    } catch (error) {
        alert('Error searching users: ' + error.message);
    }
}

function displayUserResults(users) {
    const resultsDiv = document.getElementById('user_search_results');
    const resultsList = document.getElementById('user_results_list');
    
    if (users.length === 0) {
        resultsList.innerHTML = '<div style="padding:20px; text-align:center; color:#6c757d;">No users found</div>';
        resultsDiv.style.display = 'block';
        return;
    }
    
    resultsList.innerHTML = '';
    users.forEach(user => {
        const userDiv = document.createElement('div');
        userDiv.style.cssText = 'padding:12px 16px; border-bottom:1px solid #dee2e6; cursor:pointer; transition:background 0.2s;';
        userDiv.onmouseover = () => userDiv.style.background = '#f8f9fa';
        userDiv.onmouseout = () => userDiv.style.background = '';
        userDiv.onclick = () => selectUser(user);
        userDiv.innerHTML = `
            <div style="font-weight:700; font-size:16px; margin-bottom:4px;">${user.name}</div>
            <div style="font-size:14px; color:#6c757d;">${user.email} | ${user.contact_number || 'No phone'}</div>
        `;
        resultsList.appendChild(userDiv);
    });
    
    resultsDiv.style.display = 'block';
}

function selectUser(user) {
    selectedUser = user;
    
    // Update selected user display
    document.getElementById('selected_user_name').textContent = user.name;
    document.getElementById('selected_user_details').textContent = `${user.email} | ${user.contact_number || 'No phone'}`;
    document.getElementById('selected_user_display').style.display = 'block';
    
    // Hide search results
    document.getElementById('user_search_results').style.display = 'none';
    
    // Load accounts for this user
    loadUserAccounts(user.id);
}

async function loadUserAccounts(userId) {
    try {
        const response = await fetch(`${apiBaseUrl}/user/${userId}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        });
        
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const data = await response.json();
        if (!data.status || data.status !== 200) throw new Error(data.message || 'Failed to load user');
        
        const userData = data.data;
        const accounts = [];
        
        // Extract accounts from sites
        if (userData.sites) {
            userData.sites.forEach(site => {
                if (site.accounts) {
                    site.accounts.forEach(account => {
                        accounts.push({
                            id: account.id,
                            name: account.account_name,
                            number: account.account_number,
                            site_id: site.id,
                            site_title: site.title
                        });
                    });
                }
            });
        }
        
        // Populate account dropdown
        const accountSelect = document.getElementById('account_select');
        accountSelect.innerHTML = '<option value="">-- Select Account --</option>';
        accounts.forEach(account => {
            const option = document.createElement('option');
            option.value = account.id;
            option.textContent = `${account.name}${account.number ? ' (' + account.number + ')' : ''}`;
            option.dataset.account = JSON.stringify(account);
            accountSelect.appendChild(option);
        });
        
        document.getElementById('account_selection').style.display = 'block';
    } catch (error) {
        alert('Error loading accounts: ' + error.message);
    }
}

function selectAccountFromDropdown() {
    const select = document.getElementById('account_select');
    const selectedOption = select.options[select.selectedIndex];
    if (!selectedOption || !selectedOption.value) {
        document.getElementById('selected_account_display').style.display = 'none';
        selectedAccount = null;
        return;
    }
    
    const account = JSON.parse(selectedOption.dataset.account);
    selectedAccount = account;
    
    document.getElementById('selected_account_name').textContent = account.name;
    document.getElementById('selected_account_display').style.display = 'block';
    
    // Load account details and meters
    loadAccountDetails(account.id);
}

async function loadAccountDetails(accountId) {
    try {
        const response = await fetch(`${apiBaseUrl}/user-accounts/manager/account/${accountId}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        });
        
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const data = await response.json();
        if (!data.success) throw new Error(data.message || 'Failed to load account');
        
        // Store account data
        const accountData = data.data;
        selectedAccount = accountData.account;
        
        // Load first meter if available
        if (accountData.meters && accountData.meters.length > 0) {
            selectedMeter = accountData.meters[0];
            // Load readings for this meter
            loadMeterReadings(selectedMeter.id);
        }
        
        // Load tariff template if account has one
        if (selectedAccount.tariff_template_id) {
            loadTariffTemplate(selectedAccount.tariff_template_id);
        }
        
        // Update tab contents
        updateUserDetailsTab(accountData);
        updateAccountsMetersTab(accountData);
        updateEditAccountTab(accountData);
    } catch (error) {
        alert('Error loading account details: ' + error.message);
    }
}

async function loadMeterReadings(meterId) {
    try {
        const response = await fetch(`${apiBaseUrl}/readings/${meterId}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        });
        
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const data = await response.json();
        
        // Populate readings into billing calculator UI
        if (data.readings && Array.isArray(data.readings)) {
            // Convert readings to format expected by billing calculator
            const readings = data.readings.map(r => ({
                date: r.reading_date,
                value: parseFloat(r.reading_value),
                type: r.reading_type || 'ACTUAL'
            }));
            
            // Initialize billing calculator with readings
            initializeBillingCalculator(readings);
        }
    } catch (error) {
        console.error('Error loading readings:', error);
    }
}

// Billing Calculator Functions (will be loaded from billing-calculator.blade.php)
// For now, we'll include a reference to load the billing calculator JavaScript
// The actual implementation will be in a separate script that we'll include

// Placeholder functions - these will be replaced with actual billing calculator logic
function switchBillingMode(mode) {
    billingMode = mode;
    const periodContainer = document.getElementById('period-mode-container');
    const sectorContainer = document.getElementById('sector-mode-container');
    
    document.querySelectorAll('.mode-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    event.target.classList.add('active');
    
    if (mode === 'period') {
        periodContainer.style.display = 'block';
        sectorContainer.style.display = 'none';
    } else {
        periodContainer.style.display = 'none';
        sectorContainer.style.display = 'block';
    }
}

function add_period() {
    alert('Add period functionality - to be implemented with billing calculator logic');
}

function add_reading() {
    alert('Add reading functionality - to be implemented with billing calculator logic');
}

function add_sector_reading() {
    alert('Add sector reading functionality - to be implemented with billing calculator logic');
}

function calculate() {
    alert('Calculate functionality - to be implemented with billing calculator logic');
}

function calculate_sector() {
    alert('Calculate sector functionality - to be implemented with billing calculator logic');
}

function resetTariffTemplate() {
    alert('Reset template functionality - to be implemented');
}

function loadTariffTemplate(templateId) {
    // Load tariff template details
    fetch(`${billingApiBaseUrl}/tariff-template-details`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ template_id: templateId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            currentTariffTemplate = data.template;
            currentTemplateTiers = data.template.water_in || [];
            // Populate tariff template UI
            populateTariffTemplate(data.template);
        }
    })
    .catch(error => {
        console.error('Error loading tariff template:', error);
    });
}

function populateTariffTemplate(template) {
    // Populate tariff template display (same as billing calculator)
    document.getElementById('tariff_template_name_display').textContent = template.template_name || '—';
    document.getElementById('tariff_billing_type_display').textContent = `Billing Type: ${template.billing_type || '—'}`;
    document.getElementById('tariff_billing_day_display').textContent = `Billing Day: ${template.billing_day || '—'}`;
    document.getElementById('tariff_vat_rate_display').textContent = `VAT: ${template.vat_rate || 0}%`;
    document.getElementById('tariff_details_display').style.display = 'block';
    
    // Populate tiers
    if (template.water_in && template.water_in.length > 0) {
        const tbody = document.getElementById('tariff_tiers_display');
        tbody.innerHTML = '';
        template.water_in.forEach((tier, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>Tier ${index + 1}</td>
                <td style="text-align:right;">${tier.max === null || tier.max === undefined ? '∞' : tier.max.toLocaleString()}</td>
                <td style="text-align:right;">R ${(tier.rate || 0).toFixed(2)}</td>
            `;
            tbody.appendChild(tr);
        });
    }
}

function initializeBillingCalculator(readings) {
    // Initialize billing calculator with readings from database
    // This will integrate with the billing calculator JavaScript
    console.log('Initializing billing calculator with readings:', readings);
}

function updateUserDetailsTab(accountData) {
    const content = document.getElementById('user_details_content');
    if (accountData.user) {
        content.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Name:</strong> ${accountData.user.name}</p>
                    <p><strong>Email:</strong> ${accountData.user.email}</p>
                    <p><strong>Phone:</strong> ${accountData.user.contact_number || 'N/A'}</p>
                </div>
            </div>
        `;
    }
}

function updateAccountsMetersTab(accountData) {
    const content = document.getElementById('accounts_meters_content');
    if (accountData.meters) {
        let html = '<h5>Meters</h5><table class="table table-bordered"><thead><tr><th>Meter Title</th><th>Meter Number</th><th>Readings</th></tr></thead><tbody>';
        accountData.meters.forEach(meter => {
            html += `<tr><td>${meter.meter_title}</td><td>${meter.meter_number}</td><td>${meter.readings.length}</td></tr>`;
        });
        html += '</tbody></table>';
        content.innerHTML = html;
    }
}

function updateEditAccountTab(accountData) {
    const content = document.getElementById('edit_account_content');
    if (accountData.account) {
        content.innerHTML = `
            <form id="edit_account_form">
                <div class="form-group">
                    <label>Account Name:</label>
                    <input type="text" class="form-control" value="${accountData.account.account_name}" id="edit_account_name">
                </div>
                <div class="form-group">
                    <label>Account Number:</label>
                    <input type="text" class="form-control" value="${accountData.account.account_number || ''}" id="edit_account_number">
                </div>
                <button type="button" class="btn btn-primary" onclick="saveAccountChanges()">Save Changes</button>
            </form>
        `;
    }
}

function saveAccountChanges() {
    if (!selectedAccount) {
        alert('Please select an account first');
        return;
    }
    
    const accountName = document.getElementById('edit_account_name').value;
    const accountNumber = document.getElementById('edit_account_number').value;
    
    fetch(`${apiBaseUrl}/account/${selectedAccount.id}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            account_name: accountName,
            account_number: accountNumber
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 200) {
            alert('Account updated successfully');
            loadAccountDetails(selectedAccount.id);
        } else {
            alert('Error: ' + (data.message || 'Failed to update account'));
        }
    })
    .catch(error => {
        alert('Error updating account: ' + error.message);
    });
}

// Copy functions (placeholders)
function copy_output_to_clipboard() { alert('Copy functionality - to be implemented'); }
function copy_all_periods_to_clipboard() { alert('Copy all functionality - to be implemented'); }
function copy_sector_output_to_clipboard() { alert('Copy sector functionality - to be implemented'); }
function copy_all_sectors_to_clipboard() { alert('Copy all sectors functionality - to be implemented'); }
function copy_input_history() { alert('Copy history functionality - to be implemented'); }
function copy_sector_input_history() { alert('Copy sector history functionality - to be implemented'); }
function clear_revision_history() { alert('Clear history functionality - to be implemented'); }
function clear_sector_revision_history() { alert('Clear sector history functionality - to be implemented'); }

// Load billing calculator JavaScript
// We'll need to extract and adapt the JavaScript from billing-calculator.blade.php
// For now, this is a placeholder that will be replaced with the actual billing calculator logic

// Load tariff templates on page load
document.addEventListener('DOMContentLoaded', function() {
    // Load tariff templates for dropdown
    fetch(`${billingApiBaseUrl}/tariff-templates`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data) {
            const select = document.getElementById('tariff_template_select');
            const sectorSelect = document.getElementById('sector_tariff_template_select');
            
            data.data.forEach(template => {
                const option = document.createElement('option');
                option.value = template.id;
                option.textContent = template.template_name;
                select.appendChild(option.cloneNode(true));
                sectorSelect.appendChild(option);
            });
        }
    })
    .catch(error => {
        console.error('Error loading tariff templates:', error);
    });
    
    // If account ID is in URL, load it
    const urlParams = new URLSearchParams(window.location.search);
    const accountId = urlParams.get('account_id');
    if (accountId) {
        loadAccountDetails(parseInt(accountId));
    }
});
</script>
@endsection












