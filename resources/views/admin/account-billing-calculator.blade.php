<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Account Billing Calculator - MyCities</title>
<style>
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
}

body{
  margin:0;
  font-family:Inter,Segoe UI,Arial,sans-serif;
  background:var(--bg);
  color:var(--text);
  font-size:16px;
}

.container{
  max-width:1400px;
  margin:0 auto;
  padding:20px;
}

/* TOP BAR */
.topbar{
  background:#fff;
  border-bottom:1px solid var(--border);
  padding:14px 18px;
  font-weight:700;
  font-size:18px;
  margin-bottom:20px;
}

/* SECTIONS */
.section{
  background:var(--card);
  border:1px solid var(--border);
  border-radius:12px;
  margin-bottom:20px;
  overflow:hidden;
}
.section-header{
  padding:14px 18px;
  font-size:18px;
  font-weight:700;
  cursor:pointer;
  background:#f9fafb;
  display:flex;
  align-items:center;
  justify-content:space-between;
}
.section-header::after{
  content:'▼';
  font-size:12px;
  color:var(--muted);
  transition:transform 0.3s ease;
}
.section.collapsed .section-header::after{
  transform:rotate(-90deg);
}
.section-content{ 
  padding:18px;
}
.section.collapsed .section-content{ 
  display:none;
}

/* SEARCH SECTION */
.search-container{
  display:flex;
  gap:12px;
  margin-bottom:16px;
}
.search-input{
  flex:1;
  padding:12px 16px;
  font-size:16px;
  border-radius:8px;
  border:1px solid var(--border);
}
.search-button{
  padding:12px 24px;
  background:var(--blue);
  color:#fff;
  border:none;
  border-radius:8px;
  font-weight:700;
  cursor:pointer;
  font-size:16px;
}
.search-button:hover{
  background:#1d4ed8;
}

/* USER RESULTS */
.user-results{
  max-height:400px;
  overflow-y:auto;
  border:1px solid var(--border);
  border-radius:8px;
  background:#fff;
}
.user-item{
  padding:12px 16px;
  border-bottom:1px solid var(--border);
  cursor:pointer;
  transition:background 0.2s;
}
.user-item:hover{
  background:#f9fafb;
}
.user-item.selected{
  background:#eef2ff;
  border-left:4px solid var(--blue);
}
.user-name{
  font-weight:700;
  font-size:16px;
  margin-bottom:4px;
}
.user-details{
  font-size:14px;
  color:var(--muted);
}

/* ACCOUNT SELECTION */
.account-list{
  display:flex;
  flex-direction:column;
  gap:8px;
}
.account-item{
  padding:12px 16px;
  border:1px solid var(--border);
  border-radius:8px;
  cursor:pointer;
  transition:all 0.2s;
}
.account-item:hover{
  background:#f9fafb;
  border-color:var(--blue);
}
.account-item.selected{
  background:#eef2ff;
  border-color:var(--blue);
  border-width:2px;
}
.account-name{
  font-weight:700;
  font-size:16px;
  margin-bottom:4px;
}
.account-number{
  font-size:14px;
  color:var(--muted);
}

/* METER SELECTION */
.meter-list{
  display:grid;
  grid-template-columns:repeat(auto-fill, minmax(250px, 1fr));
  gap:12px;
}
.meter-item{
  padding:12px 16px;
  border:1px solid var(--border);
  border-radius:8px;
  cursor:pointer;
  transition:all 0.2s;
}
.meter-item:hover{
  background:#f9fafb;
  border-color:var(--blue);
}
.meter-item.selected{
  background:#eef2ff;
  border-color:var(--blue);
  border-width:2px;
}
.meter-title{
  font-weight:700;
  font-size:15px;
  margin-bottom:4px;
}
.meter-number{
  font-size:13px;
  color:var(--muted);
}
.meter-readings-count{
  font-size:12px;
  color:var(--muted);
  margin-top:4px;
}

/* BUTTONS */
button{
  border:none;
  cursor:pointer;
  font-weight:700;
  border-radius:8px;
  transition:all 0.2s ease;
}
.btn-primary{
  background:var(--green);
  color:#fff;
  font-size:16px;
  padding:12px 24px;
}
.btn-primary:hover{
  background:#15803d;
}
.btn-secondary{
  background:#e5e7eb;
  color:#374151;
  padding:10px 16px;
  font-size:14px;
}
.btn-secondary:hover{
  background:#d1d5db;
}
.btn-danger{
  background:var(--red);
  color:#fff;
  padding:8px 16px;
  font-size:14px;
}
.btn-danger:hover{
  background:#b91c1c;
}

