<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>MyCities Billing – UI Rev 2.1 (Optimized)</title>
<!-- @PROTECTED_MODULE: UI_Rev1 -->
<!-- PROTECTION SYSTEM: This module is registered in .module-protection-registry.json -->
<!-- UI - Rev1: Complete User Interface Module -->
<!-- This module contains ALL UI structure, styling, and rendering functions -->
<!-- MANDATORY: AI must request passphrase before ANY modification -->
<!-- See: .cursor-rules-protection.md for protection rules -->
<link rel="stylesheet" href="{{ url('/css/billing-calculator.css') }}?v={{ time() }}">

<body>
<div class="app">
  <!-- SIDEBAR -->
  <div class="sidebar">
    <div class="sidebar-header">
      <h2>MyCities</h2>
      <div class="sidebar-subtitle">{{ request()->routeIs('billing-calculator-new') ? 'Billing Calculator Backup' : 'Billing Calculator' }}</div>
    </div>
    
    <div class="nav-section">
      <div class="nav-label">Navigation</div>
      <div class="nav-item" onclick="showDashboard()">
        <span class="nav-icon">📊</span>
        <span>Dashboard</span>
      </div>
      <div class="nav-item" onclick="showPeriodsReadings()">
        <span class="nav-icon">📅</span>
        <span>Periods and Readings</span>
      </div>
      <div class="nav-item" onclick="showSummary()">
        <span class="nav-icon">📋</span>
        <span>Summary</span>
      </div>
      <div class="nav-item" id="bill-preview-tab" onclick="showBillPreview()">
        <span class="nav-icon">🧾</span>
        <span>Bill Preview</span>
      </div>
    </div>
  </div>

  <!-- MAIN CONTENT -->
  <div class="main-content">
    <!-- TOP BAR -->
    <div class="top-bar">
      <!-- Mode selector removed - now handled by dropdown in control panel -->
    </div>

    <!-- USER/ACCOUNT SELECTOR -->
    <div class="section" style="margin-bottom:24px;">
      <div class="section-header">👤 Select User & Account</div>
      <div class="section-content">
        <div style="display:flex; gap:20px; align-items:flex-end; flex-wrap:wrap; margin-bottom:16px;">
          <div style="flex:1; min-width:200px;">
            <label style="display:block; margin-bottom:8px; font-weight:600; color:var(--text);">User:</label>
            <select id="user_select" class="input-select" style="width:100%;" onchange="window.loadUserAccounts(this.value)">
              <option value="">-- Select User --</option>
            </select>
          </div>
          <div style="flex:1; min-width:200px;">
            <label style="display:block; margin-bottom:8px; font-weight:600; color:var(--text);">Account:</label>
            <select id="account_select" class="input-select" style="width:100%;" onchange="window.loadAccountDetails(this.value)" disabled>
              <option value="">-- Select Account --</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- USER INFORMATION (COLLAPSIBLE) -->
    <div class="section collapsed" id="user_info_section" style="display:none; margin-bottom:24px;">
      <div class="section-header" onclick="this.parentElement.classList.toggle('collapsed')">ℹ️ User Information</div>
      <div class="section-content">
        <div id="user_info_display" style="padding:16px; background:var(--card); border-radius:8px; border:1px solid var(--border);">
          <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:16px;">
            <div>
              <div style="font-weight:600; color:var(--muted); margin-bottom:4px;">Name:</div>
              <div id="user_name_display" style="color:var(--text);">—</div>
            </div>
            <div>
              <div style="font-weight:600; color:var(--muted); margin-bottom:4px;">Email:</div>
              <div id="user_email_display" style="color:var(--text);">—</div>
            </div>
            <div>
              <div style="font-weight:600; color:var(--muted); margin-bottom:4px;">Contact:</div>
              <div id="user_contact_display" style="color:var(--text);">—</div>
            </div>
            <div>
              <div style="font-weight:600; color:var(--muted); margin-bottom:4px;">Address:</div>
              <div id="site_address_display" style="color:var(--text);">—</div>
            </div>
            <div>
              <div style="font-weight:600; color:var(--muted); margin-bottom:4px;">Region:</div>
              <div id="site_region_display" style="color:var(--text);">—</div>
            </div>
            <div>
              <div style="font-weight:600; color:var(--muted); margin-bottom:4px;">Account Name:</div>
              <div id="account_name_display" style="color:var(--text);">—</div>
            </div>
            <div>
              <div style="font-weight:600; color:var(--muted); margin-bottom:4px;">Account Number:</div>
              <div id="account_number_display" style="color:var(--text);">—</div>
            </div>
            <div>
              <div style="font-weight:600; color:var(--muted); margin-bottom:4px;">Name on Bill:</div>
              <div id="name_on_bill_display" style="color:var(--text);">—</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- BILL DISPLAY SECTION -->
    <div class="section" id="bill_display_section" style="display:none; margin-bottom:24px;">
      <div class="section-header">🧾 Bills</div>
      <div class="section-content">
        <div id="bills_display" style="padding:16px; background:var(--card); border-radius:8px; border:1px solid var(--border);">
          <div style="text-align:center; color:var(--muted); padding:20px;">No bills available</div>
        </div>
      </div>
    </div>

    <!-- PERIOD MODE CONTAINER -->
    <div id="period-mode-container">
      <!-- Compact Control Panel - Single Column Layout -->
      <div class="compact-control-panel" style="margin-bottom:24px; max-width:600px;">
        <!-- Billing Mode Selector -->
        <div class="compact-field">
          <label class="compact-label">Billing Mode</label>
          <select id="billing_mode_select_period" class="compact-input" onchange="window.switchBillingModeFromDropdown(this.value)">
            <option value="period" selected>Period to Period</option>
            <option value="sector">Date to Date</option>
          </select>
        </div>
        
        <!-- Template Selector -->
        <div class="compact-field">
          <label class="compact-label">Template</label>
          <select id="tariff_template_select" class="compact-input" onchange="window.loadTariffTemplate(this.value, 'period')">
            <option value="">-- Select Template --</option>
          </select>
        </div>
        
        <!-- Reset Button -->
        <div style="margin-top:12px;">
          <button id="reset_template_btn" onclick="window.resetTariffTemplate('period')" class="reset-button" style="display:none;">Reset</button>
        </div>
      </div>
      
      <!-- 1️⃣ TARIFF TEMPLATE -->
      <div class="section collapsed">
        <div class="section-header" onclick="this.parentElement.classList.toggle('collapsed')">
          1️⃣ Tariff Template
        </div>
        <div class="section-content">
          <div style="margin-bottom:16px;">
            <label>Select Tariff Template:</label>
            <select id="tariff_template_select_old" class="input-select" onchange="window.loadTariffTemplate(this.value, 'period')" style="margin-top:8px; width:100%; display:none;">
              <option value="">-- Select Template --</option>
            </select>
            <button id="reset_template_btn_old" onclick="window.resetTariffTemplate('period')" class="btn-secondary" style="margin-top:8px; display:none;">Reset Template</button>
          </div>
          
          <div id="tariff_details_display" style="display:none; padding:16px; background:var(--card); border-radius:8px; border:1px solid var(--border); margin-top:16px;">
            <div style="font-size:18px; font-weight:700; color:var(--text); margin-bottom:12px;" id="tariff_template_name_display">—</div>
            <div style="font-size:14px; color:var(--muted); margin-bottom:16px;">
              <span id="tariff_billing_type_display">Billing Type: —</span> | 
              <span id="tariff_billing_day_display">Billing Day: —</span> | 
              <span id="tariff_vat_rate_display">VAT: —%</span>
            </div>
            <div id="tariff_error" style="color:var(--red); display:none; margin-top:8px;"></div>
            
            <!-- Collapsible Tariff Charges -->
            <div class="section collapsed" style="margin-top:16px; margin-bottom:0;">
              <div class="section-header" onclick="this.parentElement.classList.toggle('collapsed')" style="font-size:16px;">
                📋 View Complete Tariff Charges
              </div>
              <div class="section-content">
                <!-- Tiers -->
                <div style="margin-bottom:20px;">
                  <div style="font-size:16px; font-weight:700; color:var(--text); margin-bottom:12px;">Water Tiers</div>
                  <table style="width:100%; border-collapse:collapse;">
                    <thead>
                      <tr style="background:var(--bg);">
                        <th style="text-align:left; padding:8px; border-bottom:1px solid var(--border);">Tier</th>
                        <th style="text-align:right; padding:8px; border-bottom:1px solid var(--border);">Max (L)</th>
                        <th style="text-align:right; padding:8px; border-bottom:1px solid var(--border);">Rate (R/kL)</th>
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
                  <table style="width:100%; border-collapse:collapse;">
                    <tbody id="tariff_fixed_costs_display">
                      <tr><td colspan="2" style="text-align:center; padding:12px; color:var(--muted);">No fixed costs</td></tr>
                    </tbody>
                  </table>
                </div>
                
                <!-- Customer Costs -->
                <div style="margin-bottom:20px;">
                  <div style="font-size:16px; font-weight:700; color:var(--text); margin-bottom:12px;">Customer Costs</div>
                  <table style="width:100%; border-collapse:collapse;">
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

      <!-- 2️⃣ BILLING PERIOD -->
      <div class="section">
        <div class="section-header">2️⃣ Billing Period</div>
        <div class="section-content">
          <div style="display:flex; gap:20px; align-items:flex-end; flex-wrap:wrap;">
            <div>
              <label>Bill Day:</label>
              <input type="number" id="bill_day" class="input-number" min="1" max="31" value="20" style="margin-top:8px; width:100px;">
            </div>
            <div>
              <label>Start Month:</label>
              <input type="month" id="start_month" class="input-date" value="2026-01" style="margin-top:8px; width:150px;">
            </div>
            <div>
              <button onclick="window.add_period()" class="btn-secondary" style="margin-top:8px;">Add Period</button>
            </div>
          </div>
        </div>
      </div>

      <!-- 3️⃣ PERIODS & READINGS -->
      <div class="section">
        <div class="section-header">3️⃣ Periods and Readings</div>
        <div class="section-content">
          <table id="period_table" style="width:100%; margin-bottom:20px;">
            <thead>
              <tr>
                <th>#</th><th>Billing Period</th><th>Status</th><th>Period_Total_Usage (L)</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
          <table id="period_reading_table">
            <thead>
              <tr>
                <th>Date</th><th>Reading (L)</th><th>Cost (R)</th><th></th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
          <div class="action-row" style="margin-top:12px;">
            <button onclick="window.add_reading()" class="btn-secondary">➕ Add Reading</button>
          </div>

          <div id="period_dashboard" style="margin-top:20px;">
            <!-- Summary Metrics (Blue Area) -->
            <div style="background:var(--blue); border-radius:12px; padding:20px;">
              <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:16px;">
                <div>
                  <div style="font-size:14px; color:rgba(255,255,255,0.8); margin-bottom:4px;">Daily Usage</div>
                  <div style="font-size:24px; font-weight:700; color:#fff;" id="period_dashboard_daily_usage">—</div>
                </div>
                <div>
                  <div style="font-size:14px; color:rgba(255,255,255,0.8); margin-bottom:4px;">Daily Cost</div>
                  <div style="font-size:24px; font-weight:700; color:#fff;" id="period_dashboard_daily_cost">—</div>
                </div>
                <div>
                  <div style="font-size:14px; color:rgba(255,255,255,0.8); margin-bottom:4px;">Total Used</div>
                  <div style="font-size:24px; font-weight:700; color:#fff;" id="period_dashboard_total_used">—</div>
                </div>
                <div>
                  <div style="font-size:14px; color:rgba(255,255,255,0.8); margin-bottom:4px;">Total Cost</div>
                  <div style="font-size:24px; font-weight:700; color:#fff;" id="period_dashboard_total_cost">R 0.00</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- CALCULATE -->
      <div style="text-align:center; margin:30px 0;">
        <!-- ENGINE SELECTOR (Admin-only, Pre-launch Testing) -->
        @php
          $showEngineSwitch = false;
          if (auth()->check()) {
            $user = auth()->user();
            if (($user->is_admin ?? 0) == 1 || ($user->is_super_admin ?? 0) == 1 || $user->email === 'admin@mycities.co.za') {
              $showEngineSwitch = true;
            }
          }
        @endphp
        @if($showEngineSwitch)
        <div style="margin-bottom:16px; padding:12px; background:var(--card); border:1px solid var(--border); border-radius:8px; max-width:400px; margin-left:auto; margin-right:auto;">
          <label style="display:block; margin-bottom:8px; font-weight:600; color:var(--text); font-size:14px;">
            🔧 Calculator Engine:
          </label>
          <select id="calculator_engine" class="input-select" style="width:100%;" onchange="window.onEngineChange()">
            <option value="js" selected>JavaScript (Legacy)</option>
            <option value="php">PHP (Experimental)</option>
          </select>
          <div id="engine_warning" style="margin-top:8px; padding:8px; background:#fff3cd; border:1px solid #ffc107; border-radius:4px; font-size:12px; color:#856404; display:none;">
            ⚠️ Changing engine will clear current results. Click "Calculate" to run selected engine.
          </div>
          <div id="engine_indicator" style="margin-top:8px; font-size:12px; font-weight:600; color:var(--text);">
            Active: <span id="engine_status" style="color:#10b981;">JavaScript (Legacy)</span>
          </div>
        </div>
        @endif
        
        <button id="calculate_btn" onclick="calculate()" class="btn-calculate" disabled>Calculate</button>
        <div id="calculate_error" style="color:var(--red); margin-top:12px; display:none; font-size:14px;"></div>
        
        <!-- COMPARISON TEST BUTTON (Admin Testing) -->
        @if($showEngineSwitch)
        <div style="margin-top:16px; padding:12px; background:var(--card); border:1px solid var(--border); border-radius:8px;">
          <button id="comparison_test_btn" onclick="window.runComparisonTest()" class="btn-secondary" style="width:100%;" disabled>
            🔬 Run Comparison Test (JS vs PHP)
          </button>
          <div id="comparison_test_result" style="margin-top:12px; display:none; padding:12px; border-radius:6px; font-size:13px;"></div>
        </div>
        @endif
      </div>

      <!-- 4️⃣ PERIOD CALCULATION OUTPUT -->
      <div class="section">
        <div class="section-header">4️⃣ Period Calculation Output</div>
        <div class="section-content">
          <div id="period_output_container"></div>
        </div>
      </div>

      <!-- SAVE BILLS BUTTON -->
      <div id="save_bills_container" style="text-align:center; margin:30px 0; display:none;">
        <button id="save_bills_btn" onclick="window.saveBills()" class="btn-calculate" disabled>💾 Save Bills</button>
        <div id="save_bills_error" style="color:var(--red); margin-top:12px; display:none; font-size:14px;"></div>
        <div id="save_bills_success" style="color:#10b981; margin-top:12px; display:none; font-size:14px;"></div>
      </div>
    </div>

    <!-- SECTOR MODE CONTAINER -->
    <div id="sector-mode-container" style="display:none;">
      <!-- Compact Control Panel - Single Column Layout -->
      <div class="compact-control-panel" style="margin-bottom:24px; max-width:600px;">
        <!-- Billing Mode Selector -->
        <div class="compact-field">
          <label class="compact-label">Billing Mode</label>
          <select id="billing_mode_select" class="compact-input" onchange="window.switchBillingModeFromDropdown(this.value)">
            <option value="period">Period to Period</option>
            <option value="sector" selected>Date to Date</option>
          </select>
        </div>
        
        <!-- Template Selector -->
        <div class="compact-field">
          <label class="compact-label">Template</label>
          <select id="sector_tariff_template_select" class="compact-input" onchange="window.loadTariffTemplate(this.value, 'sector')">
            <option value="">-- Select Template --</option>
          </select>
        </div>
        
        <!-- Start Date Picker -->
        <div class="compact-field">
          <label class="compact-label">Start Date</label>
          <div style="position:relative;">
            <input type="date" id="sector_date_picker" class="compact-input" style="padding-right:40px;">
            <span style="position:absolute; right:12px; top:50%; transform:translateY(-50%); pointer-events:none; color:var(--muted);">📅</span>
          </div>
        </div>
        
        <!-- Reset Button (Below Start Date) -->
        <div style="margin-top:12px;">
          <button id="sector_reset_template_btn" onclick="window.resetTariffTemplate('sector')" class="reset-button" style="display:none;">Reset</button>
        </div>
      </div>

      <!-- Tariff Details Display -->
      <div id="sector_tariff_details_display" style="display:none; padding:16px; background:var(--card); border-radius:8px; border:1px solid var(--border); margin-bottom:16px; margin-top:16px;">
        <div style="font-size:18px; font-weight:700; color:var(--text); margin-bottom:12px;" id="sector_tariff_template_name_display">—</div>
        <div style="font-size:14px; color:var(--muted); margin-bottom:16px;">
          <span id="sector_tariff_billing_type_display">Billing Type: —</span> | 
          <span id="sector_tariff_billing_day_display">Billing Day: —</span> | 
          <span id="sector_tariff_vat_rate_display">VAT: —%</span>
        </div>
        <div id="sector_tariff_error" style="color:var(--red); display:none; margin-top:8px;"></div>
        
        <!-- Collapsible Tariff Charges -->
        <div class="section collapsed" style="margin-top:16px; margin-bottom:0;">
          <div class="section-header" onclick="this.parentElement.classList.toggle('collapsed')" style="font-size:16px;">
            📋 View Complete Tariff Charges
          </div>
          <div class="section-content">
            <!-- Tiers -->
            <div style="margin-bottom:20px;">
              <div style="font-size:16px; font-weight:700; color:var(--text); margin-bottom:12px;">Water Tiers</div>
              <table style="width:100%; border-collapse:collapse;">
                <thead>
                  <tr style="background:var(--bg);">
                    <th style="text-align:left; padding:8px; border-bottom:1px solid var(--border);">Tier</th>
                    <th style="text-align:right; padding:8px; border-bottom:1px solid var(--border);">Max (L)</th>
                    <th style="text-align:right; padding:8px; border-bottom:1px solid var(--border);">Rate (R/kL)</th>
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
              <table style="width:100%; border-collapse:collapse;">
                <tbody id="sector_tariff_fixed_costs_display">
                  <tr><td colspan="2" style="text-align:center; padding:12px; color:var(--muted);">No fixed costs</td></tr>
                </tbody>
              </table>
            </div>
            
            <!-- Customer Costs -->
            <div style="margin-bottom:20px;">
              <div style="font-size:16px; font-weight:700; color:var(--text); margin-bottom:12px;">Customer Costs</div>
              <table style="width:100%; border-collapse:collapse;">
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

      <!-- 3️⃣ SECTORS & READINGS -->
      <div class="section">
        <div class="section-header">3️⃣ Sectors and Readings</div>
        <div class="section-content">

          <div id="periods_list" style="margin-bottom:20px;">
            <!-- Populated by JavaScript - Period headers -->
          </div>

          <h3 style="margin-top:20px; font-size:16px; font-weight:700;">Meter Readings (active period)</h3>

          <table id="sector_reading_table">
            <thead>
              <tr>
                <th>Date</th><th>Reading (L)</th><th>Difference (L)</th><th>Cost (R)</th><th></th>
              </tr>
            </thead>
            <tbody>
              <!-- Populated by JavaScript -->
            </tbody>
          </table>

          <div class="action-row" style="margin-top:12px; margin-bottom:20px; display:flex; gap:12px; align-items:center;">
            <button onclick="add_sector_reading()" class="btn-secondary" style="flex:1;">➕ Add Reading</button>
            <button id="sector_calculate_btn" onclick="window.calculate_sector()" class="btn-secondary" disabled style="flex:1;">Calculate</button>
          </div>
          <div id="sector_calculate_error" style="color:var(--red); margin-top:8px; display:none; font-size:14px;"></div>

          <div id="sector_dashboard" style="margin-top:20px;">
            <!-- Summary Metrics (Blue Area) -->
            <div style="background:var(--blue); border-radius:12px; padding:20px;">
              <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr 1fr; gap:16px;">
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
                <div>
                  <div style="font-size:14px; color:rgba(255,255,255,0.8); margin-bottom:4px;">Total Used</div>
                  <div style="font-size:24px; font-weight:700; color:#fff;" id="dashboard_total_used">—</div>
                </div>
                <div>
                  <div style="font-size:14px; color:rgba(255,255,255,0.8); margin-bottom:4px;">Total Cost</div>
                  <div style="font-size:24px; font-weight:700; color:#fff;" id="dashboard_total_cost">R 0.00</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Close Period button removed: Auto-close handles period closure automatically -->
        </div>
      </div>

      <!-- 4️⃣ SECTOR CALCULATION OUTPUT -->
      <div class="section">
        <div class="section-header">4️⃣ Sector Calculation Output</div>
        <div class="section-content">
          <div id="sector_output_container" style="max-width: 1100px; margin: auto; font-family: 'Inter', 'Segoe UI', Arial, sans-serif;"></div>
        </div>
      </div>
    </div>

    <!-- BILL PREVIEW CONTAINER -->
    <div id="bill-preview-container" style="display:none;">
      <div class="section">
        <div class="section-header">Bill Preview</div>
        <div class="section-content">
          <div id="bill_preview_content">
            <!-- Bill preview will be populated by JavaScript -->
            <div id="bill_no_data" style="text-align:center; padding:40px; color:var(--muted); display:none;">
              <div style="font-size:18px; margin-bottom:8px;">No bill data available</div>
              <div style="font-size:14px;">Add readings and calculate to see bill preview</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- END BILL PREVIEW CONTAINER -->

