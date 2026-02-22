<template>
  <UserAppLayout>

    <!-- TEAL TOP BAR: date + period navigation -->
    <div class="dash-topbar">
      <button class="dash-nav-btn" @click="changePeriod('back')" :disabled="loading">◄</button>
      <div class="dash-topbar-center">
        <span class="dash-date">Current date: {{ today }}</span>
        <span v-if="readingDueInDays !== null && readingDueInDays >= 0" class="dash-due-pill">
          First Reading due in {{ readingDueInDays }} days
        </span>
      </div>
      <button class="dash-nav-btn" @click="changePeriod('forward')"
              :disabled="loading || currentPeriodIndex >= 0">►</button>
    </div>

    <!-- PERIOD LABEL -->
    <div class="dash-period" v-if="periodLabel">
      Period: {{ periodLabel }}
    </div>

    <!-- GRAND TOTAL -->
    <div class="dash-grand-total">
      R{{ grandTotal }}
    </div>

    <!-- WATER SECTION -->
    <div class="dash-section" v-if="waterBill">
      <div class="dash-section-header">
        <div class="dash-section-left">
          <i class="fas fa-tint dash-icon dash-icon--water"></i>
          <span class="dash-section-name">Water</span>
        </div>
        <div class="dash-section-stats">
          <div class="dash-stat">
            <span class="dash-stat-label">Daily Usage</span>
            <span class="dash-stat-value">{{ waterBill.daily_usage }} L</span>
          </div>
          <div class="dash-stat">
            <span class="dash-stat-label">Total Usage</span>
            <span class="dash-stat-value">{{ waterBill.total_usage }} L</span>
          </div>
          <div class="dash-stat">
            <span class="dash-stat-label">Daily Cost</span>
            <span class="dash-stat-value">R{{ dailyCost(waterBill) }}</span>
          </div>
        </div>
        <div class="dash-section-actions">
          <Link :href="route('user.reading.water')" class="dash-link">Enter reading</Link>
          <span class="dash-link-sep">|</span>
          <Link :href="route('user.reading.history')" class="dash-link">View History</Link>
          <span class="dash-link-sep">|</span>
          <button class="dash-link" @click="waterExpanded = !waterExpanded">
            {{ waterExpanded ? 'Hide Details' : 'Show Details' }}
          </button>
        </div>
      </div>

      <!-- Expanded water breakdown -->
      <transition name="expand">
        <div class="dash-section-detail" v-if="waterExpanded">
          <div class="dash-proj-line">
            Projected charges &rarr;
            <span class="dash-proj-amount">R {{ waterBill.projected_charge }}</span>
          </div>
          <div class="dash-breakdown">
            <div class="dash-breakdown-row">
              <span>Consumption Charges</span>
              <span>R{{ waterBill.consumption_charge }}</span>
            </div>
            <div class="dash-breakdown-row">
              <span>Discharge charges</span>
              <span>R{{ waterBill.discharge_charge }}</span>
            </div>
            <div class="dash-breakdown-row">
              <span>Infrastructure Surcharge</span>
              <span>R{{ waterBill.infrastructure_charge }}</span>
            </div>
            <div class="dash-breakdown-row">
              <span>VAT</span>
              <span>R{{ waterBill.vat_amount }}</span>
            </div>
            <div class="dash-breakdown-row">
              <span>Rates</span>
              <span>R{{ waterBill.rates }}</span>
            </div>
          </div>
        </div>
      </transition>
    </div>

    <!-- ELECTRICITY SECTION -->
    <div class="dash-section" v-if="electricityBill">
      <div class="dash-section-header">
        <div class="dash-section-left">
          <i class="fas fa-bolt dash-icon dash-icon--elec"></i>
          <span class="dash-section-name">Electricity</span>
        </div>
        <div class="dash-section-stats">
          <div class="dash-stat">
            <span class="dash-stat-label">Daily Usage</span>
            <span class="dash-stat-value">{{ electricityBill.daily_usage }} kWh</span>
          </div>
          <div class="dash-stat">
            <span class="dash-stat-label">Total Usage</span>
            <span class="dash-stat-value">{{ electricityBill.total_usage }} kWh</span>
          </div>
          <div class="dash-stat">
            <span class="dash-stat-label">Daily Cost</span>
            <span class="dash-stat-value">R{{ dailyCost(electricityBill) }}</span>
          </div>
        </div>
        <div class="dash-section-actions">
          <Link :href="route('user.reading.electricity')" class="dash-link">Enter reading</Link>
          <span class="dash-link-sep">|</span>
          <Link :href="route('user.reading.history')" class="dash-link">View History</Link>
          <span class="dash-link-sep">|</span>
          <button class="dash-link" @click="elecExpanded = !elecExpanded">
            {{ elecExpanded ? 'Hide Details' : 'Show Details' }}
          </button>
        </div>
      </div>

      <transition name="expand">
        <div class="dash-section-detail" v-if="elecExpanded">
          <div class="dash-proj-line">
            Projected charges &rarr;
            <span class="dash-proj-amount">R {{ electricityBill.projected_charge }}</span>
          </div>
          <div class="dash-breakdown">
            <div class="dash-breakdown-row">
              <span>Consumption Charges</span>
              <span>R{{ electricityBill.consumption_charge }}</span>
            </div>
            <div class="dash-breakdown-row">
              <span>Infrastructure Surcharge</span>
              <span>R{{ electricityBill.infrastructure_charge }}</span>
            </div>
            <div class="dash-breakdown-row">
              <span>VAT</span>
              <span>R{{ electricityBill.vat_amount }}</span>
            </div>
            <div class="dash-breakdown-row">
              <span>Rates</span>
              <span>R{{ electricityBill.rates }}</span>
            </div>
          </div>
        </div>
      </transition>
    </div>

    <!-- GRAND TOTAL FOOTER LINE -->
    <div class="dash-total-line" v-if="waterBill || electricityBill">
      <span class="dash-total-label">Total</span>
      <span class="dash-total-amount">R{{ grandTotal }}</span>
    </div>

    <!-- EMPTY STATE -->
    <div class="dash-empty" v-if="!waterBill && !electricityBill">
      <i class="fas fa-info-circle"></i>
      <p>No billing data available yet.</p>
      <p class="dash-empty-sub">Your account is being set up. Check back soon.</p>
    </div>

  </UserAppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import UserAppLayout from '@/Layouts/UserAppLayout.vue'