/* TABLES */
table{ width:100%; border-collapse:collapse }
th{
  text-align:left;
  font-size:14px;
  color:var(--muted);
  font-weight:700;
  padding:10px 8px;
  border-bottom:1px solid var(--border);
}
td{
  padding:12px 8px;
  border-bottom:1px solid var(--border);
  font-size:16px;
}
tbody tr:hover{ background:#f9fafb }

/* INPUTS */
input, select{
  padding:8px 10px;
  font-size:16px;
  border-radius:8px;
  border:1px solid var(--border);
  width:100%;
}
.input-group{
  margin-bottom:16px;
}
.input-group label{
  display:block;
  margin-bottom:8px;
  font-weight:600;
  color:var(--text);
}

/* BILL PREVIEW (Reused from billing calculator) */
.bill-preview{
  background:var(--card);
  border:2px solid var(--blue);
  border-radius:12px;
  padding:24px;
}
.bill-header{
  margin-bottom:24px;
  padding-bottom:16px;
  border-bottom:2px solid var(--border);
}
.bill-title{
  font-size:24px;
  font-weight:700;
  color:var(--text);
  margin-bottom:8px;
}
.bill-subtitle{
  font-size:14px;
  color:var(--muted);
}

/* ACTION BUTTONS */
.action-buttons{
  display:flex;
  gap:12px;
  margin-top:16px;
}
</style>
</head>
<body>

<div class="container">
  <!-- TOP BAR -->
  <div class="topbar">Account Billing Calculator</div>

  <!-- USER SEARCH SECTION -->
  <div class="section">
    <div class="section-header" onclick="this.parentElement.classList.toggle('collapsed')">
      1️⃣ Search User
    </div>
    <div class="section-content">
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
      
      <!-- Search Results -->
      <div id="user_search_results" style="display:none;">
        <div style="font-weight:700; margin-bottom:12px; color:var(--text);">Search Results:</div>
        <div id="user_results_list" class="user-results"></div>
      </div>
      
      <!-- Selected User Display -->
      <div id="selected_user_display" style="display:none; margin-top:20px; padding:16px; background:#f9fafb; border-radius:8px;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
          <div>
            <div style="font-weight:700; font-size:18px; margin-bottom:4px;" id="selected_user_name">—</div>
            <div style="font-size:14px; color:var(--muted);" id="selected_user_details">—</div>
          </div>
          <button class="btn-secondary" onclick="clearSelection()">Clear Selection</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ACCOUNT SELECTION SECTION -->
  <div class="section" id="account_selection_section" style="display:none;">
    <div class="section-header" onclick="this.parentElement.classList.toggle('collapsed')">
      2️⃣ Select Account
    </div>
    <div class="section-content">
      <div id="account_list" class="account-list"></div>
      
      <!-- Add Account Button -->
      <div style="margin-top:16px;">
        <button class="btn-secondary" onclick="showAddAccountForm()">+ Add Account</button>
      </div>
      
      <!-- Add Account Form (Hidden by default) -->
      <div id="add_account_form" style="display:none; margin-top:20px; padding:16px; background:#f9fafb; border-radius:8px;">
        <div class="input-group">
          <label>Account Name: <span style="color:var(--red);">*</span></label>
          <input type="text" id="new_account_name" placeholder="Enter account name">
        </div>
        <div class="input-group">
          <label>Account Number:</label>
          <input type="text" id="new_account_number" placeholder="Enter account number">
        </div>
        <div class="action-buttons">
          <button class="btn-primary" onclick="addAccount()">Add Account</button>
          <button class="btn-secondary" onclick="hideAddAccountForm()">Cancel</button>
        </div>
      </div>
    </div>
  </div>

  <!-- METER SELECTION SECTION -->
  <div class="section" id="meter_selection_section" style="display:none;">
    <div class="section-header" onclick="this.parentElement.classList.toggle('collapsed')">
      3️⃣ Select Meter
    </div>
    <div class="section-content">
      <div id="meter_list" class="meter-list"></div>
      
      <!-- Add Meter Button -->
      <div style="margin-top:16px;">
        <button class="btn-secondary" onclick="showAddMeterForm()">+ Add Meter</button>
      </div>
      
      <!-- Add Meter Form (Hidden by default) -->
      <div id="add_meter_form" style="display:none; margin-top:20px; padding:16px; background:#f9fafb; border-radius:8px;">
        <div class="input-group">
          <label>Meter Title: <span style="color:var(--red);">*</span></label>
          <input type="text" id="new_meter_title" placeholder="e.g., Main Water Meter">
        </div>
        <div class="input-group">
          <label>Meter Number: <span style="color:var(--red);">*</span></label>
          <input type="text" id="new_meter_number" placeholder="Enter meter number">
        </div>
        <div class="input-group">
          <label>Meter Type: <span style="color:var(--red);">*</span></label>
          <select id="new_meter_type">
            <option value="">-- Select Meter Type --</option>
            <!-- Populated by JavaScript -->
          </select>
        </div>
        <div class="action-buttons">
          <button class="btn-primary" onclick="addMeter()">Add Meter</button>
          <button class="btn-secondary" onclick="hideAddMeterForm()">Cancel</button>
        </div>
      </div>
    </div>
  </div>

  <!-- READINGS MANAGEMENT SECTION -->
  <div class="section" id="readings_section" style="display:none;">
    <div class="section-header" onclick="this.parentElement.classList.toggle('collapsed')">
      4️⃣ Meter Readings
    </div>
    <div class="section-content">
      <div style="margin-bottom:16px;">
        <button class="btn-primary" onclick="showAddReadingForm()">+ Add Reading</button>
      </div>
      
      <!-- Readings Table -->
      <table id="readings_table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Reading (L)</th>
            <th>Type</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="readings_table_body">
          <tr><td colspan="4" style="text-align:center; padding:20px; color:var(--muted);">No readings yet</td></tr>
        </tbody>
      </table>
      
      <!-- Add Reading Form (Hidden by default) -->
      <div id="add_reading_form" style="display:none; margin-top:20px; padding:16px; background:#f9fafb; border-radius:8px;">
        <div class="input-group">
          <label>Reading Date: <span style="color:var(--red);">*</span></label>
          <input type="date" id="new_reading_date">
        </div>
        <div class="input-group">
          <label>Reading Value (L): <span style="color:var(--red);">*</span></label>
          <input type="number" id="new_reading_value" placeholder="Enter reading value" min="0" step="0.01">
        </div>
        <div class="input-group">
          <label>Reading Type:</label>
          <select id="new_reading_type">
            <option value="ACTUAL">ACTUAL</option>
            <option value="CALCULATED">CALCULATED</option>
            <option value="PROVISIONAL">PROVISIONAL</option>
          </select>
        </div>
        <div class="action-buttons">
          <button class="btn-primary" onclick="addReading()">Add Reading</button>
          <button class="btn-secondary" onclick="hideAddReadingForm()">Cancel</button>
        </div>
      </div>
    </div>
  </div>

  <!-- BILL CALCULATION SECTION -->
  <div class="section" id="bill_calculation_section" style="display:none;">
    <div class="section-header" onclick="this.parentElement.classList.toggle('collapsed')">
      5️⃣ Calculate Bill
    </div>
    <div class="section-content">
      <div style="margin-bottom:16px;">
        <label style="display:block; margin-bottom:8px; font-weight:600;">Billing Mode:</label>
        <select id="billing_mode_select" style="width:100%; max-width:300px;">
          <option value="PERIOD_TO_PERIOD">Period to Period</option>
          <option value="DATE_TO_DATE">Date to Date</option>
        </select>
      </div>
      
      <div style="margin-bottom:16px;">
        <button class="btn-primary" onclick="calculateBill()">Calculate Bill</button>
      </div>
      
      <!-- Bill Preview (Reused from billing calculator) -->
      <div id="bill_preview_container" style="display:none;">
        <!-- Bill Preview will be populated here using the same structure as billing-calculator.blade.php -->
        <div class="bill-preview">
          <div class="bill-header">
            <div class="bill-title" id="bill_template_name">No Template Selected</div>
            <div class="bill-subtitle" id="bill_billing_type">Billing Type: —</div>
            <div class="bill-subtitle" id="bill_period" style="margin-top:4px;">Billing Period: —</div>
          </div>
          
          <!-- Usage Summary -->
          <div style="margin-bottom:24px;">
            <div style="font-size:18px; font-weight:700; margin-bottom:12px;">Usage Summary</div>
            <div style="background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:16px;">
              <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div>
                  <div style="font-size:14px; color:var(--muted); margin-bottom:4px;">Total Usage</div>
                  <div style="font-size:20px; font-weight:700;" id="bill_total_usage">— L</div>
                </div>
                <div>
                  <div style="font-size:14px; color:var(--muted); margin-bottom:4px;">Daily Usage</div>
                  <div style="font-size:20px; font-weight:700;" id="bill_daily_usage">— L/day</div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Consumption Charges -->
          <div id="bill_tier_charges_section" style="margin-bottom:24px; display:none;">
            <div style="font-size:18px; font-weight:700; margin-bottom:12px;">Consumption Charges</div>
            <table>
              <thead>
                <tr style="background:var(--bg);">
                  <th>Tier</th>
                  <th style="text-align:right;">Usage (L)</th>
                  <th style="text-align:right;">Rate (R/kL)</th>
                  <th style="text-align:right;">Charge (R)</th>
                </tr>
              </thead>
              <tbody id="bill_tier_breakdown"></tbody>
              <tfoot>
                <tr style="border-top:2px solid var(--border);">
                  <td colspan="3" style="text-align:right; font-weight:700;">Consumption Charges Subtotal:</td>
                  <td style="text-align:right; font-weight:700;" id="bill_tier_subtotal">R 0.00</td>
                </tr>
              </tfoot>
            </table>
          </div>
          
          <!-- Fixed Costs -->
          <div id="bill_fixed_costs_section" style="margin-bottom:24px; display:none;">
            <div style="font-size:18px; font-weight:700; margin-bottom:12px;">Fixed Costs</div>
            <table>
              <tbody id="bill_fixed_costs"></tbody>
              <tfoot>
                <tr style="border-top:2px solid var(--border);">
                  <td style="text-align:right; font-weight:700;">Fixed Costs Subtotal:</td>
                  <td style="text-align:right; font-weight:700;" id="bill_fixed_costs_subtotal">R 0.00</td>
                </tr>
              </tfoot>
            </table>
          </div>
          
          <!-- Customer Costs -->
          <div id="bill_customer_costs_section" style="margin-bottom:24px; display:none;">
            <div style="font-size:18px; font-weight:700; margin-bottom:12px;">Customer Costs</div>
            <table>
              <tbody id="bill_customer_costs"></tbody>
              <tfoot>
                <tr style="border-top:2px solid var(--border);">
                  <td style="text-align:right; font-weight:700;">Customer Costs Subtotal:</td>
                  <td style="text-align:right; font-weight:700;" id="bill_customer_costs_subtotal">R 0.00</td>
                </tr>
              </tfoot>
            </table>
          </div>
          
          <!-- Water Out Charges -->
          <div id="bill_water_out_section" style="margin-bottom:24px; display:none;">
            <div style="font-size:18px; font-weight:700; margin-bottom:12px;">Water Out Charges</div>
            <table>
              <thead>
                <tr style="background:var(--bg);">
                  <th>Tier</th>
                  <th style="text-align:right;">Min (L)</th>
                  <th style="text-align:right;">Max (L)</th>
                  <th style="text-align:right;">Usage (L)</th>
                  <th style="text-align:right;">%</th>
                  <th style="text-align:right;">Rate (R/kL)</th>
                  <th style="text-align:right;">Charge (R)</th>
                </tr>
              </thead>
              <tbody id="bill_water_out_breakdown"></tbody>
              <tfoot>
                <tr style="border-top:2px solid var(--border);">
                  <td colspan="6" style="text-align:right; font-weight:700;">Water Out Total:</td>
                  <td style="text-align:right; font-weight:700;" id="bill_water_out_subtotal">R 0.00</td>
                </tr>
              </tfoot>
            </table>
          </div>
          
          <!-- Water Out Related Charges -->
          <div id="bill_water_out_related_section" style="margin-bottom:24px; display:none;">
            <div style="font-size:18px; font-weight:700; margin-bottom:12px;">Water Out Related Charges</div>
            <table>
              <tbody id="bill_water_out_related"></tbody>
              <tfoot>
                <tr style="border-top:2px solid var(--border);">
                  <td style="text-align:right; font-weight:700;">Water Out Related Charges Subtotal:</td>
                  <td style="text-align:right; font-weight:700;" id="bill_water_out_related_subtotal">R 0.00</td>
                </tr>
              </tfoot>
            </table>
          </div>
          
          <!-- Additional Charges -->
          <div id="bill_additional_charges_section" style="margin-bottom:24px; display:none;">
            <div style="font-size:18px; font-weight:700; margin-bottom:12px;">Additional Charges</div>
            <table>
              <tbody id="bill_additional_charges"></tbody>
              <tfoot>
                <tr style="border-top:2px solid var(--border);">
                  <td style="text-align:right; font-weight:700;">Additional Charges Subtotal:</td>
                  <td style="text-align:right; font-weight:700;" id="bill_additional_charges_subtotal">R 0.00</td>
                </tr>
              </tfoot>
            </table>
          </div>
          
          <!-- Totals -->
          <div style="background:var(--blue-light); border:2px solid var(--blue); border-radius:12px; padding:24px; margin-top:32px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
              <div style="font-size:18px; font-weight:700;">Subtotal (Before VAT):</div>
              <div style="font-size:18px; font-weight:700;" id="bill_subtotal">R 0.00</div>
            </div>
            <div id="bill_vat_section" style="display:none; flex-direction:row; justify-content:space-between; align-items:center; margin-bottom:12px; padding-top:12px; border-top:1px solid var(--border);">
              <div style="font-size:18px; font-weight:700;">
                VAT (<span id="bill_vat_rate">0</span>%):
              </div>
              <div style="font-size:18px; font-weight:700;" id="bill_vat_amount">R 0.00</div>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; padding-top:12px; border-top:2px solid var(--blue);">
              <div style="font-size:24px; font-weight:700; color:var(--blue);">Total:</div>
              <div style="font-size:24px; font-weight:700; color:var(--blue);" id="bill_total">R 0.00</div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Error Message -->
      <div id="bill_error" style="display:none; margin-top:16px; padding:12px; background:#fee2e2; border-left:4px solid var(--red); border-radius:8px; color:var(--red);"></div>
    </div>
  </div>
</div>

<script>
const apiBaseUrl = '{{ url("/admin/account-billing-calculator") }}';
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

// Global state
let selectedUser = null;
let selectedAccount = null;
let selectedMeter = null;
let accountData = null;
let meterTypes = [];

// API Functions
const AccountBillingAPI = {
    searchUsers: async function(query) {
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
            if (!data.success) throw new Error(data.message || 'Search failed');
            return data.data;
        } catch (error) {
            console.error('Error searching users:', error);
            throw error;
        }
    },
    
    getAccount: async function(accountId) {
        try {
            const response = await fetch(`${apiBaseUrl}/account/${accountId}`, {
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
            return data.data;
        } catch (error) {
            console.error('Error loading account:', error);
            throw error;
        }
    },
    
    calculateBill: async function(accountId, meterId, billingMode, options = {}) {
        try {
            const response = await fetch(`${apiBaseUrl}/calculate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    account_id: accountId,
                    meter_id: meterId,
                    billing_mode: billingMode,
                    bill_day: options.bill_day || null,
                    start_date: options.start_date || null
                })
            });
            
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data = await response.json();
            if (!data.success) throw new Error(data.message || 'Failed to calculate bill');
            return data.data;
        } catch (error) {
            console.error('Error calculating bill:', error);
            throw error;
        }
    },
    
    addReading: async function(meterId, readingDate, readingValue, readingType) {
        try {
            const response = await fetch(`${apiBaseUrl}/reading`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    meter_id: meterId,
                    reading_date: readingDate,
                    reading_value: readingValue,
                    reading_type: readingType || 'ACTUAL'
                })
            });
            
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data = await response.json();
            if (!data.success) throw new Error(data.message || 'Failed to add reading');
            return data.data;
        } catch (error) {
            console.error('Error adding reading:', error);
            throw error;
        }
    },
    
    deleteReading: async function(readingId) {
        try {
            const response = await fetch(`${apiBaseUrl}/reading/${readingId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            });
            
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data = await response.json();
            if (!data.success) throw new Error(data.message || 'Failed to delete reading');
            return data.data;
        } catch (error) {
            console.error('Error deleting reading:', error);
            throw error;
        }
    },
    
    getTariffTemplates: async function(billingType = null) {
        try {
            let url = `${apiBaseUrl}/tariff-templates`;
            if (billingType) {
                url += `?billing_type=${billingType}`;
            }
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            });
            
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data = await response.json();
            if (!data.success) throw new Error(data.message || 'Failed to load templates');
            return data.data;
        } catch (error) {
            console.error('Error loading tariff templates:', error);
            throw error;
        }
    }
};