<!-- Context Menu -->
<div id="context_menu" class="context-menu">
    <div class="context-menu-header" id="context_menu_header">Calculation Explanation</div>
    <div class="context-menu-explanation" id="context_menu_explanation"></div>
</div>
<!-- @END_PROTECTED_MODULE: UI_Rev1 -->

<!-- Embedded Revision Data (Updated by JavaScript) -->
<script id="revision_data" type="application/json">
{"revisions":[{"number":1,"timestamp":"2025-01-27T00:00:00.000Z","action":"HTML Saved","details":"HTML file saved as: MyCities - Billing - Rev_1.html"}],"revisionNumber":1}
</script>

<!-- Load core billing calculator JavaScript -->
<script src="{{ url('/js/billing-calculator-core.js') }}?v={{ time() }}"></script>

<!-- Laravel API Integration (must stay inline for Blade variables) -->
<script>
// ==================== LARAVEL API INTEGRATION ====================
// Setup CSRF token for all fetch requests
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
const apiBaseUrl = '{{ url("/admin/billing-calculator") }}';

// Configure fetch to include CSRF token
const originalFetch = window.fetch;
window.fetch = function(url, options = {}) {
    if (typeof url === 'string' && url.startsWith('/')) {
        options.headers = options.headers || {};
        options.headers['X-CSRF-TOKEN'] = csrfToken;
        options.headers['Content-Type'] = options.headers['Content-Type'] || 'application/json';
        options.headers['Accept'] = 'application/json';
    }
    return originalFetch(url, options);
};

// Laravel API endpoints
const LaravelAPI = {
    getUsers: async function() {
        try {
            const response = await fetch(`${apiBaseUrl}/users`);
            const data = await response.json();
            return data.success ? data.data : [];
        } catch (error) {
            console.error('Error loading users:', error);
            return [];
        }
    },
    
    getAccountDetails: async function(accountId) {
        try {
            const response = await fetch(`${apiBaseUrl}/account/${accountId}`);
            const data = await response.json();
            return data.success ? data.data : null;
        } catch (error) {
            console.error('Error loading account details:', error);
            return null;
        }
    },
    
    getTariffTemplates: async function() {
        try {
            const response = await fetch(`${apiBaseUrl}/tariff-templates`);
            const data = await response.json();
            return data.success ? data.data : [];
        } catch (error) {
            console.error('Error loading tariff templates:', error);
            return [];
        }
    },
    
    getTariffTemplateDetails: async function(templateId) {
        try {
            // Ensure templateId is an integer
            const templateIdInt = parseInt(templateId, 10);
            if (isNaN(templateIdInt)) {
                throw new Error('Invalid template ID');
            }
            
            console.log('Loading tariff template:', templateIdInt, 'from:', `${apiBaseUrl}/tariff-template-details`);
            console.log('CSRF Token:', csrfToken ? 'Present' : 'Missing');
            
            const response = await fetch(`${apiBaseUrl}/tariff-template-details`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ template_id: templateIdInt }),
            });
            
            console.log('Response status:', response.status);
            
            if (!response.ok) {
                let errorText = '';
                try {
                    errorText = await response.text();
                    console.error('API Error Response:', response.status, errorText);
                } catch (e) {
                    errorText = `HTTP ${response.status}`;
                }
                throw new Error(`HTTP ${response.status}: ${errorText}`);
            }
            
            const data = await response.json();
            console.log('API Response:', data);
            
            if (!data.success) {
                const errorMsg = data.message || data.error || 'Failed to load tariff template';
                console.error('API returned error:', errorMsg);
                throw new Error(errorMsg);
            }
            
            return data.data;
        } catch (error) {
            console.error('Error loading tariff template details:', error);
            throw error; // Re-throw to let caller handle it
        }
    }
};

// Global template storage
let currentTariffTemplate = null;
let currentTemplateTiers = null; // Store tiers separately for easy access
let allTariffTemplates = []; // Store all templates for filtering

// Filter templates by billing type
function filterTemplatesByBillingType(templates, billingType) {
    if (!templates || templates.length === 0) return [];
    
    // billingType should be 'MONTHLY' for period mode or 'DATE_TO_DATE' for sector mode
    return templates.filter(template => {
        // If template doesn't have billing_type, default to MONTHLY
        const templateBillingType = template.billing_type || 'MONTHLY';
        return templateBillingType === billingType;
    });
}

// Populate dropdown with filtered templates
function populateTemplateDropdown(dropdownId, billingType) {
    const dropdown = document.getElementById(dropdownId);
    if (!dropdown) return;
    
    // Clear existing options except the first one
    while (dropdown.options.length > 1) {
        dropdown.remove(1);
    }
    
    // Filter templates by billing type
    const filteredTemplates = filterTemplatesByBillingType(allTariffTemplates, billingType);
    
    if (filteredTemplates.length === 0) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = '-- No templates available for this billing type --';
        option.disabled = true;
        dropdown.appendChild(option);
    } else {
        filteredTemplates.forEach(template => {
            const option = document.createElement('option');
            option.value = template.id;
            option.textContent = template.name + (template.region_name ? ` (${template.region_name})` : '');
            dropdown.appendChild(option);
        });
    }
}

// Load tariff templates and populate dropdowns
window.loadTariffTemplates = async function() {
    allTariffTemplates = await LaravelAPI.getTariffTemplates();
    
    // Populate dropdowns based on current billing mode
    updateTemplateDropdowns();
    
    return allTariffTemplates;
};

// Update template dropdowns based on current billing mode
function updateTemplateDropdowns() {
    // Get current billing mode
    const periodContainer = document.getElementById('period-mode-container');
    const isPeriodMode = periodContainer && periodContainer.style.display !== 'none';
    
    if (isPeriodMode) {
        // Period to Period mode - show MONTHLY templates
        populateTemplateDropdown('tariff_template_select', 'MONTHLY');
        populateTemplateDropdown('sector_tariff_template_select', 'MONTHLY');
    } else {
        // Date to Date mode - show DATE_TO_DATE templates
        populateTemplateDropdown('tariff_template_select', 'DATE_TO_DATE');
        populateTemplateDropdown('sector_tariff_template_select', 'DATE_TO_DATE');
    }
}

// Format currency for display
function formatCurrency(amount) {
    if (amount === null || amount === undefined || isNaN(amount)) return 'R 0.00';
    return 'R ' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, " ");
}

// Format bill number (with thousand separators)
function formatBillNumber(num) {
    if (num === null || num === undefined || isNaN(num)) return '0';
    return num.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, " ");
}

