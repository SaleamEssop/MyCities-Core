<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>CalculatorPHP (Rev1) - Clean Implementation</title>
  <!-- CalculatorPHP - NEW ISOLATED IMPLEMENTATION -->
  <!-- Totally isolated from billing-calculator-php.blade.php -->
  <!-- All calculations performed by CalculatorPHP backend (PHP) -->
  <link rel="stylesheet" href="{{ url('/css/billing-calculator.css') }}?v={{ time() }}">
  <!-- Flatpickr CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <style>
    .search-result-item:hover {
        background: #f8fafc !important;
        transform: translateX(5px);
    }
    .search-result-item {
        transition: all 0.2s ease;
    }
    #search_results::-webkit-scrollbar {
        width: 8px;
    }
    #search_results::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 0 12px 12px 0;
    }
    #search_results::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }
    #search_results::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
    .mode-btn.active {
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06) !important;
    }
  </style>

<body>
  <div class="app">
    <!-- SIDEBAR -->
    <div class="sidebar">
      <div class="sidebar-header">
        <h2>MyCities</h2>
        <div class="sidebar-subtitle">CalculatorPHP (Rev1) - Clean</div>
      </div>

      <div class="nav-section">
        <div class="nav-label">Navigation</div>
        <div class="nav-item" onclick="window.CalculatorPHPUI.showDashboard()">
          <span class="nav-icon">📊</span>
          <span>Dashboard</span>
        </div>
        <div class="nav-item" onclick="window.CalculatorPHPUI.showPeriodsReadings()">
          <span class="nav-icon">📅</span>
          <span>Periods and Readings</span>
        </div>
        <div class="nav-item" onclick="window.CalculatorPHPUI.showSummary()">
          <span class="nav-icon">📋</span>
          <span>Summary</span>
        </div>
        <div class="nav-item" id="bill-preview-tab" onclick="window.CalculatorPHPUI.showBillPreview()">
          <span class="nav-icon">🧾</span>
          <span>Bill Preview</span>
        </div>
      </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
      <!-- TOP BAR -->
      <div class="top-bar" style="display: flex; justify-content: space-between; align-items: center;">
        <!-- CalculatorPHP Mode Indicator -->
        <div style="padding: 8px 16px; background: #e3f2fd; border-radius: 4px; font-size: 12px; color: #1976d2;">
          <strong>CalculatorPHP (Rev1)</strong> - Clean Implementation
        </div>

        <!-- MODE SWITCHER -->
        <div class="mode-switcher"
          style="display: flex; background: var(--border); border-radius: 8px; padding: 2px; gap: 2px;">
          <button id="mode_test_btn" class="mode-btn active" onclick="window.CalculatorPHPUI.switchMode('test')"
            style="padding: 6px 16px; border: none; background: white; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 12px; color: #1e3a8a; box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: all 0.2s;">
            🧪 Test Bill
          </button>
          <button id="mode_account_btn" class="mode-btn" onclick="window.CalculatorPHPUI.switchMode('account')"
            style="padding: 6px 16px; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 12px; color: var(--muted); transition: all 0.2s;">
            👤 User Account
          </button>
        </div>
      </div>

      <!-- ACCOUNT SEARCH (Hidden in Test Mode) -->
      <div id="account_search_section" class="section"
        style="display:none; margin-bottom:24px; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);">
        <div class="section-content" style="padding: 20px;">
          <div style="position: relative; max-width: 800px; margin: 0 auto;">
            <div
              style="font-weight: 700; color: #1e3a8a; margin-bottom: 12px; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">
              Search for Account</div>
            <div style="position: relative;">
              <span style="position: absolute; left: 15px; top: 12px; font-size: 18px;">🔍</span>
              <input type="text" id="account_search_input" placeholder="Search by name, account #, address or phone..."
                style="width: 100%; padding: 12px 15px 12px 45px; border-radius: 12px; border: 1px solid #cbd5e1; background: white; color: var(--text); font-size: 15px; outline: none; transition: all 0.2s; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);"
                oninput="window.CalculatorPHPUI.handleAccountSearch(this.value)">

              <div id="search_results"
                style="position: absolute; top: calc(100% + 5px); left: 0; right: 0; background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); z-index: 1000; display: none; max-height: 450px; overflow-y: auto;">
                <!-- Results dynamically populated -->
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- TEST BILL USER SELECTION (Only visible in Test Mode) -->
      <div id="test_bill_selection_section" class="section" style="margin-bottom:24px;">
        <div class="section-header">🧪 Quick Start: Select User & Account <small
            style="font-weight: normal; color: var(--muted);">(Optional for simulation)</small></div>
        <div class="section-content">
          <div style="display:flex; gap:20px; align-items:flex-end; flex-wrap:wrap; margin-bottom:16px;">
            <div style="flex:1; min-width:200px;">
              <label
                style="display:block; margin-bottom:8px; font-weight:600; color:var(--text); font-size: 12px;">User:</label>
              <select id="user_select" class="input-select" style="width:100%;"
                onchange="window.CalculatorPHPUI.loadUserAccounts(this.value)">
                <option value="">-- Select User --</option>
              </select>
            </div>
            <div style="flex:1; min-width:200px;">
              <label
                style="display:block; margin-bottom:8px; font-weight:600; color:var(--text); font-size: 12px;">Account:</label>
              <select id="account_select" class="input-select" style="width:100%;"
                onchange="window.CalculatorPHPUI.loadAccountDetails(this.value)" disabled>
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
          <div id="user_info_display"
            style="padding:16px; background:var(--card); border-radius:8px; border:1px solid var(--border);">
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

              <!-- Customer Editable Charges (Account Mode) -->
              <div id="customer_overrides_section"
                style="margin-top:20px; padding-top:20px; border-top:1px solid var(--border);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                  <h4 style="margin: 0; font-size: 16px; color: #1e3a8a;">Customer Specific Charges
                    <small>(Overrides)</small></h4>
                  <button type="button" onclick="window.CalculatorPHPUI.saveCustomerCosts()" class="btn-sm"
                    style="background:#10b981; color:white; border:none; padding:4px 12px; border-radius:4px; cursor:pointer; font-size:12px;">Save
                    Overrides</button>
                </div>
                <div id="customer_overrides_list"
                  style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:15px;">
                  <!-- Dynamically populated -->
                  <div style="grid-column: 1/-1; color: var(--muted); font-size: 13px;">Select an account to manage
                    specific charges.</div>
                </div>
                <div id="customer_costs_status" style="margin-top:10px; font-size:12px; display:none;"></div>
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
          <div id="bills_display"
            style="padding:16px; background:var(--card); border-radius:8px; border:1px solid var(--border);">
            <div style="text-align:center; color:var(--muted); padding:20px;">No bills available</div>
          </div>
        </div>
      </div>

      <!-- PERIOD MODE CONTAINER -->
      <div id="period-mode-container">
        <!-- Billing Mode and Template -->
        <div style="margin-bottom:24px;">
          <div style="display:flex; gap:30px;">
            <!-- Billing Mode Field -->
            <div style="flex:1;">
              <label style="display:block; margin-bottom:5px; font-weight:600;">Billing Mode</label>
              <select id="billing_mode_select_period" class="input-select"
                onchange="window.CalculatorPHPUI.switchBillingMode(this.value)"
                style="width:100%; border:1px solid #4da3d9; border-radius:6px; padding:8px 12px; font-size:16px;">
                <option value="period" selected>Period to Period</option>
                <option value="sector" disabled>Date to Date (Coming Soon)</option>
              </select>
            </div>

            <!-- Template Field -->
            <div style="flex:1;">
              <label style="display:block; margin-bottom:5px; font-weight:600;">Template</label>
              <div style="display:flex; gap:8px;">
                <select id="tariff_template_select" class="input-select"
                  onchange="window.CalculatorPHPUI.loadTariffTemplate(this.value)"
                  style="flex:1; border:1px solid #4da3d9; border-radius:6px; padding:8px 12px; font-size:16px;">
                  <option value="">-- Select Template --</option>
                </select>
                <button onclick="window.CalculatorPHPUI.loadTariffTemplates()"
                  style="background:#4da3d9; color:white; border:none; padding:8px 12px; border-radius:6px; cursor:pointer; white-space:nowrap;"
                  title="Reload Templates">🔄</button>
              </div>
            </div>
          </div>

          <!-- Reset Button -->
          <div style="margin-top:12px;">
            <button id="reset_template_btn" onclick="window.CalculatorPHPUI.resetTariffTemplate()" class="reset-button"
              style="display:none;">Reset</button>
          </div>
        </div>

        <!-- 1️⃣ TARIFF TEMPLATE -->
        <div class="section collapsed">
          <div class="section-header" onclick="this.parentElement.classList.toggle('collapsed')">
            1️⃣ Tariff Template
          </div>
          <div class="section-content">
            <div id="tariff_details_display"
              style="display:none; padding:16px; background:var(--card); border-radius:8px; border:1px solid var(--border); margin-top:16px;">
              <div style="font-size:18px; font-weight:700; color:var(--text); margin-bottom:12px;"
                id="tariff_template_name_display">—</div>
              <div style="font-size:14px; color:var(--muted); margin-bottom:16px;">
                <span id="tariff_billing_type_display">Billing Type: —</span> |
                <span id="tariff_billing_day_display">Billing Day: —</span> |
                <span id="tariff_vat_rate_display">VAT: —%</span>
              </div>
              <div id="tariff_error" style="color:var(--red); display:none; margin-top:8px;"></div>

              <!-- Collapsible Tariff Charges -->
              <div class="section collapsed" style="margin-top:16px; margin-bottom:0;">
                <div class="section-header" onclick="this.parentElement.classList.toggle('collapsed')"
                  style="font-size:16px;">
                  📋 View Complete Tariff Charges
                </div>
                <div class="section-content">
                  <!-- Tiers -->
                  <div style="margin-bottom:20px;">
                    <div style="font-size:16px; font-weight:700; color:var(--text); margin-bottom:12px;">Water Tiers
                    </div>
                    <table style="width:100%; border-collapse:collapse;">
                      <thead>
                        <tr style="background:var(--bg);">
                          <th style="text-align:left; padding:8px; border-bottom:1px solid var(--border);">Tier</th>
                          <th style="text-align:right; padding:8px; border-bottom:1px solid var(--border);">Max (L)</th>
                          <th style="text-align:right; padding:8px; border-bottom:1px solid var(--border);">Rate (R/kL)
                          </th>
                        </tr>
                      </thead>
                      <tbody id="tariff_tiers_display">
                        <tr>
                          <td colspan="3" style="text-align:center; padding:12px; color:var(--muted);">No tiers
                            available</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>

                  <!-- Fixed Costs -->
                  <div style="margin-bottom:20px;">
                    <div style="font-size:16px; font-weight:700; color:var(--text); margin-bottom:12px;">Fixed Costs
                    </div>
                    <table style="width:100%; border-collapse:collapse;">
                      <tbody id="tariff_fixed_costs_display">
                        <tr>
                          <td colspan="2" style="text-align:center; padding:12px; color:var(--muted);">No fixed costs
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>

                  <!-- Customer Costs -->
                  <div style="margin-bottom:20px;">
                    <div style="font-size:16px; font-weight:700; color:var(--text); margin-bottom:12px;">Customer Costs
                    </div>
                    <table style="width:100%; border-collapse:collapse;">
                      <tbody id="tariff_customer_costs_display">
                        <tr>
                          <td colspan="2" style="text-align:center; padding:12px; color:var(--muted);">No customer costs
                          </td>
                        </tr>
                      </tbody>
                    </table>
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
            <div style="display:flex; align-items:flex-end; gap:40px;">
              <!-- Bill Day Field -->
              <div style="flex:1;">
                <label style="display:block; margin-bottom:5px; font-weight:600;">Bill Day</label>
                <input type="number" id="bill_day" class="input-number" min="1" max="31" value="20"
                  style="width:100%; border:1px solid #4da3d9; border-radius:6px; padding:8px 12px; font-size:16px;">
              </div>

              <!-- Start Month Field -->
              <div style="flex:1;">
                <label style="display:block; margin-bottom:5px; font-weight:600;">Start Month</label>
                <input type="month" id="start_month" class="input-date" value="2026-01"
                  style="width:100%; border:1px solid #4da3d9; border-radius:6px; padding:8px 12px; font-size:16px;">
              </div>

              <!-- Current Date Field -->
              <div style="flex:1;">
                <label style="display:block; margin-bottom:5px; font-weight:600;">Current date</label>
                <input type="date" id="current_date" class="input-date"
                  style="width:100%; border:1px solid #4da3d9; border-radius:6px; padding:8px 12px; font-size:16px;">
              </div>

              <!-- Add Period Button -->
              <div>
                <button onclick="window.CalculatorPHPUI.addPeriod()"
                  style="background:#8fc7d8; border:none; padding:6px 12px; border-radius:6px; font-size:16px; cursor:pointer; white-space:nowrap; font-weight:500;">Add
                  Period</button>
              </div>
            </div>
          </div>
        </div>

        <!-- 3️⃣ PERIODS & READINGS -->
        <div class="section">
          <div class="section-header">3️⃣ Periods and Readings</div>
          <div class="section-content">
            <table id="period_table"
              style="width:100%; margin-bottom:20px; border-collapse:collapse; border-spacing:0;">
              <thead style="display:none;">
                <tr>
                  <th></th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
            <table id="period_reading_table" style="width:100%; border-collapse:separate; border-spacing:0;">
              <thead>
                <tr>
                  <th style="padding:12px 16px; text-align:left; color:#000000; font-size:16px; font-weight:400;">Date
                  </th>
                  <th style="padding:12px 16px; text-align:left; color:#000000; font-size:16px; font-weight:400;">
                    Reading</th>
                  <th style="padding:12px 16px; text-align:left; color:#000000; font-size:16px; font-weight:400;">Cost
                    (R)</th>
                  <th style="padding:12px 16px;"></th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
            <!-- Action Buttons -->
            <div class="action-row" style="margin-top:20px; margin-bottom:20px; text-align:center;">
              <button onclick="window.CalculatorPHPUI.addReading()"
                style="padding:6px 16px; font-size:16px; background:#D9D9D9; color:#000000; border:none; border-radius:8px; font-weight:400; cursor:pointer; margin:0 8px;">
                Add Reading
              </button>

              <button id="calculate_btn" onclick="window.CalculatorPHPUI.calculate()"
                style="padding:6px 16px; font-size:16px; background:#118a2c; color:#FFFFFF; border:none; border-radius:8px; font-weight:400; cursor:pointer; margin:0 8px;"
                disabled>Calculate</button>
            </div>

            <div id="calculate_error"
              style="color:var(--red); display:none; font-size:14px; text-align:right; margin-top:8px;"></div>

            <div id="period_dashboard" style="margin-top:30px;">
              <!-- Professional Bill Header -->
              <div
                style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); color: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); margin-bottom: 25px;">
                <div
                  style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.2); padding-bottom: 15px; margin-bottom: 20px;">
                  <div>
                    <h4 style="margin: 0; font-weight: 700; letter-spacing: 0.5px;">BILLING PERFORMANCE</h4>
                    <div style="font-size: 13px; opacity: 0.9;" id="bill_period_dates">—</div>
                  </div>
                  <div style="text-align: right;">
                    <span class="badge" id="bill_status_badge"
                      style="background: rgba(255,255,255,0.2); font-size: 11px; padding: 4px 10px; border-radius: 20px;">CALCULATING...</span>
                  </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                  <div
                    style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px; text-align: center;">
                    <div
                      style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px; opacity: 0.8;">
                      Avg Daily Usage</div>
                    <div style="font-size: 24px; font-weight: 700;" id="period_dashboard_daily_usage">—</div>
                  </div>
                  <div
                    style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px; text-align: center;">
                    <div
                      style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px; opacity: 0.8;">
                      Avg Daily Cost</div>
                    <div style="font-size: 24px; font-weight: 700;" id="period_dashboard_daily_cost">—</div>
                  </div>
                  <div
                    style="background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.4); padding: 15px; border-radius: 8px; text-align: center;">
                    <div
                      style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px; opacity: 1; color: #6ee7b7;">
                      Projected Total</div>
                    <div style="font-size: 24px; font-weight: 700; color: #ffffff;" id="period_dashboard_total_cost">R
                      0.00</div>
                  </div>
                </div>
              </div>

              <!-- Professional Bill Breakdown -->
              <div id="bill_detailed_breakdown"
                style="display: none; background: white; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; margin-top: 20px;">
                <div
                  style="padding: 15px 20px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                  <h5 style="margin: 0; font-weight: 600; color: #374151;">Detailed Cost Breakdown</h5>
                  <button type="button" class="btn-sm"
                    style="background: #e5e7eb; border: none; padding: 4px 8px; border-radius: 4px; font-size: 11px;">View
                    Full Bill</button>
                </div>
                <div style="padding: 20px;">
                  <!-- Tier Breakdown Container -->
                  <div id="tier_breakdown_section">
                    <h6
                      style="font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; margin-bottom: 12px;">
                      Consumption Tiers</h6>
                    <div id="tier_list_container"></div>
                  </div>

                  <hr style="border: 0; border-top: 1px solid #f3f4f6; margin: 20px 0;">

                  <!-- Fixed & Customer Costs -->
                  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px;">
                    <div>
                      <h6
                        style="font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; margin-bottom: 12px;">
                        Fixed Charges</h6>
                      <div id="fixed_charges_container"></div>
                    </div>
                    <div>
                      <h6
                        style="font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; margin-bottom: 12px;">
                        Custom Charges</h6>
                      <div id="customer_charges_container"></div>
                    </div>
                  </div>

                  <hr style="border: 0; border-top: 2px solid #efeff1; margin: 20px 0;">

                  <div style="display: flex; justify-content: flex-end;">
                    <div style="width: 250px;">
                      <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="color: #6b7280;">Subtotal:</span>
                        <span id="bill_subtotal" style="font-weight: 600;">R 0.00</span>
                      </div>
                      <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="color: #6b7280;">VAT (15%):</span>
                        <span id="bill_vat" style="font-weight: 600;">R 0.00</span>
                      </div>
                      <div
                        style="display: flex; justify-content: space-between; padding-top: 10px; border-top: 2px solid #1e3a8a;">
                        <span style="font-weight: 700; color: #1e3a8a;">TOTAL:</span>
                        <span id="bill_grand_total" style="font-weight: 800; color: #1e3a8a; font-size: 18px;">R
                          0.00</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
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
          <button id="save_bills_btn" onclick="window.CalculatorPHPUI.saveBills()" class="btn-calculate"
            style="background: #1e3a8a;" disabled>💾 Save Calculation to Account</button>
          <div id="save_bills_error" style="color:var(--red); margin-top:12px; display:none; font-size:14px;"></div>
          <div id="save_bills_success" style="color:#10b981; margin-top:12px; display:none; font-size:14px;"></div>
        </div>
      </div>

      <!-- BILL PREVIEW CONTAINER -->
      <div id="bill-preview-container" style="display:none;">
        <div class="section">
          <div class="section-header">Bill Preview</div>
          <div class="section-content">
            <div id="bill_preview_content">
              <div id="bill_no_data" style="text-align:center; padding:40px; color:var(--muted); display:none;">
                <div style="font-size:18px; margin-bottom:8px;">No bill data available</div>
                <div style="font-size:14px;">Add readings and calculate to see bill preview</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- CalculatorPHP Mode Configuration -->
  <script>
    // CalculatorPHP mode - NEW CLEAN IMPLEMENTATION
    window.FORCE_PHP_CALCULATOR = true;
    window.CALCULATOR_MODE = 'calculator-php';
    window.CALCULATOR_REVISION = 'Rev1';
  </script>

  <!-- Flatpickr JS -->
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <!-- Load CalculatorPHP UI (NEW ISOLATED) -->
  <script src="{{ url('/js/calculator-php-ui.js') }}?v={{ time() }}"></script>

</body>

</html>