// Search Users
async function searchUsers() {
    const query = document.getElementById('user_search_input').value.trim();
    if (!query) {
        alert('Please enter a search term');
        return;
    }
    
    try {
        const users = await AccountBillingAPI.searchUsers(query);
        displayUserResults(users);
    } catch (error) {
        alert('Error searching users: ' + error.message);
    }
}

// Display User Results
function displayUserResults(users) {
    const resultsDiv = document.getElementById('user_search_results');
    const resultsList = document.getElementById('user_results_list');
    
    if (users.length === 0) {
        resultsList.innerHTML = '<div style="padding:20px; text-align:center; color:var(--muted);">No users found</div>';
        resultsDiv.style.display = 'block';
        return;
    }
    
    resultsList.innerHTML = '';
    users.forEach(user => {
        const userDiv = document.createElement('div');
        userDiv.className = 'user-item';
        userDiv.onclick = () => selectUser(user);
        userDiv.innerHTML = `
            <div class="user-name">${user.name}</div>
            <div class="user-details">${user.email} | ${user.contact_number || 'No phone'}</div>
            <div class="user-details" style="margin-top:4px;">${user.accounts.length} account(s)</div>
        `;
        resultsList.appendChild(userDiv);
    });
    
    resultsDiv.style.display = 'block';
}