// Display tariff details in the UI
function displayTariffDetails(template, mode) {
    const prefix = mode === 'sector' ? 'sector_' : '';
    
    // Show details section
    const detailsDisplay = document.getElementById(`${prefix}tariff_details_display`);
    if (detailsDisplay) {
        detailsDisplay.style.display = 'block';
    }
    
    // Update header info
    const nameDisplay = document.getElementById(`${prefix}tariff_template_name_display`);
    if (nameDisplay) nameDisplay.textContent = template.name || '—';
    
    // Update template name display next to dropdown (for sector mode)
    if (mode === 'sector') {
        const templateNameDisplay = document.getElementById('sector_template_name_display');
        if (templateNameDisplay) {
            templateNameDisplay.textContent = template.name || '—';
        }
    }
    
    const billingTypeDisplay = document.getElementById(`${prefix}tariff_billing_type_display`);
    if (billingTypeDisplay) {
        const billingType = template.billing_type || 'MONTHLY';
        billingTypeDisplay.textContent = `Billing Type: ${billingType === 'MONTHLY' ? 'Period to Period' : 'Date to Date'}`;
    }
    
    const billingDayDisplay = document.getElementById(`${prefix}tariff_billing_day_display`);
    if (billingDayDisplay) billingDayDisplay.textContent = `Billing Day: ${template.billing_day || '—'}`;
    
    const vatRateDisplay = document.getElementById(`${prefix}tariff_vat_rate_display`);
    if (vatRateDisplay) vatRateDisplay.textContent = `VAT: ${(template.vat_rate || 15).toFixed(1)}%`;
    
    // Display tiers
    const tiersTbody = document.getElementById(`${prefix}tariff_tiers_display`);
    if (tiersTbody && template.tiers && template.tiers.length > 0) {
        tiersTbody.innerHTML = '';
        template.tiers.forEach((tier, index) => {
            const row = tiersTbody.insertRow();
            row.innerHTML = `
                <td>Tier ${index + 1}</td>
                <td style="text-align:right;">${tier.max ? formatBillNumber(tier.max) : '∞'}</td>
                <td style="text-align:right;">R ${(tier.rate || 0).toFixed(2)}</td>
            `;
        });
    } else if (tiersTbody) {
        tiersTbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding:12px; color:var(--muted);">No tiers available</td></tr>';
    }
    
    // Display fixed costs
    const fixedCostsTbody = document.getElementById(`${prefix}tariff_fixed_costs_display`);
    if (fixedCostsTbody) {
        if (template.fixed_costs && template.fixed_costs.length > 0) {
            fixedCostsTbody.innerHTML = '';
            template.fixed_costs.forEach(cost => {
                const row = fixedCostsTbody.insertRow();
                row.innerHTML = `
                    <td>${cost.name || 'Fixed Cost'}</td>
                    <td style="text-align:right;">${formatCurrency(cost.value || 0)}</td>
                `;
            });
        } else {
            fixedCostsTbody.innerHTML = '<tr><td colspan="2" style="text-align:center; padding:12px; color:var(--muted);">No fixed costs</td></tr>';
        }
    }
    
    // Display customer costs
    const customerCostsTbody = document.getElementById(`${prefix}tariff_customer_costs_display`);
    if (customerCostsTbody) {
        if (template.customer_costs && template.customer_costs.length > 0) {
            customerCostsTbody.innerHTML = '';
            template.customer_costs.forEach(cost => {
                const row = customerCostsTbody.insertRow();
                row.innerHTML = `
                    <td>${cost.name || 'Customer Cost'}</td>
                    <td style="text-align:right;">${formatCurrency(cost.value || 0)}</td>
                `;
            });
        } else {
            customerCostsTbody.innerHTML = '<tr><td colspan="2" style="text-align:center; padding:12px; color:var(--muted);">No customer costs</td></tr>';
        }
    }
    
    // Display additional charges
    const additionalChargesDiv = document.getElementById(`${prefix}tariff_additional_charges_display`);
    if (additionalChargesDiv) {
        const allAdditional = [
            ...(template.waterin_additional || []).map(c => ({ ...c, type: 'Water In' })),
            ...(template.waterout_additional || []).map(c => ({ ...c, type: 'Water Out' }))
        ];
        
        if (allAdditional.length > 0) {
            let html = '<table style="width:100%; border-collapse:collapse;"><tbody>';
            allAdditional.forEach(charge => {
                const chargeLabel = charge.title || charge.name || 'Additional Charge';
                const chargeDesc = charge.percentage ? `${chargeLabel} (${charge.percentage}%)` : chargeLabel;
                html += `
                    <tr>
                        <td>${chargeDesc}</td>
                        <td style="text-align:right;">${charge.cost ? formatCurrency(charge.cost) : 'Percentage-based'}</td>
                    </tr>
                `;
            });
            html += '</tbody></table>';
            additionalChargesDiv.innerHTML = html;
        } else {
            additionalChargesDiv.textContent = 'No additional charges';
        }
    }
    
    // Hide any errors
    const errorDisplay = document.getElementById(`${prefix}tariff_error`);
    if (errorDisplay) {
        errorDisplay.style.display = 'none';
        errorDisplay.textContent = '';
    }
}

// Load tariff template details
window.loadTariffTemplate = async function(templateId, mode) {
    if (!templateId) {
        window.clearTariffTemplate(mode);
        return;
    }
    
    try {
        const template = await LaravelAPI.getTariffTemplateDetails(templateId);
        
        // Store globally
        currentTariffTemplate = template;
        currentTemplateTiers = template.tiers || [];
        
        // Ensure dropdown value matches selected template (for UI consistency)
        const dropdown = document.getElementById(`${mode === 'sector' ? 'sector_' : ''}tariff_template_select`);
        if (dropdown) {
            dropdown.value = templateId;
        }
        
        // Display in UI
        displayTariffDetails(template, mode);
        
        // Update Calculate button state (enable it now that template is loaded)
        updateCalculateButtonState();
        
        // Auto-switch billing mode if template requires it
        const templateBillingType = template.billing_type || 'MONTHLY';
        const requiredMode = templateBillingType === 'MONTHLY' ? 'period' : 'sector';
        if (typeof window.switchBillingMode === 'function') {
            window.switchBillingMode(requiredMode);
        }
        
        // Trigger recalculation if readings exist
        if (mode === 'period' && typeof BillingEngineUI !== 'undefined' && BillingEngineUI.render) {
            BillingEngineUI.render();
        } else if (mode === 'sector' && typeof SectorBillingUI !== 'undefined' && SectorBillingUI.render) {
            SectorBillingUI.render();
            // Initialize date picker when switching to sector mode
            if (typeof SectorBillingUI.updateDatePickerDefault === 'function') {
                SectorBillingUI.updateDatePickerDefault();
            }
        }
        
        // Save to localStorage
        localStorage.setItem('billing_calculator_template_id', templateId);
        
        // Update reset button visibility
        updateResetButtonVisibility();
        
        // Update bill preview
        if (typeof window.updateBillPreview === 'function') {
            window.updateBillPreview();
        }
    } catch (error) {
        console.error('Error loading tariff template:', error);
        window.showTariffError(error.message || 'Failed to load tariff template', mode);
    }
};

// Clear tariff template
window.clearTariffTemplate = function(mode) {
    const prefix = mode === 'sector' ? 'sector_' : '';
    
    currentTariffTemplate = null;
    currentTemplateTiers = null;
    
    // Update Calculate button state (disable it now that template is cleared)
    updateCalculateButtonState();
    
    // Hide details display
    const detailsDisplay = document.getElementById(`${prefix}tariff_details_display`);
    if (detailsDisplay) {
        detailsDisplay.style.display = 'none';
    }
    
    // Clear dropdown
    const dropdown = document.getElementById(`${prefix}tariff_template_select`);
    if (dropdown) {
        dropdown.value = '';
    }
    
    // Clear template name display (for sector mode)
    if (mode === 'sector') {
        const templateNameDisplay = document.getElementById('sector_template_name_display');
        if (templateNameDisplay) {
            templateNameDisplay.textContent = '—';
        }
    }
    
    // Remove from localStorage
    localStorage.removeItem('billing_calculator_template_id');
    
    // Update reset button visibility
    updateResetButtonVisibility();
    
    // Trigger recalculation
    if (mode === 'period' && typeof BillingEngineUI !== 'undefined' && BillingEngineUI.render) {
        BillingEngineUI.render();
    } else if (mode === 'sector' && typeof SectorBillingUI !== 'undefined' && SectorBillingUI.render) {
        SectorBillingUI.render();
    }
    
    // Update bill preview
    if (typeof window.updateBillPreview === 'function') {
        window.updateBillPreview();
    }
};

// Reset tariff template
window.resetTariffTemplate = function(mode) {
    // Auto-confirm for automated testing (prevents blocking dialogs)
    // TODO: Consider making this conditional based on test environment
    // if (confirm('Reset tariff template? This will clear the selected template.')) {
        window.clearTariffTemplate(mode);
    // }
};

// Show tariff error
window.showTariffError = function(message, mode) {
    const prefix = mode === 'sector' ? 'sector_' : '';
    const errorDisplay = document.getElementById(`${prefix}tariff_error`);
    if (errorDisplay) {
        errorDisplay.textContent = message;
        errorDisplay.style.display = 'block';
    }
};

// Update reset button visibility
function updateResetButtonVisibility() {
    const hasTemplate = currentTariffTemplate !== null;
    const resetBtn = document.getElementById('reset_template_btn');
    const sectorResetBtn = document.getElementById('sector_reset_template_btn');
    
    if (resetBtn) {
        resetBtn.style.display = hasTemplate ? 'block' : 'none';
    }
    if (sectorResetBtn) {
        sectorResetBtn.style.display = hasTemplate ? 'block' : 'none';
    }
}

// Update Calculate button state based on template selection
// This function works for BOTH "Period to Period" and "Date to Date" modes
function updateCalculateButtonState() {
    // Determine current mode (Period to Period or Date to Date)
    const periodContainer = document.getElementById('period-mode-container');
    const sectorContainer = document.getElementById('sector-mode-container');
    const isPeriodMode = periodContainer && periodContainer.style.display !== 'none';
    const isSectorMode = sectorContainer && sectorContainer.style.display !== 'none';
    
    // Get buttons and error divs for both modes
    const periodCalculateBtn = document.getElementById('calculate_btn');
    const sectorCalculateBtn = document.getElementById('sector_calculate_btn');
    const periodErrorDiv = document.getElementById('calculate_error');
    const sectorErrorDiv = document.getElementById('sector_calculate_error');
    
    // Update Period to Period button
    if (periodCalculateBtn && isPeriodMode) {
        // Check template for Period to Period mode
        const dropdown = document.getElementById('tariff_template_select');
        const detailsDisplay = document.getElementById('tariff_details_display');
        
        const hasTemplateVisible = detailsDisplay && detailsDisplay.style.display !== 'none';
        const hasDropdownValue = dropdown && dropdown.value && dropdown.value !== '';
        const hasTemplateTiers = typeof currentTemplateTiers !== 'undefined' && 
                                currentTemplateTiers !== null && 
                                currentTemplateTiers.length > 0;
        const hasTemplate = (hasTemplateVisible || hasDropdownValue) && hasTemplateTiers;
        
        if (hasTemplate) {
            periodCalculateBtn.disabled = false;
            periodCalculateBtn.title = 'Calculate billing periods';
            if (periodErrorDiv) {
                periodErrorDiv.style.display = 'none';
                periodErrorDiv.textContent = '';
            }
        } else {
            periodCalculateBtn.disabled = true;
            periodCalculateBtn.title = 'Please select a tariff template first';
            if (periodErrorDiv) {
                periodErrorDiv.textContent = '⚠️ Please select a tariff template to enable calculations';
                periodErrorDiv.style.display = 'block';
                periodErrorDiv.style.color = 'var(--orange, #f59e0b)';
            }
        }
    }
    
    // Update Date to Date button
    if (sectorCalculateBtn && isSectorMode) {
        // Check template for Date to Date mode
        const dropdown = document.getElementById('sector_tariff_template_select');
        const detailsDisplay = document.getElementById('sector_tariff_details_display');
        
        const hasTemplateVisible = detailsDisplay && detailsDisplay.style.display !== 'none';
        const hasDropdownValue = dropdown && dropdown.value && dropdown.value !== '';
        const hasTemplateTiers = typeof currentTemplateTiers !== 'undefined' && 
                                currentTemplateTiers !== null && 
                                currentTemplateTiers.length > 0;
        const hasTemplate = (hasTemplateVisible || hasDropdownValue) && hasTemplateTiers;
        
        if (hasTemplate) {
            sectorCalculateBtn.disabled = false;
            sectorCalculateBtn.title = 'Calculate billing for selected dates';
            if (sectorErrorDiv) {
                sectorErrorDiv.style.display = 'none';
                sectorErrorDiv.textContent = '';
            }
        } else {
            sectorCalculateBtn.disabled = true;
            sectorCalculateBtn.title = 'Please select a tariff template first';
            if (sectorErrorDiv) {
                sectorErrorDiv.textContent = '⚠️ Please select a tariff template to enable calculations';
                sectorErrorDiv.style.display = 'block';
                sectorErrorDiv.style.color = 'var(--orange, #f59e0b)';
            }
        }
    }
}

// Restore template from localStorage
function restoreTemplateFromStorage() {
    try {
        const savedTemplateId = localStorage.getItem('billing_calculator_template_id');
        if (savedTemplateId) {
            const periodDropdown = document.getElementById('tariff_template_select');
            const sectorDropdown = document.getElementById('sector_tariff_template_select');
            
            // Set dropdown values
            if (periodDropdown) {
                periodDropdown.value = savedTemplateId;
            }
            if (sectorDropdown) {
                sectorDropdown.value = savedTemplateId;
            }
            
            // Load the template (will determine mode automatically)
            window.loadTariffTemplate(savedTemplateId, 'period');
        }
    } catch (error) {
        console.error('Error restoring template from storage:', error);
    }
}