const props = defineProps({
  accounts:         { type: Array,  default: () => [] },
  currentAccount:   { type: Object, default: null },
  waterBill:        { type: Object, default: null },
  electricityBill:  { type: Object, default: null },
  readingDueInDays: { type: Number, default: null },
  periodLabel:      { type: String, default: '' },
  periodIndex:      { type: Number, default: 0 },
  today:            { type: String, default: '' },
})

const waterExpanded      = ref(false)
const elecExpanded       = ref(false)
const loading            = ref(false)
const currentPeriodIndex = ref(props.periodIndex)

const grandTotal = computed(() => {
  const w = parseFloat(String(props.waterBill?.bill_total ?? '0').replace(/,/g, ''))
  const e = parseFloat(String(props.electricityBill?.bill_total ?? '0').replace(/,/g, ''))
  const total = (isNaN(w) ? 0 : w) + (isNaN(e) ? 0 : e)
  return total.toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
})

const dailyCost = (bill) => {
  if (!bill?.bill_total || !bill?.period_start_date || !bill?.period_end_date) return '0.00'
  const start = new Date(bill.period_start_date)
  const end   = new Date(bill.period_end_date)
  const days  = Math.max(1, Math.round((end - start) / 86400000) + 1)
  const total = parseFloat(String(bill.bill_total).replace(/,/g, ''))
  return isNaN(total) ? '0.00' : (total / days).toFixed(2)
}

const changePeriod = (direction) => {
  if (direction === 'back') {
    currentPeriodIndex.value = currentPeriodIndex.value - 1
  } else {
    if (currentPeriodIndex.value >= 0) return
    currentPeriodIndex.value = currentPeriodIndex.value + 1
  }
  loading.value = true
  router.get(route('user.dashboard'), { period: currentPeriodIndex.value }, {
    preserveState: false,
    onFinish: () => { loading.value = false },
  })
}
</script>