// Select User
function selectUser(user) {
    selectedUser = user;
    
    // Update selected user display
    document.getElementById('selected_user_name').textContent = user.name;
    document.getElementById('selected_user_details').textContent = `${user.email} | ${user.contact_number || 'No phone'}`;
    document.getElementById('selected_user_display').style.display = 'block';
    
    // Hide search results
    document.getElementById('user_search_results').style.display = 'none';
    
    // Show account selection
    displayAccounts(user.accounts);
    document.getElementById('account_selection_section').style.display = 'block';
}

// Display Accounts
function displayAccounts(accounts) {
    const accountList = document.getElementById('account_list');
    accountList.innerHTML = '';
    
    if (accounts.length === 0) {
        accountList.innerHTML = '<div style="padding:20px; text-align:center; color:var(--muted);">No accounts found. Add an account to continue.</div>';
        return;
    }
    
    accounts.forEach(account => {
        const accountDiv = document.createElement('div');
        accountDiv.className = 'account-item';
        accountDiv.onclick = () => selectAccount(account);
        accountDiv.innerHTML = `
            <div class="account-name">${account.account_name}</div>
            <div class="account-number">${account.account_number || 'No account number'}</div>
            <div style="font-size:12px; color:var(--muted); margin-top:4px;">
                ${account.billing_type === 'DATE_TO_DATE' ? 'Date to Date' : 'Period to Period'} Billing
            </div>
        `;
        accountList.appendChild(accountDiv);
    });
}