// Calculate bill and update preview
function calculateBill() {
    if (!currentTariffTemplate) {
        document.getElementById('bill_no_data').style.display = 'block';
        return;
    }
    
    // Get readings based on current mode
    const periodContainer = document.getElementById('period-mode-container');
    const isPeriodMode = periodContainer && periodContainer.style.display !== 'none';
    
    let readings = [];
    if (isPeriodMode) {
        // Get period readings
        const periods = typeof BillingEngineLogic !== 'undefined' ? BillingEngineLogic.get_periods() : [];
        periods.forEach(period => {
            if (period.readings && period.readings.length > 0) {
                period.readings.forEach(reading => {
                    readings.push({
                        date: reading.date,
                        value: reading.value
                    });
                });
            }
        });
    } else {
        // Get sector readings
        const sectors = typeof SectorBillingLogic !== 'undefined' ? SectorBillingLogic.getSectors() : [];
        const activeSector = typeof SectorBillingLogic !== 'undefined' ? SectorBillingLogic.getActiveSector() : null;
        if (activeSector !== null && sectors[activeSector]) {
            const sector = sectors[activeSector];
            if (sector.readings && sector.readings.length > 0) {
                sector.readings.forEach(reading => {
                    readings.push({
                        date: reading.date,
                        value: reading.value
                    });
                });
            }
        }
    }
    
    if (readings.length === 0) {
        document.getElementById('bill_no_data').style.display = 'block';
        return;
    }
    
    document.getElementById('bill_no_data').style.display = 'none';
    
    // Calculate bill using template
    const billingMode = isPeriodMode ? 'PERIOD_TO_PERIOD' : 'DATE_TO_DATE';
    
    // Use tiers from template
    const tiers = currentTemplateTiers || [];
    
    // Calculate consumption charges
    let totalUsage = 0;
    if (readings.length > 1) {
        totalUsage = readings[readings.length - 1].value - readings[0].value;
    }
    
    // Calculate tier costs
    let consumptionTotal = 0;
    const tierBreakdown = [];
    let remainingUsage = totalUsage;
    
    tiers.forEach((tier, index) => {
        const min = tier.min || 0;
        const max = tier.max === null || tier.max === undefined ? Infinity : tier.max;
        const rate = tier.rate || 0;
        
        let used = 0;
        if (remainingUsage > 0) {
            const tierMax = max === Infinity ? remainingUsage : Math.min(max - min, remainingUsage);
            used = Math.max(0, tierMax);
            remainingUsage -= used;
        }
        
        const charge = used * (rate / 1000); // Convert R/kL to R/L
        consumptionTotal += charge;
        
        tierBreakdown.push({
            tier: `Tier ${index + 1}`,
            used: used,
            rate: rate,
            charge: charge
        });
    });
    
    // Get fixed costs
    const fixedCost = parseFloat(currentTariffTemplate.fixed_cost || 0);
    const customerCost = parseFloat(currentTariffTemplate.customer_cost || 0);
    const additionalCost = parseFloat(currentTariffTemplate.additional_cost || 0);
    
    // Calculate water out charges
    const waterOutTiers = currentTariffTemplate.water_out || [];
    let waterOutTotal = 0;
    let waterOutRelatedTotal = 0;
    
    if (waterOutTiers.length > 0) {
        waterOutTiers.forEach(tier => {
            const min = tier.min || 0;
            const max = tier.max === null || tier.max === undefined ? Infinity : tier.max;
            const percentage = tier.percentage || 0;
            const cost = tier.cost || 0;
            
            if (totalUsage >= min && (max === Infinity || totalUsage <= max)) {
                const waterOutCharge = totalUsage * (percentage / 100) * (cost / 1000);
                waterOutTotal += waterOutCharge;
                
                // Water out related cost (if applicable)
                const relatedPercentage = tier.related_percentage || 0;
                if (relatedPercentage > 0) {
                    waterOutRelatedTotal += totalUsage * (relatedPercentage / 100) * (cost / 1000);
                }
            }
        });
    }
    
    // Calculate subtotal
    let subtotal = consumptionTotal + fixedCost + customerCost + additionalCost + waterOutTotal + waterOutRelatedTotal;
    
    // Calculate VAT
    const vatRate = parseFloat(currentTariffTemplate.vat_rate || 0);
    let vatAmount = 0;
    if (vatRate > 0 && subtotal > 0) {
        vatAmount = subtotal * (vatRate / 100);
    }
    
    // Calculate total
    const total = subtotal + vatAmount;
    
    // Update bill preview HTML
    let billHtml = '<div style="max-width:800px; margin:0 auto;">';
    
    // Consumption Charges
    if (consumptionTotal > 0) {
        billHtml += '<div style="margin-bottom:24px;">';
        billHtml += '<h3 style="font-size:18px; font-weight:700; margin-bottom:12px;">Consumption Charges</h3>';
        billHtml += '<table style="width:100%; border-collapse:collapse; margin-bottom:12px;">';
        billHtml += '<thead><tr style="border-bottom:2px solid var(--border);"><th style="text-align:left; padding:8px;">Tier</th><th style="text-align:right; padding:8px;">Usage (L)</th><th style="text-align:right; padding:8px;">Rate (R/kL)</th><th style="text-align:right; padding:8px;">Charge (R)</th></tr></thead>';
        billHtml += '<tbody>';
        tierBreakdown.forEach(item => {
            if (item.used > 0) {
                billHtml += `<tr><td style="padding:8px;">${item.tier}</td><td style="text-align:right; padding:8px;">${item.used.toLocaleString()}</td><td style="text-align:right; padding:8px;">R ${item.rate.toFixed(2)}</td><td style="text-align:right; padding:8px;">R ${item.charge.toFixed(2)}</td></tr>`;
            }
        });
        billHtml += '</tbody></table>';
        billHtml += `<div style="text-align:right; font-weight:700; padding:8px; border-top:2px solid var(--border);">Consumption Charges Subtotal: R ${consumptionTotal.toFixed(2)}</div>`;
        billHtml += '</div>';
    }
    
    // Fixed Cost
    if (fixedCost > 0) {
        billHtml += '<div style="margin-bottom:24px;">';
        billHtml += '<h3 style="font-size:18px; font-weight:700; margin-bottom:12px;">Fixed Charges</h3>';
        billHtml += `<div style="text-align:right; font-weight:700; padding:8px;">R ${fixedCost.toFixed(2)}</div>`;
        billHtml += '</div>';
    }
    
    // Customer Cost
    if (customerCost > 0) {
        billHtml += '<div style="margin-bottom:24px;">';
        billHtml += '<h3 style="font-size:18px; font-weight:700; margin-bottom:12px;">Customer Charges</h3>';
        billHtml += `<div style="text-align:right; font-weight:700; padding:8px;">R ${customerCost.toFixed(2)}</div>`;
        billHtml += '</div>';
    }
    
    // Additional Cost
    if (additionalCost > 0) {
        billHtml += '<div style="margin-bottom:24px;">';
        billHtml += '<h3 style="font-size:18px; font-weight:700; margin-bottom:12px;">Additional Charges</h3>';
        billHtml += `<div style="text-align:right; font-weight:700; padding:8px;">R ${additionalCost.toFixed(2)}</div>`;
        billHtml += '</div>';
    }
    
    // Water Out Charges
    if (waterOutTotal > 0) {
        billHtml += '<div style="margin-bottom:24px;">';
        billHtml += '<h3 style="font-size:18px; font-weight:700; margin-bottom:12px;">Water Out Charges</h3>';
        billHtml += `<div style="text-align:right; font-weight:700; padding:8px;">R ${waterOutTotal.toFixed(2)}</div>`;
        billHtml += '</div>';
    }
    
    // Water Out Related Charges
    if (waterOutRelatedTotal > 0) {
        billHtml += '<div style="margin-bottom:24px;">';
        billHtml += '<h3 style="font-size:18px; font-weight:700; margin-bottom:12px;">Water Out Related Charges</h3>';
        billHtml += `<div style="text-align:right; font-weight:700; padding:8px;">R ${waterOutRelatedTotal.toFixed(2)}</div>`;
        billHtml += '</div>';
    }
    
    // Subtotal
    billHtml += '<div style="margin-bottom:24px; padding-top:16px; border-top:2px solid var(--border);">';
    billHtml += `<div style="text-align:right; font-weight:700; font-size:18px; padding:8px;">Subtotal: R ${subtotal.toFixed(2)}</div>`;
    billHtml += '</div>';
    
    // VAT
    if (vatRate > 0 && subtotal > 0) {
        billHtml += '<div style="margin-bottom:24px;">';
        billHtml += `<h3 style="font-size:18px; font-weight:700; margin-bottom:12px;">VAT (${vatRate}%)</h3>`;
        billHtml += `<div style="text-align:right; font-weight:700; padding:8px;">R ${vatAmount.toFixed(2)}</div>`;
        billHtml += '</div>';
    }
    
    // Total
    billHtml += '<div style="margin-bottom:24px; padding-top:16px; border-top:3px solid var(--blue);">';
    billHtml += `<div style="text-align:right; font-weight:700; font-size:24px; padding:8px; color:var(--blue);">Total: R ${total.toFixed(2)}</div>`;
    billHtml += '</div>';
    
    billHtml += '</div>';
    
    document.getElementById('bill_preview_content').innerHTML = billHtml;
}

// Update bill preview (called from render functions)
window.updateBillPreview = function() {
    // Only update if bill preview is visible
    const billPreviewContainer = document.getElementById('bill-preview-container');
    if (billPreviewContainer && billPreviewContainer.style.display !== 'none') {
        calculateBill();
    }
};

// Tab navigation handlers
function showDashboard() {
    // Navigate to admin dashboard
    window.location.href = '{{ url("/admin") }}';
}

function showPeriodsReadings() {
    showDashboard(); // Same as dashboard for now
    updateActiveNavItem('Periods and Readings');
}

function showSummary() {
    showDashboard(); // Same as dashboard for now
    updateActiveNavItem('Summary');
}

function showBillPreview() {
    hideAllSections();
    document.getElementById('bill-preview-container').style.display = 'block';
    updateActiveNavItem('Bill Preview');
    calculateBill(); // Recalculate when showing
}

function hideAllSections() {
    const periodContainer = document.getElementById('period-mode-container');
    const sectorContainer = document.getElementById('sector-mode-container');
    const billPreviewContainer = document.getElementById('bill-preview-container');
    
    if (periodContainer) periodContainer.style.display = 'none';
    if (sectorContainer) sectorContainer.style.display = 'none';
    if (billPreviewContainer) billPreviewContainer.style.display = 'none';
}

function updateActiveNavItem(activeText) {
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
        if (item.textContent.trim() === activeText || 
            (activeText === 'Bill Preview' && item.id === 'bill-preview-tab')) {
            item.classList.add('active');
        }
    });
}

// Function to switch billing mode from dropdown
window.switchBillingModeFromDropdown = function(mode) {
    if (typeof window.switchBillingMode === 'function') {
        window.switchBillingMode(mode);
    }
    // Update calculate button state when switching modes
    updateCalculateButtonState();
};

// Override switchBillingMode from external JS to ensure it's available
if (typeof window.switchBillingMode === 'undefined') {
    // Fallback if external JS didn't define it
    window.switchBillingMode = function(mode) {
        const billingMode = mode;
        
        // Update tab buttons
        document.querySelectorAll('.mode-tab').forEach(tab => {
            if (tab.dataset.mode === mode) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });
        
        // Show/hide containers
        const periodContainer = document.getElementById('period-mode-container');
        const sectorContainer = document.getElementById('sector-mode-container');
        
        if (periodContainer) {
            periodContainer.style.display = mode === 'period' ? '' : 'none';
        }
        
        if (sectorContainer) {
            sectorContainer.style.display = mode === 'sector' ? '' : 'none';
            
            // Ensure dashboard is visible when sector mode is active
            if (mode === 'sector') {
                const dashboardEl = document.getElementById('sector_dashboard');
                if (dashboardEl) {
                    dashboardEl.style.display = 'block';
                    if (typeof SectorBillingUI !== 'undefined' && SectorBillingUI.updateDashboard) {
                        SectorBillingUI.updateDashboard();
                    }
                }
            }
        }
        
        // Update billing mode dropdowns to reflect current mode
        const billingModeSelect = document.getElementById('billing_mode_select');
        const billingModeSelectPeriod = document.getElementById('billing_mode_select_period');
        if (billingModeSelect) {
            billingModeSelect.value = mode;
        }
        if (billingModeSelectPeriod) {
            billingModeSelectPeriod.value = mode;
        }
        
        // Update template dropdowns
        if (typeof updateTemplateDropdowns === 'function') {
            updateTemplateDropdowns();
        }
        
        // Re-render appropriate UI
        if (mode === 'period') {
            if (typeof BillingEngineUI !== 'undefined' && BillingEngineUI.render) {
                BillingEngineUI.render();
            }
        } else {
            if (typeof SectorBillingUI !== 'undefined' && SectorBillingUI.render) {
                SectorBillingUI.render();
            }
        }
    };
}

// Preload account data if provided via query parameters
@if(isset($preloadAccountData) && $preloadAccountData)
    currentAccountData = @json($preloadAccountData);
    const preloadUserId = {{ $preloadUserId ?? 'null' }};
    const preloadAccountId = {{ $preloadAccountId ?? 'null' }};
@else
    const preloadUserId = null;
    const preloadAccountId = null;
@endif

// Hook into existing render functions (defer until DOM is ready)
document.addEventListener('DOMContentLoaded', function() {
    // Preload user/account if provided via query parameters
    if (preloadUserId && document.getElementById('user_select')) {
        document.getElementById('user_select').value = preloadUserId;
        if (typeof window.loadUserAccounts === 'function') {
            window.loadUserAccounts(preloadUserId);
        }
    }
    if (preloadAccountId && document.getElementById('account_select')) {
        setTimeout(() => {
            document.getElementById('account_select').value = preloadAccountId;
            if (typeof window.loadAccountDetails === 'function') {
                window.loadAccountDetails(preloadAccountId);
            }
        }, 500);
    }
    
    // Load users on page load
    window.loadUsers();
    // Hook BillingEngineUI.render
    if (typeof BillingEngineUI !== 'undefined' && BillingEngineUI.render) {
        const originalBillingEngineRender = BillingEngineUI.render;
        BillingEngineUI.render = function() {
            const result = originalBillingEngineRender.call(this);
            if (typeof window.updateBillPreview === 'function') {
                window.updateBillPreview();
            }
            return result;
        };
    }
    
    // Hook SectorBillingUI.render
    if (typeof SectorBillingUI !== 'undefined' && SectorBillingUI.render) {
        const originalSectorBillingRender = SectorBillingUI.render;
        SectorBillingUI.render = function() {
            const result = originalSectorBillingRender.call(this);
            if (typeof window.updateBillPreview === 'function') {
                window.updateBillPreview();
            }
            return result;
        };
    }
    
    // Hook SectorBillingUI.updateDashboard
    if (typeof SectorBillingUI !== 'undefined' && SectorBillingUI.updateDashboard) {
        const originalUpdateDashboard = SectorBillingUI.updateDashboard;
        SectorBillingUI.updateDashboard = function() {
            const result = originalUpdateDashboard.call(this);
            if (typeof window.updateBillPreview === 'function') {
                window.updateBillPreview();
            }
            return result;
        };
    }
    
    // Initialize Calculate button state on page load
    // Will be updated when template is loaded
    updateCalculateButtonState();
    
    // Initialize Comparison Test button state
    if (typeof updateComparisonTestButtonState === 'function') {
        updateComparisonTestButtonState();
    }
    
    // Load tariff templates on page load
    if (typeof window.loadTariffTemplates === 'function') {
        window.loadTariffTemplates().then(() => {
            // Restore template from localStorage after templates are loaded
            // loadTariffTemplate will call updateCalculateButtonState() when template loads
            restoreTemplateFromStorage();
        }).catch(() => {
            // If loading templates fails, ensure button state is still initialized
            updateCalculateButtonState();
        });
    }
    
    // Ensure dashboard is visible if in sector mode
    const sectorContainer = document.getElementById('sector-mode-container');
    if (sectorContainer && sectorContainer.style.display !== 'none') {
        const dashboardEl = document.getElementById('sector_dashboard');
        if (dashboardEl) {
            dashboardEl.style.display = 'block';
            if (typeof SectorBillingUI !== 'undefined' && SectorBillingUI.updateDashboard) {
                SectorBillingUI.updateDashboard();
            }
        }
        // Initialize date picker if in sector mode
        if (typeof SectorBillingUI !== 'undefined' && SectorBillingUI.updateDatePickerDefault) {
            SectorBillingUI.updateDatePickerDefault();
        }
    }
    
    // Initialize date picker on page load (regardless of mode)
    setTimeout(() => {
        if (typeof SectorBillingUI !== 'undefined' && SectorBillingUI.updateDatePickerDefault) {
            SectorBillingUI.updateDatePickerDefault();
            // Update formatted date display after date picker is initialized
            updateFormattedDateDisplay();
        }
    }, 100);
    
    // Function to format date as "1st Jan 2026"
    function formatDateForDisplay(dateString) {
        if (!dateString) return '—';
        try {
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return '—';
            
            // Use SectorBillingLogic.formatDate if available, otherwise format manually
            if (typeof SectorBillingLogic !== 'undefined' && SectorBillingLogic.formatDate) {
                return SectorBillingLogic.formatDate(date);
            }
            
            // Manual formatting as fallback
            const day = date.getDate();
            const suffix =
                day % 10 === 1 && day !== 11 ? "st" :
                day % 10 === 2 && day !== 12 ? "nd" :
                day % 10 === 3 && day !== 13 ? "rd" : "th";
            const month = date.toLocaleString("en-GB", {month: "short"});
            const year = date.getFullYear();
            return `${day}${suffix} ${month} ${year}`;
        } catch (error) {
            console.error('Error formatting date:', error);
            return '—';
        }
    }
    
    // Function to update formatted date display (make it globally accessible)
    window.updateFormattedDateDisplay = function() {
        const datePicker = document.getElementById('sector_date_picker');
        const formattedDisplay = document.getElementById('sector_date_formatted_display');
        
        if (datePicker && formattedDisplay) {
            const formattedDate = formatDateForDisplay(datePicker.value);
            formattedDisplay.textContent = formattedDate;
        }
    };
    
    // Alias for easier access
    function updateFormattedDateDisplay() {
        window.updateFormattedDateDisplay();
    }
    
    // Add event listener to date picker to update formatted display
    const datePicker = document.getElementById('sector_date_picker');
    if (datePicker) {
        datePicker.addEventListener('change', function() {
            updateFormattedDateDisplay();
            // Also update date picker default in case it was changed
            if (typeof SectorBillingUI !== 'undefined' && SectorBillingUI.updateDatePickerDefault) {
                SectorBillingUI.updateDatePickerDefault();
            }
        });
        
        datePicker.addEventListener('input', function() {
            updateFormattedDateDisplay();
        });
    }
    
    // Function to add start reading and create Period 1
    window.addStartReading = function() {
        const startReadingInput = document.getElementById('start_reading_input');
        if (!startReadingInput) return;
        
        // Check if already locked
        if (startReadingInput.disabled) {
            alert('Start reading has already been set. Period 1 is already open.');
            return;
        }
        
        const newStartReading = parseFloat(startReadingInput.value) || 0;
        
        // Validate: ensure value is positive
        if (newStartReading <= 0) {
            alert('Please enter a valid start reading (greater than 0).');
            startReadingInput.focus();
            return;
        }
        
        if (newStartReading < 0) {
            alert('Start reading cannot be negative. Setting to 0.');
            startReadingInput.value = 0;
            return;
        }
        
        // Get start date
        const datePicker = document.getElementById('sector_date_picker');
        if (!datePicker || !datePicker.value) {
            alert('Please select a start date first.');
            datePicker?.focus();
            return;
        }
        
        const startDate = new Date(datePicker.value);
        startDate.setHours(0, 0, 0, 0);
        const startDateStr = SectorBillingLogic.iso(startDate);
        
        // Check if period already exists
        if (typeof SectorBillingLogic !== 'undefined') {
            const sectors = SectorBillingLogic.getSectors();
            
            if (sectors.length > 0) {
                alert('Period 1 already exists. Start reading cannot be changed.');
                return;
            }
            
            // Create first sector (Period 1) with this start reading
            const newSectorId = 1;
            sectors.push({
                sector_id: newSectorId,
                start_date: startDate,
                end_date: null,
                start_reading: newStartReading,
                end_reading: newStartReading, // Initially same as start
                total_usage: 0,
                daily_usage: 0,
                days: 0,
                status: 'OPEN',
                readings: [{ date: startDateStr, value: newStartReading }], // Opening reading
                tier_cost: 0,
                tier_items: []
            });
            
            SectorBillingLogic.setSectors(sectors);
            SectorBillingLogic.setActiveSector(0);
            
            SectorBillingUI.save_revision('Period 1 Created', `Period 1 started on ${startDateStr} with start reading ${newStartReading} L`);
            
            // Recalculate sector
            if (typeof SectorBillingLogic.calculateSector === 'function') {
                SectorBillingLogic.calculateSector(0);
            }
            
            // Lock the start reading field
            startReadingInput.disabled = true;
            startReadingInput.style.backgroundColor = '#f3f4f6';
            startReadingInput.style.cursor = 'not-allowed';
            
            // Hide the ADD button
            const addBtn = document.getElementById('add_start_reading_btn');
            if (addBtn) {
                addBtn.style.display = 'none';
            }
            
            // Re-render UI
            if (typeof SectorBillingUI !== 'undefined' && SectorBillingUI.render) {
                SectorBillingUI.render();
            }
        }
    };
    
    // Add Start Reading field validation (no auto-create)
    const startReadingInput = document.getElementById('start_reading_input');
    if (startReadingInput) {
        startReadingInput.addEventListener('blur', function() {
            if (this.disabled) return; // Already locked
            
            // Validate: ensure value is not negative
            const value = parseFloat(this.value) || 0;
            if (value < 0) {
                alert('Start reading cannot be negative. Setting to 0.');
                this.value = 0;
            }
        });
    }
});

