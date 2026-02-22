<template>
  <AdminLayout>
    <div class="calc-shell">

      <!-- ══════════════════════ LEFT PANEL ══════════════════════ -->
      <aside class="calc-left">
        <div class="calc-left-header">
          <div class="calc-left-title">Billing Calculator</div>
          <div class="calc-left-sub">PD.md ↔ Calculator.php</div>
        </div>

        <!-- Mode switch -->
        <div class="calc-mode-group">
          <button class="calc-mode-btn" :class="{ active: mode === 'test' }" @click="setMode('test')">
            🧪 Test Bill
          </button>
          <button class="calc-mode-btn" :class="{ active: mode === 'account' }" @click="setMode('account')">
            👤 User Account
          </button>
        </div>

        <!-- Tab navigation -->
        <nav class="calc-nav">
          <button
            v-for="t in tabs"
            :key="t.id"
            class="calc-nav-btn"
            :class="{ active: activeTab === t.id }"
            @click="activeTab = t.id"
          >
            <span class="calc-nav-icon">{{ t.icon }}</span>
            <span>{{ t.label }}</span>
          </button>
        </nav>

        <!-- Status indicator -->
        <div v-if="result" class="calc-left-status">
          <div class="calc-left-status-label">Last Calculation</div>
          <div class="calc-left-status-total">R {{ formatMoney(result.bill_total ?? result.total_amount) }}</div>
          <div class="calc-left-status-period">
            {{ result.period_start_date || periodStartDate }}
          </div>
        </div>
      </aside>

      <!-- ══════════════════════ RIGHT PANEL ══════════════════════ -->
      <main class="calc-right">

        <!-- ═══ ACCOUNT SEARCH (User Account mode) ═══ -->
        <section v-if="mode === 'account'" class="calc-section">
          <div class="calc-section-title">Search for Account</div>
          <div class="calc-search-box">
            <span class="calc-search-icon">🔍</span>
            <input
              v-model="searchQuery"
              class="calc-search-input"
              placeholder="Search by name, email, account number, phone…"
              @input="onSearchInput"
              autocomplete="off"
            />
            <div v-if="searchResults.length > 0" class="calc-search-dropdown">
              <div
                v-for="item in searchResults"
                :key="item.id"
                class="calc-search-item"
                @click="selectSearchAccount(item)"
              >
                <div class="calc-search-name">{{ item.site?.user?.name || '—' }}</div>
                <div class="calc-search-detail">
                  {{ item.account_name }} · {{ item.account_number }}
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- ═══ USER / ACCOUNT DROPDOWNS (Test Bill mode) ═══ -->
        <section v-if="mode === 'test'" class="calc-section">
          <div class="calc-section-title">
            🧪 Select User &amp; Account
            <small class="calc-muted"> — optional, pre-fills readings from account</small>
          </div>
          <div class="calc-row">
            <div class="calc-field">
              <label class="calc-label">User</label>
              <select v-model="selectedUserId" class="calc-select" @change="onUserChange">
                <option value="">— Select User —</option>
                <option v-for="u in users" :key="u.id" :value="u.id">
                  {{ u.name }} ({{ u.email }})
                </option>
              </select>
            </div>
            <div class="calc-field">
              <label class="calc-label">Account</label>
              <select
                v-model="selectedAccountId"
                class="calc-select"
                :disabled="!selectedUserId"
                @change="onAccountSelectChange"
              >
                <option value="">— Select Account —</option>
                <option v-for="a in filteredAccounts" :key="a.id" :value="a.id">
                  {{ a.account_name }}
                </option>
              </select>
            </div>
          </div>
        </section>

        <!-- ═══ USER INFO PANEL (when account loaded) ═══ -->
        <section v-if="accountData" class="calc-section calc-info-section">
          <details open>
            <summary class="calc-details-summary">ℹ️ User Information</summary>
            <div class="calc-info-grid">
              <div class="calc-info-item"><span>Name</span><strong>{{ accountData.user.name }}</strong></div>
              <div class="calc-info-item"><span>Email</span><strong>{{ accountData.user.email }}</strong></div>
              <div class="calc-info-item"><span>Contact</span><strong>{{ accountData.user.contact_number || '—' }}</strong></div>
              <div class="calc-info-item"><span>Address</span><strong>{{ accountData.site.address || '—' }}</strong></div>
              <div class="calc-info-item"><span>Region</span><strong>{{ accountData.site.region || '—' }}</strong></div>
              <div class="calc-info-item"><span>Account #</span><strong>{{ accountData.account.account_number }}</strong></div>
              <div class="calc-info-item"><span>Bill Day</span><strong>{{ accountData.account.bill_day || '—' }}</strong></div>
              <div class="calc-info-item"><span>Name on Bill</span><strong>{{ accountData.account.name_on_bill || '—' }}</strong></div>
            </div>
          </details>
        </section>

        <!-- ═══ EXISTING BILLS (User Account mode) ═══ -->
        <section v-if="accountData && accountData.bills && accountData.bills.length > 0" class="calc-section">
          <div class="calc-section-title">🧾 Existing Bills</div>
          <div class="calc-table-wrap">
            <table class="calc-table">
              <thead>
                <tr>
                  <th>Period Start</th>
                  <th>Period End</th>
                  <th>Status</th>
                  <th>Consumption</th>
                  <th class="calc-td-r">Total</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="b in accountData.bills" :key="b.id">
                  <td>{{ b.period_start_date }}</td>
                  <td>{{ b.period_end_date }}</td>
                  <td>
                    <span class="calc-status-badge" :class="'status-' + String(b.status || 'provisional').toLowerCase()">
                      {{ b.status || 'PROVISIONAL' }}
                    </span>
                  </td>
                  <td>{{ b.consumption != null ? Number(b.consumption).toLocaleString() + ' L' : '—' }}</td>
                  <td class="calc-td-r">R {{ formatMoney(b.total_amount) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- ═══ TARIFF + BILLING MODE ═══ -->
        <section class="calc-section">
          <div class="calc-row">
            <div class="calc-field">
              <label class="calc-label">Billing Mode</label>
              <select class="calc-select" v-model="billingMode">
                <option value="period">Period to Period</option>
              </select>
            </div>
            <div class="calc-field">
              <label class="calc-label">Tariff Template</label>
              <div class="calc-input-with-btn">
                <select class="calc-select" v-model="selectedTemplateId" @change="onTemplateChange">
                  <option value="">— Select Template —</option>
                  <option v-for="t in tariffTemplates" :key="t.id" :value="t.id">
                    {{ t.name }}{{ t.region_name ? ` (${t.region_name})` : '' }}
                  </option>
                </select>
                <button class="calc-btn-icon" @click="reloadTemplates" title="Reload templates">🔄</button>
              </div>
            </div>
          </div>

          <!-- Tariff badges -->
          <div v-if="tariffDetails" class="calc-tariff-badges">
            <span class="calc-badge">{{ tariffDetails.billing_type }}</span>
            <span class="calc-badge calc-badge-neutral">Day {{ tariffDetails.billing_day || billDay }}</span>
            <span class="calc-badge calc-badge-neutral">VAT {{ tariffDetails.vat_rate }}%</span>
          </div>

          <!-- Tariff full details (collapsible) -->
          <details v-if="tariffDetails" class="calc-tariff-details">
            <summary class="calc-details-summary">📋 View Full Tariff Charges</summary>
            <div class="calc-tariff-body">
              <div v-if="tariffDetails.tiers && tariffDetails.tiers.length">
                <div class="calc-breakdown-subtitle">Water In Tiers</div>
                <table class="calc-table">
                  <thead><tr><th>Tier</th><th>Max (L)</th><th>Rate (R/kL)</th></tr></thead>
                  <tbody>
                    <tr v-for="(tier, i) in tariffDetails.tiers" :key="i">
                      <td>{{ i + 1 }}</td>
                      <td>{{ tier.max != null ? Number(tier.max).toLocaleString() : '∞' }}</td>
                      <td>{{ tier.rate }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div v-if="tariffDetails.fixed_costs && tariffDetails.fixed_costs.length">
                <div class="calc-breakdown-subtitle">Fixed Costs</div>
                <table class="calc-table">
                  <tbody>
                    <tr v-for="(c, i) in tariffDetails.fixed_costs" :key="i">
                      <td>{{ c.name }}</td>
                      <td class="calc-td-r">R {{ formatMoney(c.value) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div v-if="tariffDetails.customer_costs && tariffDetails.customer_costs.length">
                <div class="calc-breakdown-subtitle">Customer Costs</div>
                <table class="calc-table">
                  <tbody>
                    <tr v-for="(c, i) in tariffDetails.customer_costs" :key="i">
                      <td>{{ c.name }}</td>
                      <td class="calc-td-r">R {{ formatMoney(c.value) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </details>
        </section>

        <!-- ══════════════ TAB: PERIODS & READINGS ══════════════ -->
        <template v-if="activeTab === 'periods'">

          <!-- Section 2: Billing Period -->
          <section class="calc-section">
            <div class="calc-section-title">2️⃣ Billing Period</div>
            <div class="calc-row">
              <div class="calc-field">
                <label class="calc-label">Bill Day</label>
                <input type="number" v-model.number="billDay" min="1" max="31" class="calc-input" />
              </div>
              <div class="calc-field">
                <label class="calc-label">Start Month</label>
                <input type="month" v-model="startMonth" class="calc-input" />
              </div>
              <div class="calc-field">
                <label class="calc-label">Current Date</label>
                <input type="date" v-model="currentDate" class="calc-input" />
              </div>
            </div>
            <div v-if="periodStartDate" class="calc-period-preview">
              <span class="calc-badge calc-badge-info">
                Period: {{ periodStartDate }} → {{ periodEndDate }}
              </span>
            </div>
          </section>

          <!-- Section 3: Readings -->
          <section class="calc-section">
            <div class="calc-section-title">3️⃣ Readings</div>

            <div v-if="accountReadingHint" class="calc-notice">
              {{ accountReadingHint }}
              <button class="calc-btn-link" @click="applyAccountReadings">Apply</button>
            </div>

            <div class="calc-table-wrap">
              <table class="calc-table">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Reading Value</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(r, i) in readings" :key="i">
                    <td>
                      <div class="calc-date-display">{{ r.date ? formatDateDisplay(r.date) : '—' }}</div>
                      <input type="date" v-model="r.date" class="calc-input-sm calc-date-input" />
                    </td>
                    <td>
                      <input
                        type="number"
                        v-model.number="r.value"
                        step="0.01"
                        min="0"
                        class="calc-input-sm"
                        placeholder="0.00"
                      />
                    </td>
                    <td>
                      <button class="calc-btn-remove" @click="readings.splice(i, 1)">×</button>
                    </td>
                  </tr>
                  <tr v-if="readings.length === 0">
                    <td colspan="3" class="calc-empty-row">
                      No readings yet — click "+ Add Reading"
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="calc-actions-row">
              <button class="calc-btn-secondary" @click="addReading">+ Add Reading</button>
              <button
                class="calc-btn-calculate"
                @click="calculate"
                :disabled="!canCalculate || calculating"
              >
                {{ calculating ? 'Calculating…' : 'Calculate' }}
              </button>
            </div>

            <div v-if="calcError" class="calc-error-msg">⚠️ {{ calcError }}</div>

            <!-- ── RESULTS ── -->
            <div v-if="result" class="calc-results">

              <!-- Performance header card -->
              <div class="calc-perf-card">
                <div class="calc-perf-top">
                  <div>
                    <div class="calc-perf-title">BILLING PERFORMANCE</div>
                    <div class="calc-perf-dates">
                      {{ result.period_start_date || periodStartDate }}
                      &nbsp;→&nbsp;
                      {{ result.period_end_date || periodEndDate }}
                    </div>
                  </div>
                  <span
                    class="calc-status-badge calc-status-badge-lg"
                    :class="'status-' + String(result.status || 'provisional').toLowerCase()"
                  >{{ result.status || 'PROVISIONAL' }}</span>
                </div>
                <div class="calc-perf-stats">
                  <div class="calc-perf-stat">
                    <div class="calc-perf-stat-label">Avg Daily Usage</div>
                    <div class="calc-perf-stat-val">{{ result.daily_usage != null ? Number(result.daily_usage).toLocaleString() + ' L' : '—' }}</div>
                  </div>
                  <div class="calc-perf-stat">
                    <div class="calc-perf-stat-label">Total Usage</div>
                    <div class="calc-perf-stat-val">{{ formatUsage(result) }}</div>
                  </div>
                  <div class="calc-perf-stat calc-perf-total">
                    <div class="calc-perf-stat-label">Bill Total</div>
                    <div class="calc-perf-stat-val">R {{ formatMoney(result.bill_total ?? result.total_amount) }}</div>
                  </div>
                </div>
              </div>

              <!-- Detailed breakdown card -->
              <div class="calc-breakdown-card">
                <div class="calc-breakdown-header">Detailed Cost Breakdown</div>
                <div class="calc-breakdown-body">

                  <div class="calc-breakdown-subtitle">Consumption Charges</div>
                  <div class="calc-line-items">
                    <div class="calc-line-item">
                      <span>Usage charge (tiered)</span>
                      <span>R {{ formatMoney(result.usage_charge) }}</span>
                    </div>
                    <div v-if="resultBreakdown.discharge_charge" class="calc-line-item">
                      <span>Discharge (sewage)</span>
                      <span>R {{ formatMoney(resultBreakdown.discharge_charge) }}</span>
                    </div>
                    <div v-if="resultBreakdown.infrastructure_charge" class="calc-line-item">
                      <span>Infrastructure surcharge</span>
                      <span>R {{ formatMoney(resultBreakdown.infrastructure_charge) }}</span>
                    </div>
                  </div>

                  <hr class="calc-hr" />

                  <div class="calc-costs-grid">
                    <div>
                      <div class="calc-breakdown-subtitle">Fixed Charges</div>
                      <div class="calc-line-item">
                        <span>Fixed total</span>
                        <span>R {{ formatMoney(result.fixed_total) }}</span>
                      </div>
                    </div>
                    <div v-if="resultBreakdown.rates != null">
                      <div class="calc-breakdown-subtitle">Custom Charges</div>
                      <div class="calc-line-item">
                        <span>Rates</span>
                        <span>R {{ formatMoney(resultBreakdown.rates) }}</span>
                      </div>
                    </div>
                  </div>

                  <hr class="calc-hr" />

                  <div class="calc-totals-section">
                    <div class="calc-total-row">
                      <span>Subtotal (excl. VAT)</span>
                      <span>R {{ formatMoney(result.subtotal ?? result.usage_charge) }}</span>
                    </div>
                    <div class="calc-total-row">
                      <span>VAT ({{ tariffDetails?.vat_rate ?? 15 }}%)</span>
                      <span>R {{ formatMoney(result.vat_amount ?? result.vat) }}</span>
                    </div>
                    <div class="calc-total-row calc-grand-total-row">
                      <span>TOTAL</span>
                      <span>R {{ formatMoney(result.bill_total ?? result.total_amount) }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Save to account (User Account mode) -->
              <div v-if="mode === 'account' && accountData" class="calc-save-section">
                <button class="calc-btn-save" @click="saveBill" :disabled="saving">
                  {{ saving ? 'Saving…' : '💾 Save Calculation to Account' }}
                </button>
                <div v-if="saveMessage" :class="saveSuccess ? 'calc-msg-success' : 'calc-msg-error'">
                  {{ saveMessage }}
                </div>
              </div>

            </div><!-- end results -->
          </section>
        </template>

        <!-- ══════════════ TAB: SUMMARY ══════════════ -->
        <section v-if="activeTab === 'summary'" class="calc-section">
          <div class="calc-section-title">📋 Summary</div>
          <div v-if="result">
            <div class="calc-table-wrap">
              <table class="calc-table">
                <thead><tr><th>Item</th><th class="calc-td-r">Value</th></tr></thead>
                <tbody>
                  <tr>
                    <td>Period</td>
                    <td class="calc-td-r">{{ result.period_start_date || periodStartDate }} → {{ result.period_end_date || periodEndDate }}</td>
                  </tr>
                  <tr>
                    <td>Total Usage</td>
                    <td class="calc-td-r">{{ formatUsage(result) }}</td>
                  </tr>
                  <tr>
                    <td>Daily Usage</td>
                    <td class="calc-td-r">{{ result.daily_usage != null ? Number(result.daily_usage).toLocaleString() + ' L/day' : '—' }}</td>
                  </tr>
                  <tr>
                    <td>Usage Charge</td>
                    <td class="calc-td-r">R {{ formatMoney(result.usage_charge) }}</td>
                  </tr>
                  <tr v-if="resultBreakdown.discharge_charge">
                    <td>Discharge Charge</td>
                    <td class="calc-td-r">R {{ formatMoney(resultBreakdown.discharge_charge) }}</td>
                  </tr>
                  <tr v-if="resultBreakdown.infrastructure_charge">
                    <td>Infrastructure Surcharge</td>
                    <td class="calc-td-r">R {{ formatMoney(resultBreakdown.infrastructure_charge) }}</td>
                  </tr>
                  <tr>
                    <td>Fixed Total</td>
                    <td class="calc-td-r">R {{ formatMoney(result.fixed_total) }}</td>
                  </tr>
                  <tr>
                    <td>VAT</td>
                    <td class="calc-td-r">R {{ formatMoney(result.vat_amount ?? result.vat) }}</td>
                  </tr>
                  <tr class="calc-summary-total">
                    <td><strong>Grand Total</strong></td>
                    <td class="calc-td-r"><strong>R {{ formatMoney(result.bill_total ?? result.total_amount) }}</strong></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div v-else class="calc-empty-state">
            Run a calculation on the <strong>Periods &amp; Readings</strong> tab to see the summary.
          </div>
        </section>

        <!-- ══════════════ TAB: BILL PREVIEW ══════════════ -->
        <section v-if="activeTab === 'preview'" class="calc-section">
          <div class="calc-section-title">🧾 Bill Preview</div>
          <div v-if="result" class="calc-bill-preview">
            <div class="calc-preview-header">
              <div class="calc-preview-logo">
                <span class="calc-preview-logo-icon">🏙️</span>
                MyCities
              </div>
              <div class="calc-preview-to">
                <div v-if="accountData">
                  <strong>{{ accountData.account.name_on_bill || accountData.user.name }}</strong><br />
                  Acc: {{ accountData.account.account_number }}<br />
                  {{ accountData.site.address || '—' }}<br />
                  {{ accountData.site.region || '' }}
                </div>
                <div v-else>
                  <strong>Test Bill</strong><br />
                  Tariff: {{ tariffTemplates.find(t => String(t.id) === String(selectedTemplateId))?.name || '—' }}
                </div>
              </div>
            </div>
            <div class="calc-preview-period">
              <strong>Billing Period:</strong>
              {{ result.period_start_date || periodStartDate }} to {{ result.period_end_date || periodEndDate }}
            </div>
            <div class="calc-table-wrap">
              <table class="calc-table">
                <tbody>
                  <tr>
                    <td>Water Consumption</td>
                    <td class="calc-td-r">R {{ formatMoney(result.usage_charge) }}</td>
                  </tr>
                  <tr v-if="resultBreakdown.discharge_charge">
                    <td>Sewage / Discharge</td>
                    <td class="calc-td-r">R {{ formatMoney(resultBreakdown.discharge_charge) }}</td>
                  </tr>
                  <tr v-if="resultBreakdown.infrastructure_charge">
                    <td>Infrastructure Surcharge</td>
                    <td class="calc-td-r">R {{ formatMoney(resultBreakdown.infrastructure_charge) }}</td>
                  </tr>
                  <tr>
                    <td>Fixed Charges</td>
                    <td class="calc-td-r">R {{ formatMoney(result.fixed_total) }}</td>
                  </tr>
                  <tr>
                    <td>VAT ({{ tariffDetails?.vat_rate ?? 15 }}%)</td>
                    <td class="calc-td-r">R {{ formatMoney(result.vat_amount ?? result.vat) }}</td>
                  </tr>
                  <tr class="calc-preview-total-row">
                    <td><strong>Amount Due</strong></td>
                    <td class="calc-td-r"><strong>R {{ formatMoney(result.bill_total ?? result.total_amount) }}</strong></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div v-else class="calc-empty-state">
            Run a calculation to preview the bill.
          </div>
        </section>

      </main><!-- end calc-right -->
    </div><!-- end calc-shell -->
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

// ── Props ──────────────────────────────────────────────────────────────────────
const props = defineProps({
  users:           { type: Array,  default: () => [] },
  tariffTemplates: { type: Array,  default: () => [] },
  today:           { type: String, default: '' },
})

// ── Navigation ─────────────────────────────────────────────────────────────────
const tabs = [
  { id: 'periods', icon: '📅', label: 'Periods & Readings' },
  { id: 'summary', icon: '📋', label: 'Summary' },
  { id: 'preview', icon: '🧾', label: 'Bill Preview' },
]
const activeTab = ref('periods')

// ── Mode ───────────────────────────────────────────────────────────────────────
const mode = ref('test')

function setMode(m) {
  mode.value          = m
  accountData.value   = null
  searchQuery.value   = ''
  searchResults.value = []
  selectedUserId.value   = ''
  selectedAccountId.value = ''
  readings.value      = []
  result.value        = null
  calcError.value     = ''
  accountReadingHint.value = ''
}

// ── Users (Test Bill mode) ─────────────────────────────────────────────────────
const users            = ref(props.users || [])
const selectedUserId   = ref('')
const selectedAccountId = ref('')

const filteredAccounts = computed(() => {
  const u = users.value.find(u => String(u.id) === String(selectedUserId.value))
  return u?.accounts || []
})

function onUserChange() {
  selectedAccountId.value = ''
  accountData.value       = null
  readings.value          = []
  accountReadingHint.value = ''
}

async function onAccountSelectChange() {
  if (!selectedAccountId.value) return
  await loadAccountById(selectedAccountId.value)
}

// ── Account Search (User Account mode) ────────────────────────────────────────
const searchQuery   = ref('')
const searchResults = ref([])
let   searchTimer   = null

function onSearchInput() {
  clearTimeout(searchTimer)
  if (searchQuery.value.length < 2) { searchResults.value = []; return }
  searchTimer = setTimeout(() => doSearch(searchQuery.value), 300)
}

async function doSearch(q) {
  try {
    const res = await apiFetch(`/admin/billing-calculator/search-accounts?q=${encodeURIComponent(q)}`)
    searchResults.value = res.accounts || []
  } catch { searchResults.value = [] }
}

async function selectSearchAccount(item) {
  searchQuery.value   = `${item.account_name} (${item.account_number})`
  searchResults.value = []
  await loadAccountById(item.id)
}

// ── Account Data ───────────────────────────────────────────────────────────────
const accountData        = ref(null)
const accountReadingHint = ref('')

async function loadAccountById(id) {
  try {
    const res = await apiFetch(`/admin/billing-calculator/account/${id}`)
    if (!res.success) return
    accountData.value = res.data

    // Pre-fill tariff from account
    if (res.data.account?.tariff_template?.id) {
      selectedTemplateId.value = String(res.data.account.tariff_template.id)
      await onTemplateChange()
    }

    // Pre-fill bill day
    if (res.data.account?.bill_day) {
      billDay.value = res.data.account.bill_day
    }

    // Reading hint
    const allReadings = (res.data.meters || []).flatMap(m => m.readings || [])
    accountReadingHint.value = allReadings.length > 0
      ? `${allReadings.length} readings available from this account.`
      : ''
  } catch (e) {
    console.error('loadAccountById error', e)
  }
}

function applyAccountReadings() {
  if (!accountData.value) return

  const meters = accountData.value.meters || []

  // Determine relevant meter type from tariff template
  const template = tariffTemplates.value.find(t => String(t.id) === String(selectedTemplateId.value))
  const isElec = template?.is_electricity && !template?.is_water

  // Filter to the relevant meter type
  let relevant = isElec
    ? meters.filter(m => /elec/i.test(m.meter_title || '') || m.meter_type_id === 2)
    : meters.filter(m => /water/i.test(m.meter_title || '') || m.meter_type_id === 1)

  // Fall back to all meters if filter yields nothing
  if (relevant.length === 0) relevant = meters

  // Flatten, sort descending (most recent first), deduplicate by date
  const flat = relevant.flatMap(m => m.readings || [])
  flat.sort((a, b) => new Date(b.date) - new Date(a.date))

  const seen = new Set()
  const deduped = flat.filter(r => {
    if (seen.has(r.date)) return false
    seen.add(r.date)
    return true
  })

  readings.value = deduped.map(r => ({ date: r.date, value: Number(r.value) }))
  accountReadingHint.value = ''
}

// ── Tariff Templates ───────────────────────────────────────────────────────────
const tariffTemplates    = ref(props.tariffTemplates || [])
const selectedTemplateId = ref('')
const tariffDetails      = ref(null)
const billingMode        = ref('period')

async function onTemplateChange() {
  if (!selectedTemplateId.value) { tariffDetails.value = null; return }
  try {
    const res = await apiPost('/admin/billing-calculator/tariff-template-details', {
      template_id: selectedTemplateId.value,
    })
    if (res.success) {
      tariffDetails.value = res.data
      if (res.data.billing_day) billDay.value = res.data.billing_day
    }
  } catch (e) {
    console.error('onTemplateChange error', e)
  }
}

async function reloadTemplates() {
  try {
    const res = await apiFetch('/admin/billing-calculator/tariff-templates')
    if (res.success) tariffTemplates.value = res.data
  } catch (e) {
    console.error('reloadTemplates error', e)
  }
}

// ── Billing Period ─────────────────────────────────────────────────────────────
const billDay    = ref(20)
const startMonth = ref(props.today ? props.today.slice(0, 7) : new Date().toISOString().slice(0, 7))
const currentDate = ref(props.today || new Date().toISOString().slice(0, 10))

const periodStartDate = computed(() => {
  if (!startMonth.value || !billDay.value) return ''
  return `${startMonth.value}-${String(billDay.value).padStart(2, '0')}`
})

const periodEndDate = computed(() => {
  if (!periodStartDate.value) return ''
  const d = new Date(periodStartDate.value)
  d.setMonth(d.getMonth() + 1)
  d.setDate(d.getDate() - 1)
  return d.toISOString().slice(0, 10)
})

// ── Readings ───────────────────────────────────────────────────────────────────
const readings = ref([])

function addReading() {
  readings.value.push({ date: currentDate.value, value: '' })
}

// ── Calculate ──────────────────────────────────────────────────────────────────
const calculating = ref(false)
const calcError   = ref('')
const result      = ref(null)
let   lastBillId  = null

const canCalculate = computed(() =>
  selectedTemplateId.value &&
  readings.value.some(r => r.date && r.value !== '' && r.value !== null) &&
  periodStartDate.value
)

const resultBreakdown = computed(() => result.value?.breakdown || {})

async function calculate() {
  calculating.value = true
  calcError.value   = ''
  result.value      = null
  lastBillId        = null

  try {
    // Step 1: create test bill record in the DB
    const payload = {
      tariff_template_id: parseInt(selectedTemplateId.value),
      period_start_date:  periodStartDate.value,
      period_end_date:    periodEndDate.value,
      readings: readings.value
        .filter(r => r.date && r.value !== '' && r.value !== null)
        .map(r => ({ date: r.date, value: parseFloat(r.value) })),
    }

    const billRes = await apiPost('/admin/billing-calculator/create-test-bill', payload)
    if (!billRes.success) {
      calcError.value = billRes.message
        || (billRes.errors ? Object.values(billRes.errors).flat().join('; ') : 'Failed to create bill record')
      return
    }
    lastBillId = billRes.data.bill_id

    // Step 2: compute using Calculator.php (clean PD.md implementation)
    const computeRes = await apiPost('/admin/calculator/compute', { bill_id: lastBillId })
    if (computeRes.success === false) {
      calcError.value = computeRes.message || 'Calculation failed'
      return
    }

    // Result may be nested under .data or flat
    result.value = computeRes.data ?? computeRes
  } catch (e) {
    calcError.value = e.message || 'Unexpected error during calculation'
  } finally {
    calculating.value = false
  }
}

// ── Save Bill ──────────────────────────────────────────────────────────────────
const saving      = ref(false)
const saveMessage = ref('')
const saveSuccess = ref(false)

async function saveBill() {
  if (!lastBillId) return
  saving.value      = true
  saveMessage.value = ''
  try {
    const res = await apiPost('/admin/billing-calculator/save-bills', { bill_ids: [lastBillId] })
    saveSuccess.value = !!res.success
    saveMessage.value = res.message || (res.success ? 'Saved successfully.' : 'Save failed.')
  } catch (e) {
    saveSuccess.value = false
    saveMessage.value = e.message
  } finally {
    saving.value = false
  }
}

// ── Helpers ────────────────────────────────────────────────────────────────────
function formatMoney(val) {
  const n = parseFloat(String(val ?? '0').replace(/,/g, ''))
  return isNaN(n) ? '0.00' : n.toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function formatUsage(r) {
  const l = r?.total_usage ?? r?.breakdown?.usage_l ?? r?.consumption ?? null
  if (l === null || l === undefined) return '—'
  const n = parseFloat(l)
  return isNaN(n) ? '—' : `${n.toLocaleString()} L`
}

function formatDateDisplay(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr + 'T00:00:00')
  return d.toLocaleDateString('en-ZA', { day: 'numeric', month: 'long', year: 'numeric' })
}

// Use window.axios (configured in bootstrap.js) — automatically sends XSRF-TOKEN cookie
async function apiFetch(url) {
  const res = await window.axios.get(url)
  return res.data
}

async function apiPost(url, data) {
  try {
    const res = await window.axios.post(url, data)
    return res.data
  } catch (err) {
    // Axios throws on 4xx/5xx — extract server message if available
    const serverData = err.response?.data
    if (serverData) return serverData
    throw err
  }
}
</script>

<style scoped>
/* ── Shell layout ─────────────────────────────────────────────────────────── */
.calc-shell {
  display: flex;
  min-height: calc(100vh - 60px);
  background: #f4f6f9;
  font-family: 'Nunito', sans-serif;
}

/* ── Left panel ───────────────────────────────────────────────────────────── */
.calc-left {
  width: 220px;
  min-width: 220px;
  background: #2c3e50;
  color: #ecf0f1;
  display: flex;
  flex-direction: column;
  padding: 0 0 1rem;
  position: sticky;
  top: 0;
  height: 100vh;
  overflow-y: auto;
}

.calc-left-header {
  padding: 1.2rem 1rem 0.8rem;
  border-bottom: 1px solid rgba(255,255,255,0.1);
}
.calc-left-title {
  font-size: 1rem;
  font-weight: 700;
  color: #fff;
}
.calc-left-sub {
  font-size: 0.68rem;
  color: rgba(255,255,255,0.45);
  margin-top: 2px;
}

/* Mode switch */
.calc-mode-group {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 0.75rem 0.75rem 0.5rem;
}
.calc-mode-btn {
  padding: 0.45rem 0.75rem;
  border: none;
  border-radius: 6px;
  font-size: 0.8rem;
  cursor: pointer;
  text-align: left;
  background: rgba(255,255,255,0.08);
  color: rgba(255,255,255,0.7);
  transition: background 0.15s, color 0.15s;
}
.calc-mode-btn.active,
.calc-mode-btn:hover {
  background: #3498db;
  color: #fff;
}

/* Nav tabs */
.calc-nav {
  display: flex;
  flex-direction: column;
  padding: 0.25rem 0.75rem;
  gap: 2px;
}
.calc-nav-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem;
  border: none;
  border-radius: 6px;
  font-size: 0.82rem;
  cursor: pointer;
  text-align: left;
  background: transparent;
  color: rgba(255,255,255,0.6);
  transition: background 0.15s, color 0.15s;
}
.calc-nav-btn.active,
.calc-nav-btn:hover {
  background: rgba(255,255,255,0.12);
  color: #fff;
}
.calc-nav-icon { font-size: 0.95rem; }

/* Status in sidebar */
.calc-left-status {
  margin: auto 0.75rem 0;
  padding: 0.75rem;
  background: rgba(255,255,255,0.07);
  border-radius: 8px;
  font-size: 0.75rem;
}
.calc-left-status-label { color: rgba(255,255,255,0.45); margin-bottom: 2px; }
.calc-left-status-total { font-size: 1.1rem; font-weight: 700; color: #2ecc71; }
.calc-left-status-period { color: rgba(255,255,255,0.5); margin-top: 2px; }

/* ── Right panel ──────────────────────────────────────────────────────────── */
.calc-right {
  flex: 1;
  overflow-y: auto;
  padding: 1.5rem 2rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

/* ── Sections ─────────────────────────────────────────────────────────────── */
.calc-section {
  background: #fff;
  border-radius: 10px;
  padding: 1.25rem 1.5rem;
  box-shadow: 0 1px 4px rgba(0,0,0,0.07);
}
.calc-section-title {
  font-weight: 700;
  font-size: 0.95rem;
  color: #2c3e50;
  margin-bottom: 1rem;
}
.calc-muted { font-weight: 400; color: #aaa; }

/* Info section */
.calc-info-section { background: #f8f9fc; }
.calc-details-summary {
  font-weight: 600;
  font-size: 0.88rem;
  color: #4a5568;
  cursor: pointer;
  user-select: none;
  padding: 0.25rem 0;
}
.calc-info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 0.6rem;
  margin-top: 0.75rem;
}
.calc-info-item {
  display: flex;
  flex-direction: column;
  font-size: 0.82rem;
}
.calc-info-item span { color: #888; margin-bottom: 1px; }
.calc-info-item strong { color: #2d3748; }

/* ── Form controls ────────────────────────────────────────────────────────── */
.calc-row {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}
.calc-field {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 160px;
}
.calc-label {
  font-size: 0.78rem;
  font-weight: 600;
  color: #5a6070;
  margin-bottom: 0.3rem;
}
.calc-input,
.calc-select {
  padding: 0.5rem 0.7rem;
  border: 1px solid #d1d3e2;
  border-radius: 6px;
  font-size: 0.88rem;
  color: #2d3748;
  background: #fff;
  box-sizing: border-box;
  width: 100%;
  transition: border-color 0.15s;
}
.calc-input:focus,
.calc-select:focus { border-color: #3498db; outline: none; }
.calc-select:disabled { background: #f0f0f0; cursor: not-allowed; }
.calc-input-sm {
  padding: 0.35rem 0.5rem;
  border: 1px solid #d1d3e2;
  border-radius: 5px;
  font-size: 0.85rem;
  width: 100%;
  box-sizing: border-box;
}
.calc-input-with-btn {
  display: flex;
  gap: 0.4rem;
  align-items: stretch;
}
.calc-input-with-btn .calc-select { flex: 1; }

/* Period preview */
.calc-period-preview { margin-top: 0.75rem; }

/* ── Buttons ──────────────────────────────────────────────────────────────── */
.calc-btn-icon {
  padding: 0 0.65rem;
  border: 1px solid #d1d3e2;
  border-radius: 6px;
  background: #fff;
  cursor: pointer;
  font-size: 1rem;
  transition: background 0.15s;
}
.calc-btn-icon:hover { background: #f0f0f0; }

.calc-btn-secondary {
  padding: 0.45rem 1rem;
  border: 1px solid #3498db;
  border-radius: 6px;
  background: transparent;
  color: #3498db;
  font-size: 0.85rem;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}
.calc-btn-secondary:hover {
  background: #3498db;
  color: #fff;
}

.calc-btn-calculate {
  padding: 0.5rem 1.75rem;
  background: linear-gradient(135deg, #2980b9, #3498db);
  color: #fff;
  border: none;
  border-radius: 6px;
  font-size: 0.9rem;
  font-weight: 700;
  cursor: pointer;
  transition: opacity 0.15s;
}
.calc-btn-calculate:disabled { opacity: 0.55; cursor: not-allowed; }

.calc-btn-save {
  padding: 0.55rem 1.5rem;
  background: #27ae60;
  color: #fff;
  border: none;
  border-radius: 6px;
  font-size: 0.9rem;
  font-weight: 700;
  cursor: pointer;
}
.calc-btn-save:disabled { opacity: 0.55; cursor: not-allowed; }

.calc-btn-remove {
  background: none;
  border: none;
  color: #e74c3c;
  font-size: 1.1rem;
  cursor: pointer;
  line-height: 1;
  padding: 0 0.4rem;
}
.calc-btn-link {
  background: none;
  border: none;
  color: #3498db;
  font-size: 0.82rem;
  cursor: pointer;
  text-decoration: underline;
  padding: 0 0.25rem;
}

/* ── Badges & status ──────────────────────────────────────────────────────── */
.calc-badge {
  display: inline-block;
  padding: 0.2rem 0.6rem;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 700;
  background: #3498db;
  color: #fff;
  margin-right: 0.4rem;
}
.calc-badge-neutral { background: #95a5a6; }
.calc-badge-info    { background: #2980b9; }

.calc-status-badge {
  display: inline-block;
  padding: 0.2rem 0.55rem;
  border-radius: 4px;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
}
.calc-status-badge-lg { font-size: 0.82rem; padding: 0.3rem 0.75rem; }

.status-actual     { background: #27ae60; color: #fff; }
.status-provisional { background: #e67e22; color: #fff; }
.status-estimated  { background: #8e44ad; color: #fff; }

/* ── Tariff details ───────────────────────────────────────────────────────── */
.calc-tariff-badges { margin-top: 0.75rem; }
.calc-tariff-details { margin-top: 0.75rem; }
.calc-tariff-body {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 1rem;
  margin-top: 0.75rem;
}

/* ── Search ───────────────────────────────────────────────────────────────── */
.calc-search-box {
  position: relative;
  display: flex;
  align-items: center;
  max-width: 600px;
}
.calc-search-icon {
  position: absolute;
  left: 0.75rem;
  font-size: 0.9rem;
  pointer-events: none;
}
.calc-search-input {
  width: 100%;
  padding: 0.55rem 0.75rem 0.55rem 2.25rem;
  border: 1px solid #d1d3e2;
  border-radius: 8px;
  font-size: 0.9rem;
  box-sizing: border-box;
  transition: border-color 0.15s;
}
.calc-search-input:focus { border-color: #3498db; outline: none; }
.calc-search-dropdown {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: #fff;
  border: 1px solid #d1d3e2;
  border-radius: 8px;
  z-index: 100;
  max-height: 300px;
  overflow-y: auto;
  box-shadow: 0 4px 16px rgba(0,0,0,0.12);
}
.calc-search-item {
  padding: 0.6rem 1rem;
  cursor: pointer;
  transition: background 0.1s;
  border-bottom: 1px solid #f0f0f0;
}
.calc-search-item:hover { background: #f0f7ff; }
.calc-search-item:last-child { border-bottom: none; }
.calc-search-name { font-weight: 600; font-size: 0.88rem; color: #2d3748; }
.calc-search-detail { font-size: 0.78rem; color: #888; margin-top: 1px; }

/* ── Notice ───────────────────────────────────────────────────────────────── */
.calc-notice {
  background: #ebf8ff;
  border: 1px solid #90cdf4;
  border-radius: 6px;
  padding: 0.5rem 0.75rem;
  font-size: 0.82rem;
  color: #2b6cb0;
  margin-bottom: 0.75rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

/* ── Table ────────────────────────────────────────────────────────────────── */
.calc-table-wrap { overflow-x: auto; }
.calc-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.85rem;
}
.calc-table th {
  background: #f8f9fc;
  padding: 0.5rem 0.75rem;
  text-align: left;
  font-weight: 700;
  font-size: 0.78rem;
  color: #5a6070;
  border-bottom: 2px solid #e2e8f0;
}
.calc-table td {
  padding: 0.45rem 0.75rem;
  border-bottom: 1px solid #f0f0f0;
  vertical-align: middle;
}
.calc-table tr:last-child td { border-bottom: none; }
.calc-table tr:hover td { background: #fafbff; }
.calc-td-r { text-align: right; }
.calc-empty-row { text-align: center; color: #aaa; font-style: italic; padding: 1.5rem; }
.calc-summary-total td,
.calc-preview-total-row td { background: #f0f7ff !important; font-size: 0.92rem; }

/* ── Actions ──────────────────────────────────────────────────────────────── */
.calc-actions-row {
  display: flex;
  gap: 0.75rem;
  align-items: center;
  margin-top: 0.75rem;
  flex-wrap: wrap;
}

/* ── Error / messages ─────────────────────────────────────────────────────── */
.calc-error-msg {
  margin-top: 0.75rem;
  padding: 0.65rem 1rem;
  background: #fff5f5;
  border: 1px solid #feb2b2;
  border-radius: 6px;
  color: #c53030;
  font-size: 0.85rem;
}
.calc-msg-success {
  margin-top: 0.5rem;
  color: #27ae60;
  font-size: 0.85rem;
  font-weight: 600;
}
.calc-msg-error {
  margin-top: 0.5rem;
  color: #e74c3c;
  font-size: 0.85rem;
  font-weight: 600;
}

/* ── Results ──────────────────────────────────────────────────────────────── */
.calc-results {
  margin-top: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

/* Performance card */
.calc-perf-card {
  background: linear-gradient(135deg, #1a252f, #2c3e50);
  color: #fff;
  border-radius: 10px;
  padding: 1.25rem 1.5rem;
}
.calc-perf-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}
.calc-perf-title {
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  color: rgba(255,255,255,0.55);
  text-transform: uppercase;
}
.calc-perf-dates {
  font-size: 0.88rem;
  color: rgba(255,255,255,0.8);
  margin-top: 2px;
}
.calc-perf-stats {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
  gap: 0.75rem;
}
.calc-perf-stat {
  background: rgba(255,255,255,0.07);
  border-radius: 8px;
  padding: 0.65rem 0.75rem;
}
.calc-perf-total { background: rgba(52, 152, 219, 0.25); }
.calc-perf-stat-label {
  font-size: 0.68rem;
  color: rgba(255,255,255,0.5);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 3px;
}
.calc-perf-stat-val {
  font-size: 1.05rem;
  font-weight: 700;
  color: #fff;
}

/* Breakdown card */
.calc-breakdown-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  overflow: hidden;
}
.calc-breakdown-header {
  padding: 0.75rem 1.25rem;
  background: #f8f9fc;
  font-weight: 700;
  font-size: 0.9rem;
  color: #2d3748;
  border-bottom: 1px solid #e2e8f0;
}
.calc-breakdown-body { padding: 1rem 1.25rem; }
.calc-breakdown-subtitle {
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #718096;
  margin-bottom: 0.4rem;
  margin-top: 0.5rem;
}
.calc-breakdown-subtitle:first-child { margin-top: 0; }
.calc-line-items { display: flex; flex-direction: column; gap: 2px; }
.calc-line-item {
  display: flex;
  justify-content: space-between;
  font-size: 0.86rem;
  padding: 0.3rem 0;
  color: #4a5568;
  border-bottom: 1px dashed #f0f0f0;
}
.calc-line-item:last-child { border-bottom: none; }
.calc-hr { border: none; border-top: 1px solid #e2e8f0; margin: 0.75rem 0; }
.calc-costs-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}
.calc-totals-section { margin-top: 0.25rem; }
.calc-total-row {
  display: flex;
  justify-content: space-between;
  padding: 0.35rem 0;
  font-size: 0.88rem;
  color: #4a5568;
}
.calc-grand-total-row {
  border-top: 2px solid #2c3e50;
  margin-top: 0.25rem;
  padding-top: 0.5rem;
  font-weight: 700;
  font-size: 1rem;
  color: #2c3e50;
}

/* Save section */
.calc-save-section { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }

/* ── Summary ──────────────────────────────────────────────────────────────── */
.calc-summary-table-wrap { max-width: 600px; }

/* ── Bill Preview ─────────────────────────────────────────────────────────── */
.calc-bill-preview {
  max-width: 560px;
  border: 2px solid #2c3e50;
  border-radius: 10px;
  overflow: hidden;
  font-size: 0.88rem;
}
.calc-preview-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 1.25rem 1.5rem;
  background: #2c3e50;
  color: #fff;
}
.calc-preview-logo {
  font-size: 1.3rem;
  font-weight: 900;
  display: flex;
  align-items: center;
  gap: 0.4rem;
}
.calc-preview-logo-icon { font-size: 1.5rem; }
.calc-preview-to { text-align: right; font-size: 0.82rem; line-height: 1.5; }
.calc-preview-period {
  padding: 0.5rem 1.5rem;
  background: #ecf0f1;
  font-size: 0.82rem;
  color: #5a6070;
}
.calc-bill-preview .calc-table { margin: 0; }
.calc-bill-preview .calc-table td { padding: 0.55rem 1.5rem; }
.calc-bill-preview .calc-preview-total-row td {
  background: #f0f7ff;
  font-weight: 700;
  font-size: 0.95rem;
}

/* ── Empty state ──────────────────────────────────────────────────────────── */
.calc-empty-state {
  padding: 2rem;
  text-align: center;
  color: #aaa;
  font-size: 0.9rem;
}

/* ── Date display ─────────────────────────────────────────────────────────── */
.calc-date-display {
  font-size: 0.8rem;
  font-weight: 600;
  color: #2d3748;
  margin-bottom: 2px;
  white-space: nowrap;
}
.calc-date-input {
  font-size: 0.72rem;
  color: #888;
  width: 130px;
}

/* ── Responsive ───────────────────────────────────────────────────────────── */
@media (max-width: 768px) {
  .calc-shell { flex-direction: column; }
  .calc-left {
    width: 100%;
    min-width: 0;
    height: auto;
    position: static;
    flex-direction: row;
    flex-wrap: wrap;
    align-items: center;
    padding: 0.5rem;
    gap: 0.5rem;
  }
  .calc-left-header { padding: 0.5rem; border-bottom: none; }
  .calc-mode-group { flex-direction: row; padding: 0; }
  .calc-nav { flex-direction: row; padding: 0; }
  .calc-right { padding: 1rem; }
  .calc-costs-grid { grid-template-columns: 1fr; }
}
</style>