// Select Account
async function selectAccount(account) {
    selectedAccount = account;
    
    // Load full account data
    try {
        accountData = await AccountBillingAPI.getAccount(account.id);
        selectedAccount = accountData.account;
        
        // Show meter selection
        displayMeters(accountData.meters);
        document.getElementById('meter_selection_section').style.display = 'block';
    } catch (error) {
        alert('Error loading account: ' + error.message);
    }
}

// Display Meters
function displayMeters(meters) {
    const meterList = document.getElementById('meter_list');
    meterList.innerHTML = '';
    
    if (meters.length === 0) {
        meterList.innerHTML = '<div style="padding:20px; text-align:center; color:var(--muted); grid-column:1/-1;">No meters found. Add a meter to continue.</div>';
        return;
    }
    
    meters.forEach(meter => {
        const meterDiv = document.createElement('div');
        meterDiv.className = 'meter-item';
        meterDiv.onclick = () => selectMeter(meter);
        meterDiv.innerHTML = `
            <div class="meter-title">${meter.meter_title}</div>
            <div class="meter-number">${meter.meter_number}</div>
            <div class="meter-readings-count">${meter.readings.length} reading(s)</div>
        `;
        meterList.appendChild(meterDiv);
    });
}

// Select Meter
function selectMeter(meter) {
    selectedMeter = meter;
    
    // Show readings section
    displayReadings(meter.readings);
    document.getElementById('readings_section').style.display = 'block';
    
    // Show bill calculation section
    document.getElementById('bill_calculation_section').style.display = 'block';
    
    // Auto-set billing mode based on account
    if (selectedAccount.billing_type === 'DATE_TO_DATE') {
        document.getElementById('billing_mode_select').value = 'DATE_TO_DATE';
    } else {
        document.getElementById('billing_mode_select').value = 'PERIOD_TO_PERIOD';
    }
}