// ==================== END BILL PREVIEW FUNCTIONS ====================

// ==================== USER/ACCOUNT SELECTION ====================
let currentUsers = [];
let currentAccountData = null;

window.loadUsers = async function() {
    try {
        currentUsers = await LaravelAPI.getUsers();
        const userSelect = document.getElementById('user_select');
        if (!userSelect) return;
        
        userSelect.innerHTML = '<option value="">-- Select User --</option>';
        currentUsers.forEach(user => {
            const option = document.createElement('option');
            option.value = user.id;
            option.textContent = user.name + ' (' + user.email + ')';
            userSelect.appendChild(option);
        });
    } catch (error) {
        console.error('Error loading users:', error);
    }
};

window.loadUserAccounts = function(userId) {
    const accountSelect = document.getElementById('account_select');
    if (!accountSelect) return;
    
    accountSelect.innerHTML = '<option value="">-- Select Account --</option>';
    accountSelect.disabled = true;
    
    if (!userId) {
        document.getElementById('user_info_section').style.display = 'none';
        document.getElementById('bill_display_section').style.display = 'none';
        return;
    }
    
    const userIdInt = parseInt(userId, 10);
    const user = currentUsers.find(function(u) { return parseInt(u.id, 10) === userIdInt; });
    if (!user || !user.accounts || user.accounts.length === 0) {
        alert('No accounts found for this user.');
        return;
    }
    
    user.accounts.forEach(function(account) {
        const option = document.createElement('option');
        option.value = account.id;
        option.textContent = account.account_name || 'Account #' + account.id;
        accountSelect.appendChild(option);
    });
    
    accountSelect.disabled = false;
};

window.loadAccountDetails = async function(accountId) {
    if (!accountId) {
        document.getElementById('user_info_section').style.display = 'none';
        document.getElementById('bill_display_section').style.display = 'none';
        return;
    }
    
    try {
        currentAccountData = await LaravelAPI.getAccountDetails(accountId);
        if (!currentAccountData) {
            alert('Error loading account details.');
            return;
        }
        
        const userInfo = currentAccountData.user;
        const siteInfo = currentAccountData.site;
        const accountInfo = currentAccountData.account;
        
        document.getElementById('user_name_display').textContent = userInfo.name || '—';
        document.getElementById('user_email_display').textContent = userInfo.email || '—';
        document.getElementById('user_contact_display').textContent = userInfo.contact_number || '—';
        document.getElementById('site_address_display').textContent = siteInfo.address || '—';
        document.getElementById('site_region_display').textContent = siteInfo.region || '—';
        document.getElementById('account_name_display').textContent = accountInfo.account_name || '—';
        document.getElementById('account_number_display').textContent = accountInfo.account_number || '—';
        document.getElementById('name_on_bill_display').textContent = accountInfo.name_on_bill || '—';
        
        document.getElementById('user_info_section').style.display = 'block';
        
        // Store bills and meters for later use (before template loads)
        // CRITICAL: Check both data.raw_bills and raw_bills (API might nest under 'data')
        const billsToLoad = currentAccountData.data?.raw_bills || currentAccountData.raw_bills || currentAccountData.data?.bills || currentAccountData.bills;
        const metersToLoad = currentAccountData.data?.meters || currentAccountData.meters;
        const lastFinalizedPeriod = currentAccountData.data?.last_finalized_period || currentAccountData.last_finalized_period;
        
        // DIAGNOSTIC: Log STATE 2 detection
        console.log('STATE 2 Detection Check:', {
            hasRawBills: !!currentAccountData.raw_bills,
            rawBillsLength: currentAccountData.raw_bills?.length || 0,
            hasDataRawBills: !!currentAccountData.data?.raw_bills,
            dataRawBillsLength: currentAccountData.data?.raw_bills?.length || 0,
            hasBills: !!currentAccountData.bills,
            billsLength: currentAccountData.bills?.length || 0,
            billsToLoadLength: billsToLoad?.length || 0,
            billsToLoad: billsToLoad,
            currentAccountDataKeys: Object.keys(currentAccountData),
            willUseState2: !!(billsToLoad && billsToLoad.length > 0)
        });
        
        if (accountInfo.tariff_template && accountInfo.tariff_template.id) {
            const templateId = accountInfo.tariff_template.id;
            if (typeof window.loadTariffTemplate === 'function') {
                const templateSelect = document.getElementById('tariff_template_select');
                if (templateSelect) {
                    templateSelect.value = templateId;
                    // Load template first, then load periods after template is loaded
                    window.loadTariffTemplate(templateId, 'period');
                    
                    // Load persisted periods into calculator AFTER template loads
                    // CRITICAL: Wait for template to be fully loaded and calculator ready
                    // Use a promise-based approach to avoid race conditions
                    const waitForTemplate = new Promise((resolve) => {
                        // Check if template is loaded (check if tiers are available)
                        const checkTemplate = setInterval(() => {
                            if (typeof currentTemplateTiers !== 'undefined' && currentTemplateTiers !== null && currentTemplateTiers.length > 0) {
                                clearInterval(checkTemplate);
                                resolve();
                            }
                        }, 100);
                        // Timeout after 5 seconds
                        setTimeout(() => {
                            clearInterval(checkTemplate);
                            resolve(); // Resolve anyway to avoid infinite wait
                        }, 5000);
                    });
                    
                    waitForTemplate.then(() => {
                        if (metersToLoad && metersToLoad.length > 0) {
                            if (billsToLoad && billsToLoad.length > 0) {
                                // STATE 2 exists: Load persisted billing state
                                if (typeof window.loadPersistedBillingState === 'function') {
                                    console.log('Loading persisted billing state (STATE 2):', {
                                        billsCount: billsToLoad.length,
                                        metersCount: metersToLoad.length
                                    });
                                    window.loadPersistedBillingState(billsToLoad, lastFinalizedPeriod, metersToLoad);
                                } else {
                                    console.error('loadPersistedBillingState function not found');
                                }
                            } else {
                                // STATE 2 does NOT exist: Construct from STATE 1 raw data (readings)
                                // CRITICAL: Use sequential population to respect calculator state transitions
                                if (typeof window.populateCalculatorSequentially === 'function') {
                                    // Use currentTariffTemplate (full template data) if available, otherwise fall back to account template
                                    const tariffTemplateToUse = currentTariffTemplate || currentAccountData.account.tariff_template;
                                    console.log('Sequentially populating calculator from STATE 1 raw data (readings):', {
                                        metersCount: metersToLoad.length,
                                        accountInfo: accountInfo,
                                        tariffTemplate: tariffTemplateToUse
                                    });
                                    // Call asynchronously to allow UI updates
                                    window.populateCalculatorSequentially(metersToLoad, accountInfo, tariffTemplateToUse).then(() => {
                                        console.log('Sequential population completed');
                                    }).catch(error => {
                                        console.error('Error during sequential population:', error);
                                    });
                                } else {
                                    console.error('populateCalculatorSequentially function not found');
                                }
                            }
                        }
                    }); // Wait for template to load
                }
            }
        } else {
            // No template, but still try to load periods if calculator is ready
            // Wait a short time for calculator to initialize
            const waitForCalculator = new Promise((resolve) => setTimeout(resolve, 500));
            waitForCalculator.then(() => {
                if (metersToLoad && metersToLoad.length > 0) {
                    if (billsToLoad && billsToLoad.length > 0) {
                        // STATE 2 exists: Load persisted billing state
                        if (typeof window.loadPersistedBillingState === 'function') {
                            window.loadPersistedBillingState(billsToLoad, lastFinalizedPeriod, metersToLoad);
                        }
                    } else {
                        // STATE 2 does NOT exist: Construct from STATE 1 raw data (readings)
                        // CRITICAL: Use sequential population to respect calculator state transitions
                        if (typeof window.populateCalculatorSequentially === 'function') {
                            console.log('Sequentially populating calculator from STATE 1 raw data (readings) - no template:', {
                                metersCount: metersToLoad.length,
                                accountInfo: accountInfo
                            });
                            // Call asynchronously to allow UI updates
                            window.populateCalculatorSequentially(metersToLoad, accountInfo, null).then(() => {
                                console.log('Sequential population completed');
                            }).catch(error => {
                                console.error('Error during sequential population:', error);
                            });
                        }
                        }
                    }
            });
        }
        
        window.renderBills(currentAccountData.bills);
        
    } catch (error) {
        console.error('Error loading account details:', error);
        alert('Error loading account details: ' + error.message);
    }
};

window.renderBills = function(bills) {
    const billsDisplay = document.getElementById('bills_display');
    const billSection = document.getElementById('bill_display_section');
    
    if (!billsDisplay || !billSection) return;
    
    if (!bills || bills.length === 0) {
        billsDisplay.innerHTML = '<div style="text-align:center; color:var(--muted); padding:20px;">No bills available</div>';
        billSection.style.display = 'none';
        return;
    }
    
    billSection.style.display = 'block';
    
    let html = '';
    bills.forEach(function(periodGroup, index) {
        const periodStart = new Date(periodGroup.period_start_date).toLocaleDateString();
        const periodEnd = new Date(periodGroup.period_end_date).toLocaleDateString();
        const status = periodGroup.status || 'UNKNOWN';
        const statusColor = status === 'ACTUAL' ? '#10b981' : status === 'CALCULATED' ? '#3b82f6' : status === 'PROVISIONAL' ? '#f59e0b' : '#6b7280';
        
        html += '<div style="margin-bottom:24px; padding:16px; background:var(--bg); border-radius:8px; border:1px solid var(--border);">';
        html += '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; padding-bottom:12px; border-bottom:2px solid var(--border);">';
        html += '<div><div style="font-size:18px; font-weight:700; color:var(--text);">Period ' + (index + 1) + '</div>';
        html += '<div style="font-size:14px; color:var(--muted); margin-top:4px;">' + periodStart + ' - ' + periodEnd + '</div></div>';
        html += '<div style="display:flex; align-items:center; gap:12px;">';
        html += '<span style="padding:4px 12px; background:' + statusColor + '20; color:' + statusColor + '; border-radius:4px; font-size:12px; font-weight:600;">' + status + '</span>';
        html += '<div style="text-align:right;"><div style="font-size:12px; color:var(--muted);">Total</div>';
        html += '<div style="font-size:20px; font-weight:700; color:var(--text);">R ' + periodGroup.total_amount.toFixed(2) + '</div></div></div></div>';
        
        periodGroup.meters.forEach(function(meterBill, meterIndex) {
            html += '<div style="margin-bottom:' + (meterIndex < periodGroup.meters.length - 1 ? '16px' : '0') + '; padding:' + (meterIndex < periodGroup.meters.length - 1 ? '0 0 16px 0' : '0') + '; border-bottom:' + (meterIndex < periodGroup.meters.length - 1 ? '1px solid var(--border)' : 'none') + ';">';
            html += '<div style="font-weight:600; color:var(--text); margin-bottom:8px;">' + meterBill.meter_title + ' (' + meterBill.meter_type + ') - #' + meterBill.meter_number + '</div>';
            html += '<table style="width:100%; border-collapse:collapse; font-size:14px;"><tbody>';
            html += '<tr><td style="padding:4px 0; color:var(--muted);">Consumption:</td><td style="padding:4px 0; text-align:right; color:var(--text); font-weight:500;">' + meterBill.consumption.toFixed(2) + ' ' + (meterBill.meter_type === 'Water' ? 'L' : 'kWh') + '</td></tr>';
            html += '<tr><td style="padding:4px 0; color:var(--muted);">Tiered Charge:</td><td style="padding:4px 0; text-align:right; color:var(--text); font-weight:500;">R ' + meterBill.tiered_charge.toFixed(2) + '</td></tr>';
            if (meterBill.fixed_costs_total > 0) {
                html += '<tr><td style="padding:4px 0; color:var(--muted);">Fixed Costs:</td><td style="padding:4px 0; text-align:right; color:var(--text); font-weight:500;">R ' + meterBill.fixed_costs_total.toFixed(2) + '</td></tr>';
            }
            html += '<tr><td style="padding:4px 0; color:var(--muted);">VAT:</td><td style="padding:4px 0; text-align:right; color:var(--text); font-weight:500;">R ' + meterBill.vat_amount.toFixed(2) + '</td></tr>';
            html += '<tr style="border-top:1px solid var(--border); margin-top:4px;"><td style="padding:8px 0 4px 0; color:var(--text); font-weight:600;">Total:</td><td style="padding:8px 0 4px 0; text-align:right; color:var(--text); font-weight:700; font-size:16px;">R ' + meterBill.total_amount.toFixed(2) + '</td></tr>';
            html += '</tbody></table></div>';
        });
        
        html += '</div>';
    });
    
    billsDisplay.innerHTML = html;
};