<style scoped>
.dash-topbar {
  background: var(--ua-primary, #009BA4);
  color: #fff;
  display: flex;
  align-items: center;
  padding: 8px 12px;
  gap: 8px;
  min-height: 48px;
}

.dash-nav-btn {
  background: rgba(255,255,255,0.2);
  border: none;
  color: #fff;
  border-radius: 4px;
  width: 32px;
  height: 32px;
  cursor: pointer;
  font-size: 0.85rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: background 0.1s;
}

.dash-nav-btn:hover:not(:disabled) { background: rgba(255,255,255,0.3); }
.dash-nav-btn:disabled              { opacity: 0.4; cursor: not-allowed; }

.dash-topbar-center {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}

.dash-date {
  font-size: 0.78rem;
  font-weight: 500;
}

.dash-due-pill {
  background: var(--ua-orange, #FF9800);
  color: #fff;
  border-radius: 12px;
  padding: 2px 10px;
  font-size: 0.68rem;
  font-weight: 700;
}

.dash-period {
  background: var(--ua-primary, #009BA4);
  color: rgba(255,255,255,0.85);
  text-align: center;
  font-size: 0.72rem;
  padding: 5px 12px;
}

.dash-grand-total {
  font-size: 2.6rem;
  font-weight: 700;
  color: var(--ua-amount, #1565C0);
  text-align: center;
  padding: 18px 16px 14px;
  background: var(--ua-card, #fff);
  border-bottom: 1px solid var(--ua-divider, #E0E0E0);
}

/* ── SECTION ── */
.dash-section {
  background: var(--ua-card, #fff);
  border-bottom: 1px solid var(--ua-divider, #E0E0E0);
  margin-bottom: 1px;
}

.dash-section-header {
  padding: 12px 16px;
}

.dash-section-left {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}

.dash-section-name {
  font-weight: 700;
  font-size: 0.95rem;
  color: var(--ua-text, #212121);
}

.dash-icon        { font-size: 1.1rem; }
.dash-icon--water { color: var(--ua-water, #2196F3); }
.dash-icon--elec  { color: var(--ua-electricity, #FFA000); }

.dash-section-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 4px;
  background: var(--ua-bg, #F5F5F5);
  border-radius: var(--ua-radius-sm, 4px);
  padding: 8px;
  margin-bottom: 8px;
}

.dash-stat        { text-align: center; }
.dash-stat-label  { display: block; font-size: 0.62rem; color: var(--ua-text-secondary, #757575); margin-bottom: 2px; }
.dash-stat-value  { display: block; font-size: 0.85rem; font-weight: 700; color: var(--ua-text, #212121); }

.dash-section-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 2px 4px;
  align-items: center;
}

.dash-link {
  background: none;
  border: none;
  color: var(--ua-primary, #009BA4);
  font-size: 0.76rem;
  font-family: 'Nunito', sans-serif;
  cursor: pointer;
  text-decoration: none;
  padding: 0;
  font-weight: 600;
}

.dash-link:hover  { text-decoration: underline; }
.dash-link-sep    { color: var(--ua-text-secondary, #757575); font-size: 0.76rem; }

/* ── Expanded detail ── */
.dash-section-detail {
  padding: 10px 16px 16px;
  border-top: 1px solid var(--ua-divider, #E0E0E0);
}

.dash-proj-line {
  font-size: 0.82rem;
  color: var(--ua-text-secondary, #757575);
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  gap: 4px;
}

.dash-proj-amount {
  font-weight: 700;
  font-size: 1rem;
  color: var(--ua-text, #212121);
}

.dash-breakdown        { display: flex; flex-direction: column; gap: 5px; }

.dash-breakdown-row {
  display: flex;
  justify-content: space-between;
  font-size: 0.82rem;
  color: var(--ua-text, #212121);
}

/* expand transition */
.expand-enter-active, .expand-leave-active { transition: opacity 0.2s; }
.expand-enter-from, .expand-leave-to        { opacity: 0; }

/* ── Total footer ── */
.dash-total-line {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px;
  background: var(--ua-card, #fff);
  border-top: 2px solid var(--ua-primary, #009BA4);
  margin-top: 4px;
}

.dash-total-label  { font-size: 1.1rem; font-weight: 700; color: var(--ua-primary, #009BA4); }
.dash-total-amount { font-size: 1.8rem; font-weight: 700; color: var(--ua-amount, #1565C0); }

/* ── Empty ── */
.dash-empty {
  padding: 48px 24px;
  text-align: center;
  color: var(--ua-text-secondary, #757575);
}

.dash-empty i        { font-size: 2.5rem; margin-bottom: 16px; display: block; color: var(--ua-grey, #9E9E9E); }
.dash-empty p        { font-size: 0.95rem; margin-bottom: 6px; }
.dash-empty-sub      { font-size: 0.82rem; }
</style>