// Display Readings
function displayReadings(readings) {
    const tbody = document.getElementById('readings_table_body');
    tbody.innerHTML = '';
    
    if (readings.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px; color:var(--muted);">No readings yet</td></tr>';
        return;
    }
    
    readings.forEach(reading => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${reading.date}</td>
            <td style="text-align:right; font-family:ui-monospace,monospace; font-weight:600;">${formatNumber(reading.value)} L</td>
            <td><span class="badge" style="padding:4px 8px; border-radius:4px; font-size:12px; background:#e9d5ff; color:#6b21a8;">${reading.type}</span></td>
            <td>
                <button class="btn-danger" onclick="deleteReading(${reading.id})" style="padding:6px 12px; font-size:13px;">Delete</button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

// Add Reading
async function addReading() {
    if (!selectedMeter) {
        alert('Please select a meter first');
        return;
    }
    
    const date = document.getElementById('new_reading_date').value;
    const value = parseFloat(document.getElementById('new_reading_value').value);
    const type = document.getElementById('new_reading_type').value;
    
    if (!date || isNaN(value) || value < 0) {
        alert('Please enter valid date and reading value');
        return;
    }
    
    try {
        await AccountBillingAPI.addReading(selectedMeter.id, date, value, type);
        
        // Reload account data to get updated readings
        accountData = await AccountBillingAPI.getAccount(selectedAccount.id);
        const updatedMeter = accountData.meters.find(m => m.id === selectedMeter.id);
        if (updatedMeter) {
            selectedMeter = updatedMeter;
            displayReadings(updatedMeter.readings);
        }
        
        // Hide form
        hideAddReadingForm();
        
        // Clear form
        document.getElementById('new_reading_date').value = '';
        document.getElementById('new_reading_value').value = '';
    } catch (error) {
        alert('Error adding reading: ' + error.message);
    }
}

// Delete Reading
async function deleteReading(readingId) {
    if (!confirm('Are you sure you want to delete this reading?')) {
        return;
    }
    
    try {
        await AccountBillingAPI.deleteReading(readingId);
        
        // Reload account data
        accountData = await AccountBillingAPI.getAccount(selectedAccount.id);
        const updatedMeter = accountData.meters.find(m => m.id === selectedMeter.id);
        if (updatedMeter) {
            selectedMeter = updatedMeter;
            displayReadings(updatedMeter.readings);
        }
    } catch (error) {
        alert('Error deleting reading: ' + error.message);
    }
}

// Calculate Bill
async function calculateBill() {
    if (!selectedAccount || !selectedMeter) {
        alert('Please select an account and meter first');
        return;
    }
    
    const billingMode = document.getElementById('billing_mode_select').value;
    const errorDiv = document.getElementById('bill_error');
    errorDiv.style.display = 'none';
    
    try {
        const result = await AccountBillingAPI.calculateBill(
            selectedAccount.id,
            selectedMeter.id,
            billingMode
        );
        
        // Display bill preview
        displayBillPreview(result);
        document.getElementById('bill_preview_container').style.display = 'block';
    } catch (error) {
        errorDiv.textContent = 'Error calculating bill: ' + error.message;
        errorDiv.style.display = 'block';
    }
}

// Display Bill Preview (Reused from billing calculator logic)
function displayBillPreview(result) {
    const bill = result.bill;
    const usageData = result.usage_data;
    
    // Update header
    document.getElementById('bill_template_name').textContent = selectedAccount.tariff_name || 'No Template';
    document.getElementById('bill_billing_type').textContent = `Billing Type: ${result.billing_mode === 'DATE_TO_DATE' ? 'Date to Date' : 'Period to Period'}`;
    
    // Update usage
    document.getElementById('bill_total_usage').textContent = formatBillNumber(usageData.total_usage) + ' L';
    const dailyUsage = usageData.sectors && usageData.sectors.length > 0 
        ? usageData.sectors[0].daily_usage || 0
        : (usageData.periods && usageData.periods.length > 0 
            ? usageData.periods[0].daily_usage || 0 
            : 0);
    document.getElementById('bill_daily_usage').textContent = formatBillNumber(dailyUsage) + ' L/day';
    
    // Update consumption charges
    if (bill.consumption_charges > 0 && bill.breakdown.consumption.length > 0) {
        const tbody = document.getElementById('bill_tier_breakdown');
        tbody.innerHTML = '';
        bill.breakdown.consumption.forEach((item, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>Tier ${index + 1}</td>
                <td style="text-align:right;">${formatBillNumber(item.used || 0)}</td>
                <td style="text-align:right;">R ${(item.rate || 0).toFixed(2)}</td>
                <td style="text-align:right;">R ${(item.cost || 0).toFixed(2)}</td>
            `;
            tbody.appendChild(tr);
        });
        document.getElementById('bill_tier_subtotal').textContent = formatCurrency(bill.consumption_charges);
        document.getElementById('bill_tier_charges_section').style.display = 'block';
    } else {
        document.getElementById('bill_tier_charges_section').style.display = 'none';
    }
    
    // Update fixed costs
    if (bill.fixed_costs > 0 && bill.breakdown.fixed_costs.length > 0) {
        const tbody = document.getElementById('bill_fixed_costs');
        tbody.innerHTML = '';
        bill.breakdown.fixed_costs.forEach(cost => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${cost.name || 'Fixed Cost'}</td>
                <td style="text-align:right;">${formatCurrency(cost.value || 0)}</td>
            `;
            tbody.appendChild(tr);
        });
        document.getElementById('bill_fixed_costs_subtotal').textContent = formatCurrency(bill.fixed_costs);
        document.getElementById('bill_fixed_costs_section').style.display = 'block';
    } else {
        document.getElementById('bill_fixed_costs_section').style.display = 'none';
    }
    
    // Update customer costs
    if (bill.customer_costs > 0 && bill.breakdown.customer_costs.length > 0) {
        const tbody = document.getElementById('bill_customer_costs');
        tbody.innerHTML = '';
        bill.breakdown.customer_costs.forEach(cost => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${cost.name || 'Customer Cost'}</td>
                <td style="text-align:right;">${formatCurrency(cost.value || 0)}</td>
            `;
            tbody.appendChild(tr);
        });
        document.getElementById('bill_customer_costs_subtotal').textContent = formatCurrency(bill.customer_costs);
        document.getElementById('bill_customer_costs_section').style.display = 'block';
    } else {
        document.getElementById('bill_customer_costs_section').style.display = 'none';
    }
    
    // Update water out charges
    if (bill.water_out_charges > 0 && bill.breakdown.water_out.length > 0) {
        const tbody = document.getElementById('bill_water_out_breakdown');
        tbody.innerHTML = '';
        bill.breakdown.water_out.forEach((item, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>Tier ${index + 1}</td>
                <td style="text-align:right;">${formatBillNumber(item.min || 0)}</td>
                <td style="text-align:right;">${item.max ? formatBillNumber(item.max) : '∞'}</td>
                <td style="text-align:right;">${formatBillNumber(item.units_in_tier || 0)}</td>
                <td style="text-align:right;">${(item.percentage || 100).toFixed(0)}%</td>
                <td style="text-align:right;">R ${(item.cost_per_unit || 0).toFixed(2)}</td>
                <td style="text-align:right;">R ${(item.charge || 0).toFixed(2)}</td>
            `;
            tbody.appendChild(tr);
        });
        document.getElementById('bill_water_out_subtotal').textContent = formatCurrency(bill.water_out_charges);
        document.getElementById('bill_water_out_section').style.display = 'block';
    } else {
        document.getElementById('bill_water_out_section').style.display = 'none';
    }
    
    // Update water out related charges
    if (bill.water_out_related_charges > 0 && bill.breakdown.water_out_related.length > 0) {
        const tbody = document.getElementById('bill_water_out_related');
        tbody.innerHTML = '';
        bill.breakdown.water_out_related.forEach(charge => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${charge.title || charge.name || 'Water Out Related Charge'}</td>
                <td style="text-align:right;">${formatCurrency(charge.cost || 0)}</td>
            `;
            tbody.appendChild(tr);
        });
        document.getElementById('bill_water_out_related_subtotal').textContent = formatCurrency(bill.water_out_related_charges);
        document.getElementById('bill_water_out_related_section').style.display = 'block';
    } else {
        document.getElementById('bill_water_out_related_section').style.display = 'none';
    }
    
    // Update additional charges
    if (bill.additional_charges > 0 && bill.breakdown.additional_charges.length > 0) {
        const tbody = document.getElementById('bill_additional_charges');
        tbody.innerHTML = '';
        bill.breakdown.additional_charges.forEach(charge => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${charge.title || charge.name || 'Additional Charge'}</td>
                <td style="text-align:right;">${formatCurrency(charge.cost || 0)}</td>
            `;
            tbody.appendChild(tr);
        });
        document.getElementById('bill_additional_charges_subtotal').textContent = formatCurrency(bill.additional_charges);
        document.getElementById('bill_additional_charges_section').style.display = 'block';
    } else {
        document.getElementById('bill_additional_charges_section').style.display = 'none';
    }
    
    // Update totals
    document.getElementById('bill_subtotal').textContent = formatCurrency(bill.subtotal);
    
    if (bill.vat_rate > 0 && bill.vat_amount > 0) {
        document.getElementById('bill_vat_rate').textContent = bill.vat_rate;
        document.getElementById('bill_vat_amount').textContent = formatCurrency(bill.vat_amount);
        document.getElementById('bill_vat_section').style.display = 'flex';
    } else {
        document.getElementById('bill_vat_section').style.display = 'none';
    }
    
    document.getElementById('bill_total').textContent = formatCurrency(bill.total);
}