// ==================== SAVE BILLS FUNCTIONALITY ====================
window.saveBills = async function() {
    if (!currentAccountData || !currentAccountData.account) {
        showSaveBillsError('Please select a user and account first.');
        return;
    }

    const accountId = currentAccountData.account.id;
    const account = currentAccountData.account;
    
    // Get meters for this account
    if (!currentAccountData.meters || currentAccountData.meters.length === 0) {
        showSaveBillsError('No meters found for this account.');
        return;
    }

    // Get calculated periods from the calculator
    let periods = [];
    let billingMode = 'MONTHLY';

    // Check which mode is active
    const periodModeContainer = document.getElementById('period-mode-container');
    const sectorModeContainer = document.getElementById('sector-mode-container');
    
    if (periodModeContainer && periodModeContainer.style.display !== 'none') {
        // Period to Period mode
        billingMode = 'MONTHLY';
        if (typeof BillingEngineLogic !== 'undefined' && BillingEngineLogic.getPeriods) {
            periods = BillingEngineLogic.getPeriods();
        } else if (typeof BillingEngineLogic !== 'undefined' && BillingEngineLogic.get_periods) {
            periods = BillingEngineLogic.get_periods();
        } else {
            showSaveBillsError('Unable to retrieve calculated periods. Please calculate first.');
            return;
        }
    } else if (sectorModeContainer && sectorModeContainer.style.display !== 'none') {
        // Date to Date mode
        billingMode = 'DATE_TO_DATE';
        if (typeof SectorBillingLogic !== 'undefined' && SectorBillingLogic.getSectors) {
            const sectors = SectorBillingLogic.getSectors();
            periods = sectors.map(sector => ({
                start: sector.start_date,
                end: sector.end_date || null,
                status: sector.status || 'OPEN',
                total_usage: sector.total_usage || 0,
                projected_total: sector.tier_cost || 0,
                tier_breakdown: sector.tier_breakdown || [],
                readings: sector.readings || []
            }));
        } else {
            showSaveBillsError('Unable to retrieve calculated periods. Please calculate first.');
            return;
        }
    } else {
        showSaveBillsError('No billing mode selected. Please select Period to Period or Date to Date mode.');
        return;
    }

    if (!periods || periods.length === 0) {
        showSaveBillsError('No periods to save. Please calculate first.');
        return;
    }

    // Filter out open/future periods that shouldn't be saved yet
    // Only save closed periods (PROVISIONAL, ACTUAL, CALCULATED with end dates)
    const periodsToSave = periods.filter(p => {
        const periodEnd = p.end || p.end_date;
        if (!periodEnd || p.status === 'OPEN' || p.status === 'PROJECTED') {
            return false; // Don't save open/projected periods
        }
        return true;
    });

    if (periodsToSave.length === 0) {
        showSaveBillsError('No closed periods to save. Only closed periods can be saved.');
        return;
    }

    // For now, save bills for the first meter (can be extended to support multiple meters)
    const meter = currentAccountData.meters[0];
    if (!meter || !meter.id) {
        showSaveBillsError('No meter found for this account.');
        return;
    }

    // Prepare request data
    const requestData = {
        account_id: accountId,
        meter_id: meter.id,
        billing_mode: billingMode,
        periods: periodsToSave.map(p => ({
            start: p.start || p.start_date,
            end: p.end || p.end_date,
            status: p.status || 'PROVISIONAL',
            total_usage: p.total_usage || p.usage || 0,
            projected_total: p.projected_total || p.tier_cost || p.total_cost || 0,
            tier_breakdown: p.tier_breakdown || [],
            readings: p.readings || []
        }))
    };

    // Show loading state
    const saveBtn = document.getElementById('save_bills_btn') || document.getElementById('save_bills_btn_sector');
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.textContent = '💾 Saving...';
    }
    hideSaveBillsMessages();

    try {
        const response = await fetch('{{ route('billing-calculator.save-bills') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(requestData)
        });

        const data = await response.json();

        if (data.success) {
            let message = `Successfully saved ${data.saved_count} bill(s).`;
            if (data.skipped_count > 0) {
                message += ` ${data.skipped_count} bill(s) skipped (immutable).`;
            }
            showSaveBillsSuccess(message);
            
            // Reload account details to show updated bills
            if (accountId) {
                setTimeout(() => {
                    window.loadAccountDetails(accountId);
                }, 1000);
            }
        } else {
            showSaveBillsError(data.message || 'Failed to save bills.');
        }
    } catch (error) {
        console.error('Error saving bills:', error);
        showSaveBillsError('Error saving bills: ' + error.message);
    } finally {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = '💾 Save Bills';
        }
    }
};

function showSaveBillsError(message) {
    const errorDiv = document.getElementById('save_bills_error') || document.getElementById('save_bills_error_sector');
    const successDiv = document.getElementById('save_bills_success') || document.getElementById('save_bills_success_sector');
    if (errorDiv) {
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
    }
    if (successDiv) {
        successDiv.style.display = 'none';
    }
}

function showSaveBillsSuccess(message) {
    const errorDiv = document.getElementById('save_bills_error') || document.getElementById('save_bills_error_sector');
    const successDiv = document.getElementById('save_bills_success') || document.getElementById('save_bills_success_sector');
    if (successDiv) {
        successDiv.textContent = message;
        successDiv.style.display = 'block';
    }
    if (errorDiv) {
        errorDiv.style.display = 'none';
    }
}

function hideSaveBillsMessages() {
    const errorDiv = document.getElementById('save_bills_error') || document.getElementById('save_bills_error_sector');
    const successDiv = document.getElementById('save_bills_success') || document.getElementById('save_bills_success_sector');
    if (errorDiv) errorDiv.style.display = 'none';
    if (successDiv) successDiv.style.display = 'none';
}

function updateSaveBillsButtonState() {
    const periodContainer = document.getElementById('save_bills_container');
    const sectorContainer = document.getElementById('save_bills_container_sector');
    const periodBtn = document.getElementById('save_bills_btn');
    const sectorBtn = document.getElementById('save_bills_btn_sector');

    // Check if conditions are met
    const hasAccount = currentAccountData !== null && currentAccountData.account;
    const hasMeters = hasAccount && currentAccountData.meters && currentAccountData.meters.length > 0;
    
    // Check if calculation has been performed
    let hasCalculation = false;
    const periodModeContainer = document.getElementById('period-mode-container');
    const sectorModeContainer = document.getElementById('sector-mode-container');
    
    if (periodModeContainer && periodModeContainer.style.display !== 'none') {
        if (typeof BillingEngineLogic !== 'undefined' && BillingEngineLogic.getPeriods) {
            const periods = BillingEngineLogic.getPeriods();
            hasCalculation = periods && periods.length > 0;
        } else if (typeof BillingEngineLogic !== 'undefined' && BillingEngineLogic.get_periods) {
            const periods = BillingEngineLogic.get_periods();
            hasCalculation = periods && periods.length > 0;
        }
    } else if (sectorModeContainer && sectorModeContainer.style.display !== 'none') {
        if (typeof SectorBillingLogic !== 'undefined' && SectorBillingLogic.getSectors) {
            const sectors = SectorBillingLogic.getSectors();
            hasCalculation = sectors && sectors.length > 0;
        }
    }

    const shouldShow = hasAccount && hasMeters && hasCalculation;

    if (periodContainer) {
        periodContainer.style.display = shouldShow ? 'block' : 'none';
    }
    if (sectorContainer) {
        sectorContainer.style.display = shouldShow ? 'block' : 'none';
    }
    if (periodBtn) {
        periodBtn.disabled = !shouldShow;
    }
    if (sectorBtn) {
        sectorBtn.disabled = !shouldShow;
    }
}

// Update save button state when account is loaded or calculation is performed
const originalLoadAccountDetails = window.loadAccountDetails;
window.loadAccountDetails = async function(accountId) {
    if (originalLoadAccountDetails) {
        await originalLoadAccountDetails(accountId);
    }
    setTimeout(updateSaveBillsButtonState, 500);
};

// Update save button state after calculation
const originalCalculate = window.calculate;
if (originalCalculate) {
    window.calculate = function() {
        originalCalculate();
        setTimeout(updateSaveBillsButtonState, 500);
        setTimeout(updateComparisonTestButtonState, 500);
    };
}

// Update comparison test button state
function updateComparisonTestButtonState() {
    const testBtn = document.getElementById('comparison_test_btn');
    if (!testBtn) return;
    
    const periods = typeof BillingEngineLogic !== 'undefined' && BillingEngineLogic.getPeriods 
        ? BillingEngineLogic.getPeriods() 
        : (typeof periods !== 'undefined' ? periods : []);
    const hasPeriods = periods && periods.length > 0;
    const hasReadings = periods && periods.some(p => p.readings && p.readings.length > 0);
    
    if (hasPeriods && hasReadings) {
        testBtn.disabled = false;
        testBtn.title = 'Compare JavaScript calculator output with PHP calculator';
    } else {
        testBtn.disabled = true;
        testBtn.title = 'Please calculate periods with readings first';
    }
}

const originalCalculateSector = window.calculate_sector;
if (originalCalculateSector) {
    window.calculate_sector = function() {
        originalCalculateSector();
        setTimeout(updateSaveBillsButtonState, 500);
        setTimeout(updateComparisonTestButtonState, 500);
    };
}

// ==================== LOAD PERSISTED BILLING STATE ====================
/**
 * Load persisted billing state into calculator
 * This function loads historical periods from DB without recalculating them
 * Only calculates open/future periods
 */
window.loadPersistedBillingState = function(bills, lastFinalizedPeriod, meters) {
    console.log('loadPersistedBillingState called with:', {
        billsCount: bills ? bills.length : 0,
        metersCount: meters ? meters.length : 0,
        lastFinalizedPeriod: lastFinalizedPeriod
    });
    
    if (!bills || bills.length === 0 || !meters || meters.length === 0) {
        console.warn('loadPersistedBillingState: No bills or meters to load', {
            bills: bills,
            meters: meters
        });
        return; // No persisted bills or meters to load
    }

    // Collect all readings from all meters
    const allReadings = [];
    meters.forEach(meter => {
        if (meter.readings && meter.readings.length > 0) {
            meter.readings.forEach(reading => {
                allReadings.push({
                    date: reading.reading_date,
                    value: parseFloat(reading.reading_value) || 0
                });
            });
        }
    });

    // Sort readings by date
    allReadings.sort((a, b) => new Date(a.date) - new Date(b.date));

    // Handle both grouped bills (from renderBills) and raw_bills (from API)
    // If bills have a 'meters' array, it's grouped format, otherwise it's raw format
    const isGroupedFormat = bills.length > 0 && bills[0].meters && Array.isArray(bills[0].meters);
    
    // Convert to flat list of bills if grouped format
    let flatBills = [];
    if (isGroupedFormat) {
        bills.forEach(group => {
            group.meters.forEach(meterBill => {
                flatBills.push({
                    period_start_date: group.period_start_date,
                    period_end_date: group.period_end_date,
                    billing_mode: group.billing_mode,
                    status: group.status,
                    meter_id: meterBill.meter_id,
                    consumption: meterBill.consumption,
                    tiered_charge: meterBill.tiered_charge,
                    fixed_costs_total: meterBill.fixed_costs_total,
                    vat_amount: meterBill.vat_amount,
                    total_amount: meterBill.total_amount,
                    tier_breakdown: []
                });
            });
        });
    } else {
        flatBills = bills;
    }

    // Convert bills to periods that the calculator can display
    // Sort bills by period_start_date ascending (oldest first)
    const sortedBills = [...flatBills].sort((a, b) => new Date(a.period_start_date) - new Date(b.period_start_date));

    const historicalPeriods = sortedBills.map((bill, index) => {
        const periodStart = new Date(bill.period_start_date);
        periodStart.setHours(0, 0, 0, 0);
        
        // Period end is exclusive in calculator, so add 1 day
        const periodEnd = new Date(bill.period_end_date);
        periodEnd.setDate(periodEnd.getDate() + 1);
        periodEnd.setHours(0, 0, 0, 0);

        // Get readings for this period
        const periodReadings = allReadings.filter(r => {
            const readingDate = new Date(r.date);
            readingDate.setHours(0, 0, 0, 0);
            return readingDate >= periodStart && readingDate < periodEnd;
        }).map(r => ({
            date: r.date,
            value: r.value
        }));

        // Calculate opening and closing readings
        let openingReading = 0;
        let closingReading = 0;
        if (periodReadings.length > 0) {
            openingReading = periodReadings[0].value;
            closingReading = periodReadings[periodReadings.length - 1].value;
        } else if (index > 0) {
            // If no readings in this period, use closing reading from previous period
            const prevBill = sortedBills[index - 1];
            const prevPeriodEnd = new Date(prevBill.period_end_date);
            const prevReadings = allReadings.filter(r => {
                const readingDate = new Date(r.date);
                return readingDate <= prevPeriodEnd;
            });
            if (prevReadings.length > 0) {
                openingReading = prevReadings[prevReadings.length - 1].value;
                closingReading = openingReading;
            }
        }

        // Get consumption from bill or calculate from readings
        const usage = bill.consumption || (closingReading - openingReading) || 0;
        
        // Map status
        let status = 'PROVISIONAL';
        if (bill.status === 'ACTUAL') {
            status = 'ACTUAL';
        } else if (bill.status === 'CALCULATED') {
            status = 'CALCULATED';
        }

        // Calculate days in period
        const periodEndDisplay = new Date(bill.period_end_date);
        const daysInPeriod = Math.ceil((periodEndDisplay - periodStart) / (1000 * 60 * 60 * 24)) + 1;
        const dailyUsage = daysInPeriod > 0 ? usage / daysInPeriod : 0;

        return {
            start: periodStart.toISOString().slice(0, 10),
            end: periodEnd.toISOString().slice(0, 10),
            start_reading: openingReading,
            opening: openingReading,
            closing: closingReading,
            end_reading: closingReading,
            usage: usage,
            total_usage: usage,
            dailyUsage: dailyUsage,
            status: status,
            readings: periodReadings,
            original_provisional_usage: bill.status === 'PROVISIONAL' ? usage : null,
            projected_total: bill.total_amount || 0,
            tier_breakdown: bill.tier_breakdown || [],
            isFromState2: true, // Mark as STATE 2 (persisted bill)
            bill_id: bill.bill_id || bill.id || null, // Store bill ID for updates
            sector_readings: bill.sector_readings || null // Store sector readings from bill
        };
    });

    // Inject periods into calculator if in Period-to-Period mode
    // First, we need to ensure readings are loaded into the calculator's readings table
    // The calculator uses a readings table structure that we need to populate
    if (typeof BillingEngineLogic !== 'undefined') {
        console.log('BillingEngineLogic found, injecting periods...', {
            historicalPeriodsCount: historicalPeriods.length,
            historicalPeriods: historicalPeriods
        });
        
        // Get current periods (might be empty)
        const currentPeriods = BillingEngineLogic.getPeriods() || [];
        console.log('Current periods from calculator:', currentPeriods.length);
        
        // Combine historical periods with any current periods (for open periods)
        // Historical periods should come first (sorted by date)
        const allPeriods = [...historicalPeriods, ...currentPeriods];
        console.log('All periods (historical + current):', allPeriods.length, allPeriods);
        
        // Set periods in calculator
        if (BillingEngineLogic.setPeriods) {
            console.log('Setting periods in BillingEngineLogic...');
            BillingEngineLogic.setPeriods(allPeriods);
            console.log('Periods set. Verifying:', BillingEngineLogic.getPeriods().length);
        } else {
            console.error('BillingEngineLogic.setPeriods is not available!');
        }
        
        // Also need to ensure readings are available in the calculator's reading system
        // The calculator may use a different reading storage mechanism
        // For now, periods contain their readings, which should be sufficient
        
        // Render the periods if UI is available
        setTimeout(() => {
            console.log('Attempting to render periods in UI...');
            if (typeof BillingEngineUI !== 'undefined' && BillingEngineUI.render) {
                console.log('Calling BillingEngineUI.render()...');
                BillingEngineUI.render();
                console.log('BillingEngineUI.render() completed');
            } else {
                console.error('BillingEngineUI.render is not available!', {
                    BillingEngineUI: typeof BillingEngineUI,
                    render: typeof BillingEngineUI !== 'undefined' ? typeof BillingEngineUI.render : 'undefined'
                });
            }
        }, 100);

        console.log('Loaded persisted billing state:', {
            historicalPeriodsCount: historicalPeriods.length,
            totalPeriodsCount: allPeriods.length,
            readingsCount: allReadings.length,
            lastFinalizedDate: lastFinalizedPeriod ? lastFinalizedPeriod.period_end_date : null
        });
    } else {
        console.error('BillingEngineLogic is not defined! Cannot load persisted billing state.');
    }
};
// ==================== END LOAD PERSISTED BILLING STATE ====================

// ==================== SEQUENTIAL PERIOD-BY-PERIOD POPULATION ====================
/**
 * Populate calculator sequentially, period by period, respecting calculator state transitions.
 * 
 * RULE ZERO (NON-NEGOTIABLE):
 * - The Billing Calculator is the sole source of truth
 * - Population must respect calculator state transitions
 * - NO bulk or parallel population
 * - NO skipping intermediate states
 * - NO asynchronous racing
 * 
 * Required behavior:
 * 1. Start with the earliest reading
 * 2. Create Period 1
 * 3. Add readings belonging to Period 1 ONLY
 * 4. Allow the calculator to compute sectors, derive closing reading, determine status
 * 5. WAIT for Period 1 to fully resolve
 * 6. Pass Period 1 closing reading as opening for Period 2
 * 7. Repeat for Period 2, Period 3, etc.
 * 
 * ABSOLUTE RULE:
 * The next period MUST NOT begin computation until the previous period is fully computed and stable.
 * 
 * @param {Array} meters - Array of meter objects with readings (STATE 1 raw data)
 * @param {Object} accountInfo - Account information (bill_day, billing_type, etc.)
 * @param {Object} tariffTemplate - Tariff template object (optional, may be loaded separately)
 */
window.populateCalculatorSequentially = async function(meters, accountInfo, tariffTemplate) {
    console.log('populateCalculatorSequentially called:', {
        metersCount: meters ? meters.length : 0,
        accountInfo: accountInfo,
        tariffTemplate: tariffTemplate
    });
    
    if (!meters || meters.length === 0) {
        console.warn('populateCalculatorSequentially: No meters provided');
        return;
    }
    
    // Check if calculator logic is available
    if (typeof BillingEngineLogic === 'undefined') {
        console.error('populateCalculatorSequentially: BillingEngineLogic is not defined!');
        return;
    }
    
    // CRITICAL: Filter meters by tariff template type (water vs electricity)
    // The calculator processes ONE meter type at a time based on the tariff template
    const tariffIsWater = tariffTemplate && (tariffTemplate.is_water === true || tariffTemplate.is_water === 1);
    const tariffIsElectricity = tariffTemplate && (tariffTemplate.is_electricity === true || tariffTemplate.is_electricity === 1);
    
    // Filter meters to match tariff template type
    // Backend sends meter_type as a string field, not meterTypes object
    const matchingMeters = meters.filter(meter => {
        // Check both meter_type (string) and meterTypes.title (object) for compatibility
        const meterTypeStr = (meter.meter_type || (meter.meterTypes && meter.meterTypes.title) || '').toLowerCase();
        const isWaterMeter = meterTypeStr.includes('water');
        const isElectricityMeter = meterTypeStr.includes('electric') || meterTypeStr.includes('electricity');
        
        if (tariffIsWater && isWaterMeter) {
            return true;
        }
        if (tariffIsElectricity && isElectricityMeter) {
            return true;
        }
        return false;
    });
    
    if (matchingMeters.length === 0) {
        console.warn('populateCalculatorSequentially: No meters match tariff template type', {
            tariffIsWater: tariffIsWater,
            tariffIsElectricity: tariffIsElectricity,
            availableMeters: meters.map(m => ({
                id: m.id,
                type: m.meter_type || (m.meterTypes ? m.meterTypes.title : 'Unknown')
            }))
        });
        return;
    }
    
    console.log('populateCalculatorSequentially: Filtered meters by tariff type:', {
        tariffIsWater: tariffIsWater,
        tariffIsElectricity: tariffIsElectricity,
        matchingMetersCount: matchingMeters.length,
        matchingMeters: matchingMeters.map(m => ({
            id: m.id,
            type: m.meter_type || (m.meterTypes ? m.meterTypes.title : 'Unknown')
        }))
    });
    
    // Collect all readings from matching meters only (STATE 1 raw data)
    // CRITICAL: Process readings in strict chronological order, month by month
    // Backend already orders by reading_date ASC, but we ensure proper sorting here too
    const allReadings = [];
    matchingMeters.forEach(meter => {
        if (meter.readings && meter.readings.length > 0) {
            // Backend should already be ordered, but ensure chronological order per meter
            const meterReadings = [...meter.readings].sort((a, b) => {
                const dateA = new Date(a.reading_date);
                const dateB = new Date(b.reading_date);
                if (dateA.getTime() !== dateB.getTime()) {
                    return dateA - dateB;
                }
                // If same date, ensure reading values increment (for same date, higher value comes after)
                return parseFloat(a.reading_value || 0) - parseFloat(b.reading_value || 0);
            });
            
            meterReadings.forEach(reading => {
                allReadings.push({
                    date: reading.reading_date,
                    value: parseFloat(reading.reading_value) || 0,
                    meter_id: meter.id
                });
            });
        }
    });
    
    // Sort all readings by date (strict chronological order)
    // CRITICAL: This ensures we process month by month, not rushing ahead
    allReadings.sort((a, b) => {
        const dateA = new Date(a.date);
        const dateB = new Date(b.date);
        if (dateA.getTime() !== dateB.getTime()) {
            return dateA - dateB;
        }
        // If same date, ensure reading values increment (for same date, higher value comes after)
        return a.value - b.value;
    });
    
    if (allReadings.length === 0) {
        console.warn('populateCalculatorSequentially: No readings found in meters');
        return;
    }
    
    console.log('populateCalculatorSequentially: Collected readings (chronologically sorted):', {
        readingsCount: allReadings.length,
        firstReading: allReadings[0],
        lastReading: allReadings[allReadings.length - 1],
        dateRange: {
            start: allReadings[0].date,
            end: allReadings[allReadings.length - 1].date
        }
    });
    
    // Determine billing type (MONTHLY or DATE_TO_DATE)
    const billingType = (tariffTemplate && tariffTemplate.billing_type) || 
                        (accountInfo && accountInfo.tariff_template && accountInfo.tariff_template.billing_type) ||
                        'MONTHLY';
    
    const isDateToDate = billingType === 'DATE_TO_DATE';
    const billDay = accountInfo && accountInfo.bill_day ? parseInt(accountInfo.bill_day, 10) : null;
    
    console.log('populateCalculatorSequentially: Billing configuration:', {
        billingType: billingType,
        isDateToDate: isDateToDate,
        billDay: billDay
    });
    
    // Clear existing periods - start fresh
    BillingEngineLogic.setPeriods([]);
    
    if (isDateToDate) {
        // DATE_TO_DATE: Use SectorBillingLogic if available
        // For now, we'll handle this separately - DATE_TO_DATE uses sectors, not periods
        console.warn('populateCalculatorSequentially: DATE_TO_DATE mode not yet fully implemented using calculator logic');
        // TODO: Implement DATE_TO_DATE using SectorBillingLogic sequential population
        return;
    } else {
        // MONTHLY: Use calculator's add_period() function
        if (!billDay || billDay < 1 || billDay > 31) {
            console.error('populateCalculatorSequentially: MONTHLY billing requires valid bill_day (1-31)');
            return;
        }
        
        // Ensure DOM elements exist and are set
        let billDayEl = document.getElementById('bill_day');
        let startMonthEl = document.getElementById('start_month');
        
        if (!billDayEl) {
            console.error('populateCalculatorSequentially: bill_day element not found');
            return;
        }
        if (!startMonthEl) {
            console.error('populateCalculatorSequentially: start_month element not found');
            return;
        }
        
        // Set bill_day value
        billDayEl.value = billDay;
        
        // Determine first period start date from first reading
        const firstReading = new Date(allReadings[0].date);
        firstReading.setHours(0, 0, 0, 0);
        
        // Find the period that contains the first reading
        let periodStart = new Date(firstReading);
        periodStart.setDate(billDay);
        if (periodStart > firstReading) {
            // Period start is after first reading, go back one month
            periodStart.setMonth(periodStart.getMonth() - 1);
        }
        
        // Set start_month value (YYYY-MM format) for first period only
        const startYear = periodStart.getFullYear();
        const startMonth = periodStart.getMonth() + 1;
        startMonthEl.value = `${startYear}-${String(startMonth).padStart(2, '0')}`;
        
        const lastReading = new Date(allReadings[allReadings.length - 1].date);
        lastReading.setHours(0, 0, 0, 0);
        
        // Generate periods using calculator's add_period() function
        // CRITICAL: GLOBAL EXECUTION PACING RULE - Process sequentially, month by month
        // - No parallel processing
        // - No speculative execution
        // - Each period must complete fully before next begins
        // - Readings processed one at a time in strict chronological order
        
        let readingIndex = 0; // Track which readings we've processed (sequential pointer)
        let periodNumber = 0;
        
        // STEP 1: Process periods one at a time, in strict chronological order
        // CRITICAL: Sequential processing - each period must fully resolve before next begins
        while (true) {
            periodNumber++;
            console.log(`populateCalculatorSequentially: [PERIOD ${periodNumber}] Starting period construction...`);
            
            try {
                // STEP 2: Create period using calculator's tested logic
                // This step must complete fully before proceeding
                BillingEngineLogic.add_period();
                
                // STEP 3: Validate period was created
                const periods = BillingEngineLogic.getPeriods();
                const newPeriod = periods[periods.length - 1];
                
                if (!newPeriod) {
                    console.error(`populateCalculatorSequentially: [PERIOD ${periodNumber}] FAILED: Period creation returned null`);
                    break;
                }
                
                // STEP 4: Extract period boundaries (must be resolved before reading assignment)
                const periodStartDate = new Date(newPeriod.start);
                periodStartDate.setHours(0, 0, 0, 0);
                const periodEndDate = new Date(newPeriod.end);
                periodEndDate.setHours(0, 0, 0, 0);
                
                console.log(`populateCalculatorSequentially: [PERIOD ${periodNumber}] Period boundaries resolved:`, {
                    start: periodStartDate.toISOString().slice(0, 10),
                    end: periodEndDate.toISOString().slice(0, 10),
                    readingIndex: readingIndex,
                    remainingReadings: allReadings.length - readingIndex
                });
                
                // STEP 5: Process readings for this period ONE AT A TIME, in chronological order
                // CRITICAL: No batching, no parallel processing - sequential only
                const periodReadings = [];
                let readingsProcessedThisPeriod = 0;
                
                // Process readings sequentially from current index
                for (let i = readingIndex; i < allReadings.length; i++) {
                    const reading = allReadings[i];
                    const readingDate = new Date(reading.date);
                    readingDate.setHours(0, 0, 0, 0);
                    
                    // STEP 5a: Validate reading date is in forward timeline
                    if (i > readingIndex) {
                        const prevReading = allReadings[i - 1];
                        const prevDate = new Date(prevReading.date);
                        prevDate.setHours(0, 0, 0, 0);
                        if (readingDate < prevDate) {
                            console.error(`populateCalculatorSequentially: [PERIOD ${periodNumber}] VALIDATION FAILED: Reading ${i} date (${reading.date}) is before previous reading (${prevReading.date})`);
                            break;
                        }
                    }
                    
                    // STEP 5b: Validate reading value increments (cannot be lower than previous)
                    if (i > readingIndex && periodReadings.length > 0) {
                        const lastPeriodReading = periodReadings[periodReadings.length - 1];
                        if (reading.value < lastPeriodReading.value) {
                            console.error(`populateCalculatorSequentially: [PERIOD ${periodNumber}] VALIDATION FAILED: Reading ${i} value (${reading.value}) is lower than previous (${lastPeriodReading.value})`);
                            break;
                        }
                    }
                    
                    // STEP 5c: Check if reading belongs to this period
                    if (readingDate >= periodStartDate && readingDate < periodEndDate) {
                        // Reading belongs to this period - add it
                        periodReadings.push({
                            date: reading.date,
                            value: reading.value
                        });
                        readingIndex = i + 1; // Move pointer forward (sequential processing)
                        readingsProcessedThisPeriod++;
                        
                        console.log(`populateCalculatorSequentially: [PERIOD ${periodNumber}] Reading ${i + 1}/${allReadings.length} assigned to period:`, {
                            date: reading.date,
                            value: reading.value,
                            periodReadingsCount: periodReadings.length
                        });
                    } else if (readingDate >= periodEndDate) {
                        // Reading is in a future period - stop processing for this period
                        console.log(`populateCalculatorSequentially: [PERIOD ${periodNumber}] Reading ${i + 1} is in future period, stopping period assignment`);
                        break;
                    } else {
                        // Reading is before period start (shouldn't happen if sorted correctly)
                        console.warn(`populateCalculatorSequentially: [PERIOD ${periodNumber}] Reading ${i + 1} date (${reading.date}) is before period start (${periodStartDate.toISOString().slice(0, 10)})`);
                        // Skip this reading but don't increment index (it may belong to a previous period we missed)
                    }
                }
                
                // STEP 6: Add readings to period using calculator's API (must complete before next period)
                // CRITICAL: Add readings one by one using calculator's add_reading() function
                // This ensures proper state management and sector creation
                for (let rIdx = 0; rIdx < periodReadings.length; rIdx++) {
                    const reading = periodReadings[rIdx];
                    // Add reading slot using calculator API
                    BillingEngineLogic.add_reading();
                    // Set reading data directly in the period (calculator will process it on calculate())
                    const periods = BillingEngineLogic.getPeriods();
                    const activePeriod = periods[periods.length - 1];
                    if (activePeriod && activePeriod.readings.length > 0) {
                        const readingSlot = activePeriod.readings[activePeriod.readings.length - 1];
                        readingSlot.date = reading.date;
                        readingSlot.value = reading.value;
                    }
                }
                
                // STEP 6a: Calculate this period NOW (sequential - wait for completion)
                // CRITICAL: Calculate period immediately after adding readings
                // This ensures period is fully resolved before next period begins
                if (typeof BillingEngineLogic.calculate === 'function') {
                    try {
                        BillingEngineLogic.calculate();
                        console.log(`populateCalculatorSequentially: [PERIOD ${periodNumber}] Period calculated successfully`);
                        
                        // Verify period status
                        const periodsAfterCalc = BillingEngineLogic.getPeriods();
                        const calculatedPeriod = periodsAfterCalc[periodsAfterCalc.length - 1];
                        if (calculatedPeriod) {
                            console.log(`populateCalculatorSequentially: [PERIOD ${periodNumber}] Period status after calculation:`, {
                                status: calculatedPeriod.status,
                                opening: calculatedPeriod.opening,
                                closing: calculatedPeriod.closing,
                                usage: calculatedPeriod.usage
                            });
                        }
                    } catch (error) {
                        console.error(`populateCalculatorSequentially: [PERIOD ${periodNumber}] Error during calculation:`, error);
                        break; // Stop on calculation error
                    }
                }
                
                // STEP 6b: Wait briefly to ensure calculator state is stable before next period
                // This prevents race conditions and ensures period is fully resolved
                await new Promise(resolve => setTimeout(resolve, 100)); // 100ms delay between periods
                
                console.log(`populateCalculatorSequentially: [PERIOD ${periodNumber}] Period complete:`, {
                    periodNumber: periodNumber,
                    start: newPeriod.start,
                    end: newPeriod.end,
                    readingsCount: periodReadings.length,
                    readingIndex: readingIndex,
                    totalReadings: allReadings.length
                });
                
                // STEP 7: Validate we haven't skipped any readings
                if (readingIndex < allReadings.length) {
                    const nextReading = allReadings[readingIndex];
                    const nextReadingDate = new Date(nextReading.date);
                    nextReadingDate.setHours(0, 0, 0, 0);
                    
                    // If next reading is before period end, we have a problem
                    if (nextReadingDate < periodEndDate && nextReadingDate >= periodStartDate) {
                        console.error(`populateCalculatorSequentially: [PERIOD ${periodNumber}] VALIDATION FAILED: Next reading (${nextReading.date}) should have been assigned to this period`);
                    }
                }
                
                // STEP 8: Check termination conditions
                // Must check both: period end after last reading AND all readings processed
                if (periodEndDate > lastReading) {
                    console.log(`populateCalculatorSequentially: [PERIOD ${periodNumber}] Termination: Period end (${periodEndDate.toISOString().slice(0, 10)}) is after last reading (${lastReading.toISOString().slice(0, 10)})`);
                    break;
                }
                
                if (readingIndex >= allReadings.length) {
                    console.log(`populateCalculatorSequentially: [PERIOD ${periodNumber}] Termination: All readings processed (${readingIndex}/${allReadings.length})`);
                    break;
                }
                
                // STEP 9: Prepare for next period (sequential continuation)
                // Update start_month for next period (calculator uses previous period's end)
                const nextPeriodStart = new Date(periodEndDate);
                const nextYear = nextPeriodStart.getFullYear();
                const nextMonth = nextPeriodStart.getMonth() + 1;
                startMonthEl.value = `${nextYear}-${String(nextMonth).padStart(2, '0')}`;
                
                console.log(`populateCalculatorSequentially: [PERIOD ${periodNumber}] Ready for next period. start_month set to: ${startMonthEl.value}`);
                
            } catch (error) {
                console.error(`populateCalculatorSequentially: [PERIOD ${periodNumber}] ERROR:`, error);
                console.error(`populateCalculatorSequentially: [PERIOD ${periodNumber}] Error details:`, {
                    readingIndex: readingIndex,
                    periodsCount: BillingEngineLogic.getPeriods().length,
                    errorMessage: error.message,
                    errorStack: error.stack
                });
                break;
            }
        }
        
        console.log(`populateCalculatorSequentially: Period construction complete. Created ${BillingEngineLogic.getPeriods().length} periods, processed ${readingIndex}/${allReadings.length} readings`);
        
        // Final validation: Verify period chaining
        const finalPeriods = BillingEngineLogic.getPeriods();
        for (let pIdx = 1; pIdx < finalPeriods.length; pIdx++) {
            const prevPeriod = finalPeriods[pIdx - 1];
            const currentPeriod = finalPeriods[pIdx];
            if (prevPeriod.closing !== null && prevPeriod.closing !== undefined) {
                if (currentPeriod.opening !== prevPeriod.closing) {
                    console.warn(`populateCalculatorSequentially: Period ${pIdx + 1} opening (${currentPeriod.opening}) does not match period ${pIdx} closing (${prevPeriod.closing})`);
                }
            }
        }
        
        // Render UI after all periods are populated
        if (typeof BillingEngineUI !== 'undefined' && BillingEngineUI.render) {
            BillingEngineUI.render();
            console.log('populateCalculatorSequentially: UI rendered');
        }
        
        console.log('populateCalculatorSequentially: Sequential population complete', {
            periodsCreated: finalPeriods.length,
            readingsProcessed: readingIndex,
            totalReadings: allReadings.length
        });
    }
};