// Form Management Functions
function showAddAccountForm() {
    document.getElementById('add_account_form').style.display = 'block';
}
function hideAddAccountForm() {
    document.getElementById('add_account_form').style.display = 'none';
}
function addAccount() {
    // TODO: Implement add account via API
    alert('Add account functionality - to be implemented');
}

function showAddMeterForm() {
    document.getElementById('add_meter_form').style.display = 'block';
    // TODO: Load meter types
}
function hideAddMeterForm() {
    document.getElementById('add_meter_form').style.display = 'none';
}
function addMeter() {
    // TODO: Implement add meter via API
    alert('Add meter functionality - to be implemented');
}

function showAddReadingForm() {
    document.getElementById('add_reading_form').style.display = 'block';
    // Set default date to today
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('new_reading_date').value = today;
}
function hideAddReadingForm() {
    document.getElementById('add_reading_form').style.display = 'none';
}

function clearSelection() {
    selectedUser = null;
    selectedAccount = null;
    selectedMeter = null;
    accountData = null;
    
    document.getElementById('selected_user_display').style.display = 'none';
    document.getElementById('user_search_results').style.display = 'none';
    document.getElementById('account_selection_section').style.display = 'none';
    document.getElementById('meter_selection_section').style.display = 'none';
    document.getElementById('readings_section').style.display = 'none';
    document.getElementById('bill_calculation_section').style.display = 'none';
    document.getElementById('bill_preview_container').style.display = 'none';
}

// Utility Functions
function formatNumber(num) {
    if (num === null || num === undefined) return '—';
    return Number(num).toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, " ");
}

function formatBillNumber(num) {
    if (num === null || num === undefined) return '0';
    return Number(num).toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, " ");
}

function formatCurrency(num) {
    if (num === null || num === undefined) return 'R 0.00';
    return 'R ' + Number(num).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, " ");
}
</script>
</body>
</html>