// ==================== LEGACY ALIAS FOR BACKWARDS COMPATIBILITY ====================
// Keep constructPeriodsFromState1 as alias to populateCalculatorSequentially
window.constructPeriodsFromState1 = window.populateCalculatorSequentially;

// ==================== END CONSTRUCT PERIODS FROM STATE 1 RAW DATA ====================

// ==================== PERIOD ACTION BUTTONS ====================
/**
 * Recalculate period from STATE 1 (raw data)
 * Ignores persisted bill and recalculates from readings
 */
window.recalculatePeriodFromState1 = async function(periodIndex) {
    if (!currentAccountData || !currentAccountData.account) {
        alert('Please select a user and account first.');
        return;
    }
    
    if (typeof BillingEngineLogic === 'undefined') {
        alert('Calculator not initialized. Please wait for calculator to load.');
        return;
    }
    
    const periods = BillingEngineLogic.getPeriods() || [];
    if (periodIndex < 0 || periodIndex >= periods.length) {
        alert('Invalid period index.');
        return;
    }
    
    const period = periods[periodIndex];
    
    // Confirm action
    if (!confirm(`Recalculate Period ${periodIndex + 1} from raw data? This will ignore the persisted bill and recalculate from readings.`)) {
        return;
    }
    
    // Get all readings from meters
    const allReadings = [];
    if (currentAccountData.meters && currentAccountData.meters.length > 0) {
        currentAccountData.meters.forEach(meter => {
            if (meter.readings && meter.readings.length > 0) {
                meter.readings.forEach(reading => {
                    allReadings.push({
                        date: reading.reading_date,
                        value: parseFloat(reading.reading_value) || 0
                    });
                });
            }
        });
    }
    
    // Sort readings by date
    allReadings.sort((a, b) => new Date(a.date) - new Date(b.date));
    
    // Recalculate this specific period using constructPeriodsFromState1 logic
    // But only for this one period
    const accountInfo = currentAccountData.account;
    const tariffTemplate = currentTariffTemplate || currentAccountData.account.tariff_template;
    
    // Mark period as recalculated (remove STATE 2 flag)
    period.isFromState2 = false;
    period.bill_id = null;
    period.sector_readings = null;
    
    // Trigger period recalculation
    // This will use the existing calculator logic to recalculate
    if (typeof BillingEngineLogic !== 'undefined' && BillingEngineLogic.recalculatePeriod) {
        BillingEngineLogic.recalculatePeriod(periodIndex);
    } else {
        // Fallback: reconstruct all periods from STATE 1
        console.log('Recalculating all periods from STATE 1...');
        window.constructPeriodsFromState1(currentAccountData.meters, accountInfo, tariffTemplate);
    }
    
    // Re-render output
    setTimeout(() => {
        if (typeof render_calculation_output === 'function') {
            render_calculation_output();
        }
        if (typeof BillingEngineUI !== 'undefined' && BillingEngineUI.render) {
            BillingEngineUI.render();
        }
    }, 500);
    
    console.log(`Period ${periodIndex + 1} recalculated from STATE 1`);
};

/**
 * Save current period (replaces existing STATE 2 if exists)
 */
window.saveCurrentPeriod = async function(periodIndex) {
    if (!currentAccountData || !currentAccountData.account) {
        alert('Please select a user and account first.');
        return;
    }
    
    if (typeof BillingEngineLogic === 'undefined') {
        alert('Calculator not initialized.');
        return;
    }
    
    const periods = BillingEngineLogic.getPeriods() || [];
    if (periodIndex < 0 || periodIndex >= periods.length) {
        alert('Invalid period index.');
        return;
    }
    
    const period = periods[periodIndex];
    
    // Check if period has end date (closed period)
    const periodEnd = new Date(period.end);
    periodEnd.setDate(periodEnd.getDate() - 1); // Period end is exclusive
    
    if (!period.end || period.status === 'OPEN') {
        alert('Cannot save open periods. Please close the period first.');
        return;
    }
    
    // Confirm action
    const action = period.isFromState2 ? 'update' : 'create';
    if (!confirm(`${action === 'update' ? 'Update' : 'Save'} Period ${periodIndex + 1}? This will ${action === 'update' ? 'replace' : 'create'} the persisted bill.`)) {
        return;
    }
    
    // Get meter
    const meter = currentAccountData.meters[0];
    if (!meter || !meter.id) {
        alert('No meter found for this account.');
        return;
    }
    
    // Get tariff
    const tariff = currentAccountData.account.tariff_template;
    if (!tariff || !tariff.id) {
        alert('No tariff template assigned to account.');
        return;
    }
    
    // Prepare period data for saving
    const periodData = {
        period_start_date: period.start,
        period_end_date: period.end,
        billing_mode: 'MONTHLY', // Default, could be determined from tariff
        status: period.status || 'PROVISIONAL',
        meter_id: meter.id,
        consumption: period.usage || 0,
        tiered_charge: 0, // Will be calculated by backend
        fixed_costs_total: 0,
        vat_amount: 0,
        total_amount: 0,
        tier_breakdown: period.tier_breakdown || [],
        readings: period.readings || [],
        sector_readings: period.sectors || []
    };
    
    // If updating existing bill, include bill_id
    if (period.bill_id) {
        periodData.bill_id = period.bill_id;
    }
    
    try {
        const response = await fetch('{{ route('billing-calculator.save-bills') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                account_id: currentAccountData.account.id,
                periods: [periodData],
                update_existing: true // Allow updating existing bills
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(`Period ${periodIndex + 1} saved successfully!`);
            
            // Mark period as STATE 2
            period.isFromState2 = true;
            if (data.bill_id) {
                period.bill_id = data.bill_id;
            }
            
            // Reload account to refresh STATE 2 data
            setTimeout(() => {
                window.reloadAccountWithoutRecalculation();
            }, 1000);
        } else {
            alert('Failed to save period: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error saving period:', error);
        alert('Error saving period: ' + error.message);
    }
};

/**
 * Reload account without recalculation (to verify STATE 2 persisted)
 */
window.reloadAccountWithoutRecalculation = async function() {
    if (!currentAccountData || !currentAccountData.account) {
        alert('Please select a user and account first.');
        return;
    }
    
    const accountId = currentAccountData.account.id;
    
    console.log('Reloading account without recalculation...');
    
    // Reload account details
    const accountData = await LaravelAPI.getAccountDetails(accountId);
    if (!accountData) {
        alert('Error loading account details.');
        return;
    }
    
    currentAccountData = accountData;
    
    // Get bills and meters
    const billsToLoad = accountData.data?.raw_bills || accountData.raw_bills || accountData.data?.bills || accountData.bills;
    const metersToLoad = accountData.data?.meters || accountData.meters;
    const lastFinalizedPeriod = accountData.data?.last_finalized_period || accountData.last_finalized_period;
    
    console.log('Reload - STATE 2 Detection:', {
        billsToLoadLength: billsToLoad?.length || 0,
        willUseState2: !!(billsToLoad && billsToLoad.length > 0)
    });
    
    // Load persisted state if STATE 2 exists, otherwise show message
    if (billsToLoad && billsToLoad.length > 0) {
        if (typeof window.loadPersistedBillingState === 'function') {
            window.loadPersistedBillingState(billsToLoad, lastFinalizedPeriod, metersToLoad);
            console.log('Reloaded STATE 2 (persisted bills)');
        }
    } else {
        alert('No persisted bills (STATE 2) found. All periods are calculated from raw data (STATE 1).');
        // Still try to construct from STATE 1 if needed
        if (typeof window.constructPeriodsFromState1 === 'function') {
            const tariffTemplate = currentTariffTemplate || accountData.account.tariff_template;
            window.constructPeriodsFromState1(metersToLoad, accountData.account, tariffTemplate);
        }
    }
    
    // Re-render
    setTimeout(() => {
        if (typeof render_calculation_output === 'function') {
            render_calculation_output();
        }
        if (typeof BillingEngineUI !== 'undefined' && BillingEngineUI.render) {
            BillingEngineUI.render();
        }
    }, 500);
};

// ==================== END PERIOD ACTION BUTTONS ====================
// ==================== END SAVE BILLS FUNCTIONALITY ====================
// ==================== END USER/ACCOUNT SELECTION ====================
// ==================== END LARAVEL API INTEGRATION ====================
</script>

</body>
</html>

