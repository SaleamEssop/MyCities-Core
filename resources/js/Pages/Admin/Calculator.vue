<template>
  <AdminLayout>
    <div class="cp">

      <!-- ══ HEADER ══ -->
      <div class="cp-header">
        <div>
          <div class="cp-title">Billing Calculator</div>
          <div class="cp-sub">PD.md ↔ Calculator.php</div>
        </div>
        <div class="cp-tabs">
          <button :class="['cp-tab', mode === 'test' && 'cp-tab--on']" @click="setMode('test')">Test User</button>
          <button :class="['cp-tab', mode === 'account' && 'cp-tab--on']" @click="setMode('account')">User +Account</button>
        </div>
      </div>

      <!-- ══════════════════ TEST MODE ══════════════════ -->
      <template v-if="mode === 'test'">

        <!-- Setup -->
        <div class="card">
          <div class="section-label">Setup</div>
          <div class="fields-row">
            <div class="field">
              <label class="f-label">Bill day</label>
              <input type="number" v-model.number="test.billDay" min="1" max="31" class="f-input"
                @change="recomputeTestPeriod" />
            </div>
            <div class="field">
              <label class="f-label">Start Month</label>
              <input type="month" v-model="test.startMonth" class="f-input"
                @change="recomputeTestPeriod" />
            </div>
            <div class="field field--grow">
              <label class="f-label">Tariff Selector</label>
              <select v-model="test.templateId" class="f-input" @change="onTestTemplateChange">
                <option value="">— Select Tariff —</option>
                <option v-for="t in tariffTemplates" :key="t.id" :value="t.id">
                  {{ t.name }}{{ t.region_name ? ` (${t.region_name})` : '' }}
                </option>
              </select>
            </div>
          </div>
          <div v-if="test.periodStart" class="period-chip-row">
            <span class="chip-period">{{ fmt(test.periodStart) }} → {{ fmt(test.periodEnd) }}</span>
            <span class="chip-days">{{ test.periodDays }} block days</span>
          </div>
        </div>

        <!-- Start Reading -->
        <div class="card">
          <div class="section-label">Start Reading</div>
          <div class="start-reading-row">
            <MeterInput
              v-model="test.startReadingKl"
              :disabled="test.startConfirmed"
            />
            <div class="start-reading-actions">
              <template v-if="!test.startConfirmed">
                <div class="confirm-hint">Enter the meter initialization reading, then confirm.</div>
                <button class="btn-confirm" @click="confirmStartReading">Confirm</button>
              </template>
              <div v-else class="confirmed-tag">✓ Confirmed — {{ litresToKlStr(test.startReadingLitres) }} kL</div>
            </div>
          </div>
        </div>

        <!-- ── Period blocks ── -->
        <template v-if="test.startConfirmed">
          <div
            v-for="(period, pi) in test.periods"
            :key="pi"
            class="period-block"
            :class="period.expanded ? 'period-block--expanded' : 'period-block--collapsed'"
          >
            <!-- Clickable header (always visible) -->
            <div class="period-hdr" @click="period.expanded = !period.expanded">
              <div class="period-hdr-left">
                <div class="period-hdr-title">
                  Period {{ pi + 1 }}
                  <span class="period-hdr-dates">
                    {{ fmt(period.start) }} → {{ fmt(period.end) }}
                    <span class="chip-days">{{ period.blockDays }} days</span>
                  </span>
                </div>
                <!-- Collapsed summary -->
                <div v-show="!period.expanded" class="period-collapsed-summary">
                  <span class="cs-item">
                    <span class="cs-label">Opening:</span>
                    <span class="cs-val">{{ litresToKlStr(period.openingLitres) }} kL</span>
                  </span>
                  <span class="cs-sep">›</span>
                  <span class="cs-item" v-if="period.provisionalClosingLitres != null">
                    <span class="cs-label">{{ period.calculatedClosingLitres != null ? 'Calculated:' : 'Provisional:' }}</span>
                    <span class="cs-val" :class="period.calculatedClosingLitres != null ? 'val-calculated' : 'val-provisional'">
                      {{ litresToKlStr(period.calculatedClosingLitres ?? period.provisionalClosingLitres) }} kL
                    </span>
                  </span>
                  <span class="cs-sep" v-if="period.dailyUsage != null">·</span>
                  <span class="cs-item" v-if="period.dailyUsage != null">
                    <span class="cs-label">Daily:</span>
                    <span class="cs-val">{{ fmtN(period.dailyUsage, 0) }} L/day</span>
                  </span>
                  <span class="cs-sep" v-if="period.stats">·</span>
                  <span class="cs-item" v-if="period.stats">
                    <span class="cs-label">Projected:</span>
                    <span class="cs-val cs-val--bill">R {{ fmtMoney(period.stats.projectedR) }}</span>
                  </span>
                </div>
              </div>
              <div class="period-hdr-right">
                <span class="period-chevron">
                  <i :class="['fas', period.expanded ? 'fa-chevron-up' : 'fa-chevron-down']"></i>
                </span>
              </div>
            </div>

            <!-- ── Expanded content ── -->
            <template v-if="period.expanded">

              <!-- 1. Opening label row -->
              <div class="period-opening-row">
                <span class="por-label">{{ pi === 0 ? 'Start Reading' : 'Opening Reading' }}</span>
                <span class="por-val">{{ litresToKlStr(period.openingLitres) }} kL</span>
                <span
                  v-if="pi > 0 && prevPeriod(pi)?.provisionalClosingSnapshot != null"
                  class="por-was"
                >
                  Provisional — {{ litresToKlStr(prevPeriod(pi).provisionalClosingSnapshot) }} updated
                </span>
              </div>

              <!-- 2. Stats bar (TOP) -->
              <div class="stats-bar">
                <div class="stat-cell">
                  <div class="stat-label">Daily Usage</div>
                  <div class="stat-val">{{ period.dailyUsage != null ? fmtN(period.dailyUsage, 0) + ' L' : '_ _' }}</div>
                </div>
                <div class="stat-cell">
                  <div class="stat-label">Current Usage</div>
                  <div class="stat-val">{{ period.stats ? 'R ' + fmtMoney(period.stats.currentR) : '_ _' }}</div>
                </div>
                <div class="stat-cell">
                  <div class="stat-label">Projected Usage</div>
                  <div class="stat-val">{{ period.stats ? 'R ' + fmtMoney(period.stats.projectedR) : '_ _' }}</div>
                </div>
              </div>

              <!-- Adjustment notice for periods 2+ -->
              <div
                v-if="period.adjustmentBroughtForward"
                class="adjustment-notice"
                :class="period.adjustmentBroughtForward > 0 ? 'adj-shortfall' : 'adj-surplus'"
              >
                <i class="fas fa-exchange-alt"></i>
                Adjustment from Period {{ pi }} carried forward:
                <strong>{{ period.adjustmentBroughtForward > 0 ? '+' : '' }}R {{ fmtMoney(Math.abs(period.adjustmentBroughtForward)) }}</strong>
                — included in projected total
              </div>

              <!-- 3. Readings (MIDDLE) -->
              <div class="readings-section">
                <div class="readings-header">
                  <span class="readings-header-label">Readings</span>
                  <span class="readings-header-hint">enter in kL · format 0000.00</span>
                </div>
                <div
                  v-for="(r, ri) in period.readings"
                  :key="ri"
                  class="reading-row"
                  :class="r.error && 'reading-row--error'"
                >
                  <div class="date-wrap">
                    <i class="fas fa-calendar-alt date-icon"></i>
                    <input
                      type="date"
                      v-model="r.date"
                      class="f-input r-date"
                      :min="period.start"
                      :max="period.end"
                      @change="recomputePeriod(period, pi)"
                    />
                  </div>
                  <MeterInput
                    v-model="r.klStr"
                    @change="onReadingInput(period, r, pi)"
                  />
                  <div class="r-litres" v-if="r.litres && !r.error">{{ fmtN(r.litres) }} L</div>
                  <div class="r-seq-error" v-if="r.error">
                    <i class="fas fa-exclamation-triangle"></i> {{ r.error }}
                  </div>
                  <button class="btn-rm" @click="period.readings.splice(ri, 1); recomputePeriod(period, pi)">
                    <i class="fas fa-times"></i>
                  </button>
                </div>
                <div v-if="period.readings.length === 0" class="empty-readings">
                  No readings yet — click "+ Add Reading"
                </div>
              </div>

              <!-- 4. Sectors -->
              <div v-if="period.sectors.length > 0" class="sectors-section">
                <div class="sectors-label">Sectors</div>
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>From</th><th>To</th>
                      <th class="num">Block Days</th>
                      <th class="num">Usage (L)</th>
                      <th class="num">Daily Avg</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(s, si) in period.sectors" :key="si">
                      <td>{{ fmt(s.start) }}</td>
                      <td>{{ fmt(s.end) }}</td>
                      <td class="num">{{ s.block_days }}</td>
                      <td class="num">{{ fmtN(s.total_usage) }}</td>
                      <td class="num">{{ fmtN(s.daily_avg, 1) }} L/day</td>
                    </tr>
                    <tr class="total-row">
                      <td colspan="2">Total</td>
                      <td class="num">{{ period.sectors.reduce((a,s)=>a+s.block_days,0) }}</td>
                      <td class="num">{{ fmtN(period.sectors.reduce((a,s)=>a+s.total_usage,0)) }}</td>
                      <td></td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- 5. Actions -->
              <div class="period-actions">
                <button class="btn-add-reading" @click="addReadingToPeriod(period)">
                  <i class="fas fa-plus"></i> Add Reading
                </button>
                <button class="btn-calc" @click="calculatePeriod(pi)"
                  :disabled="!canCalculatePeriod(period) || period.calculating">
                  {{ period.calculating ? 'Calculating…' : 'Calculate' }}
                </button>
              </div>
              <div v-if="period.calcError" class="msg-error">{{ period.calcError }}</div>

              <!-- 6. Closing bar (BOTTOM) -->
              <div class="closing-bar" :class="period.calculatedClosingLitres != null && 'closing-bar--resolved'">
                <div class="closing-cell">
                  <div class="closing-cell-label">Closing provisional</div>
                  <div class="closing-cell-val val-provisional">
                    {{ period.provisionalClosingLitres != null ? litresToKlStr(period.provisionalClosingLitres) : '_ _' }}
                  </div>
                  <div class="closing-cell-sub" v-if="period.provisionalBillR != null">
                    R {{ fmtMoney(period.provisionalBillR) }}
                  </div>
                  <div class="closing-cell-sub" v-else-if="period.provisionalClosingLitres != null">kL</div>
                </div>
                <div class="closing-cell">
                  <div class="closing-cell-label">Closing calculated</div>
                  <div class="closing-cell-val" :class="period.calculatedClosingLitres != null ? 'val-calculated' : 'val-empty'">
                    {{ period.calculatedClosingLitres != null ? litresToKlStr(period.calculatedClosingLitres) : '_ _' }}
                  </div>
                  <div class="closing-cell-sub" v-if="period.calculatedBillR != null">
                    R {{ fmtMoney(period.calculatedBillR) }}
                  </div>
                  <div class="closing-cell-sub" v-else-if="period.calculatedClosingLitres != null">kL</div>
                </div>
                <div class="closing-cell">
                  <div class="closing-cell-label">Adjustment</div>
                  <div class="closing-cell-val" :class="adjustmentClass(period)">
                    {{ formatAdjustment(period) }}
                  </div>
                  <div class="closing-cell-sub" v-if="period.calculatedClosingLitres != null">
                    {{ (period.calculatedClosingLitres - period.provisionalClosingLitres) >= 0 ? 'shortfall' : 'surplus' }}
                  </div>
                </div>
              </div>

              <!-- Billing section — collapses with the period -->
              <div v-if="period.bill" class="period-billing">
                <div class="period-billing-header">
                  <i class="fas fa-file-invoice-dollar"></i>
                  Bill · Period {{ pi + 1 }}: {{ fmt(period.start) }} → {{ fmt(period.end) }}
                </div>
                <div class="bill-grid">
                  <div class="bill-stat">
                    <div class="bill-stat-label">Total Usage</div>
                    <div class="bill-stat-val">{{ fmtN(period.bill.consumption_litres) }} L</div>
                  </div>
                  <div class="bill-stat">
                    <div class="bill-stat-label">Usage Charge</div>
                    <div class="bill-stat-val">R {{ fmtMoney(period.bill.usage_charge) }}</div>
                  </div>
                  <div class="bill-stat">
                    <div class="bill-stat-label">Fixed Charges</div>
                    <div class="bill-stat-val">R {{ fmtMoney(period.bill.fixed_total) }}</div>
                  </div>
                  <div class="bill-stat">
                    <div class="bill-stat-label">VAT</div>
                    <div class="bill-stat-val">R {{ fmtMoney(period.bill.vat_amount) }}</div>
                  </div>
                  <div v-if="period.bill.adjustment_brought_forward" class="bill-stat">
                    <div class="bill-stat-label">Adjustment b/f</div>
                    <div class="bill-stat-val"
                      :class="period.bill.adjustment_brought_forward > 0 ? 'val-shortfall' : 'val-surplus'">
                      {{ period.bill.adjustment_brought_forward > 0 ? '+' : '' }}R {{ fmtMoney(Math.abs(period.bill.adjustment_brought_forward)) }}
                    </div>
                  </div>
                  <div class="bill-stat bill-stat--total">
                    <div class="bill-stat-label">BILL TOTAL</div>
                    <div class="bill-stat-val">R {{ fmtMoney(period.bill.bill_total) }}</div>
                  </div>
                </div>
                <div v-if="period.bill.tier_breakdown?.length" class="tier-section">
                  <div class="tier-label">Tier Breakdown</div>
                  <table class="data-table">
                    <thead>
                      <tr>
                        <th>Tier</th>
                        <th class="num">Units (kL)</th>
                        <th class="num">Rate (R/kL)</th>
                        <th class="num">Charge</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(t, i) in period.bill.tier_breakdown" :key="i">
                        <td>Tier {{ i + 1 }}</td>
                        <td class="num">{{ fmtN(t.units_kl ?? t.units / 1000, 3) }}</td>
                        <td class="num">{{ t.rate }}</td>
                        <td class="num">R {{ fmtMoney(t.amount) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

            </template><!-- end expanded -->
          </div><!-- end period-block -->

          <!-- Add Period — below all period blocks (and their bills) -->
          <button class="btn-add-period-bottom" @click="addPeriod">
            <i class="fas fa-plus"></i> Add Period
          </button>

        </template><!-- end startConfirmed -->
      </template>

      <!-- ══════════════════ USER + ACCOUNT MODE ══════════════════ -->
      <template v-else>

        <!-- Select account + meter -->
        <div class="card">
          <div class="section-label">Select Account</div>
          <div class="fields-row">
            <div class="field field--grow">
              <label class="f-label">User</label>
              <select v-model="ua.userId" class="f-input" @change="onUserChange">
                <option value="">— Select User —</option>
                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
              </select>
            </div>
            <div class="field field--grow">
              <label class="f-label">Account</label>
              <select v-model="ua.accountId" class="f-input" :disabled="!ua.userId" @change="onAccountChange">
                <option value="">— Select Account —</option>
                <option v-for="a in filteredAccounts" :key="a.id" :value="a.id">
                  {{ a.account_name }} ({{ a.account_number }})
                </option>
              </select>
            </div>
            <div class="field field--grow">
              <label class="f-label">Meter</label>
              <select v-model="ua.meterId" class="f-input" :disabled="!ua.accountId" @change="loadMeter">
                <option value="">— Select Meter —</option>
                <option v-for="m in filteredMeters" :key="m.id" :value="m.id">
                  {{ m.meter_title || m.meter_number }}
                </option>
              </select>
            </div>
          </div>
        </div>

        <template v-if="ua.meterData">

          <!-- ── Current period ── -->
          <div
            class="period-block period-block--open"
            :class="ua.currentExpanded ? 'period-block--expanded' : 'period-block--collapsed'"
          >
            <div class="period-hdr" @click="ua.currentExpanded = !ua.currentExpanded">
              <div class="period-hdr-left">
                <div class="period-hdr-title">
                  Current Period
                  <span class="chip-open">OPEN</span>
                  <span class="period-hdr-dates">
                    {{ fmt(ua.meterData.current_period.start) }} → {{ fmt(ua.meterData.current_period.end) }}
                    <span class="chip-days">{{ ua.meterData.current_period.block_days }} days</span>
                  </span>
                </div>
                <div v-show="!ua.currentExpanded" class="period-collapsed-summary">
                  <span class="cs-item" v-if="ua.meterData.opening_provisional">
                    <span class="cs-label">Opening:</span>
                    <span class="cs-val">{{ litresToKlStr(ua.meterData.opening_provisional.value) }} kL</span>
                  </span>
                  <span class="cs-item" v-if="ua.meterData.closing_provisional != null">
                    <span class="cs-sep">›</span>
                    <span class="cs-label">Provisional:</span>
                    <span class="cs-val val-provisional">{{ litresToKlStr(ua.meterData.closing_provisional) }} kL</span>
                  </span>
                </div>
              </div>
              <div class="period-hdr-right">
                <span class="period-chevron">
                  <i :class="['fas', ua.currentExpanded ? 'fa-chevron-up' : 'fa-chevron-down']"></i>
                </span>
              </div>
            </div>

            <template v-if="ua.currentExpanded">
              <!-- Anchor grid -->
              <div class="anchor-grid">
                <div class="anchor-cell anchor-cell--open">
                  <div class="anchor-cell-label">Opening Provisional</div>
                  <div class="anchor-cell-val">
                    {{ ua.meterData.opening_provisional ? litresToKlStr(ua.meterData.opening_provisional.value) : '_ _' }}
                  </div>
                  <div class="anchor-cell-sub">{{ ua.meterData.opening_provisional?.source }}</div>
                </div>
                <div class="anchor-cell" :class="!ua.meterData.opening_actual && 'anchor-cell--pending'">
                  <div class="anchor-cell-label">Opening Actual</div>
                  <div class="anchor-cell-val" :class="ua.meterData.opening_actual ? 'val-calculated' : 'val-empty'">
                    {{ ua.meterData.opening_actual ? litresToKlStr(ua.meterData.opening_actual.value) : '_ _' }}
                  </div>
                  <div class="anchor-cell-sub">{{ ua.meterData.opening_actual ? ua.meterData.opening_actual.source : 'populated by straddle' }}</div>
                </div>
                <div class="anchor-cell">
                  <div class="anchor-cell-label">Closing Provisional</div>
                  <div class="anchor-cell-val" :class="ua.meterData.closing_provisional != null ? 'val-provisional' : 'val-empty'">
                    {{ ua.meterData.closing_provisional != null ? litresToKlStr(ua.meterData.closing_provisional) : '_ _' }}
                  </div>
                  <div class="anchor-cell-sub">live estimate</div>
                </div>
                <div class="anchor-cell" :class="!ua.meterData.closing_actual && 'anchor-cell--pending'">
                  <div class="anchor-cell-label">Closing Actual</div>
                  <div class="anchor-cell-val" :class="ua.meterData.closing_actual != null ? 'val-calculated' : 'val-empty'">
                    {{ ua.meterData.closing_actual != null ? litresToKlStr(ua.meterData.closing_actual) : '_ _' }}
                  </div>
                  <div class="anchor-cell-sub">{{ ua.meterData.closing_actual != null ? 'reading on period end' : 'populated by next period straddle' }}</div>
                </div>
              </div>

              <!-- Readings list -->
              <div class="readings-section">
                <div class="readings-header">
                  <span class="readings-header-label">Readings</span>
                  <span class="readings-header-hint">enter in kL · format 0000.00</span>
                </div>
                <div v-for="(r, i) in ua.meterData.period_readings" :key="r.id" class="reading-row">
                  <span class="r-date-display">{{ fmt(r.date) }}</span>
                  <span class="r-kl-display">{{ litresToKlStr(r.value) }} kL</span>
                  <span class="r-litres-display">{{ fmtN(r.value) }} L</span>
                  <span class="r-sector-avg" v-if="ua.meterData.sectors[i]">
                    {{ fmtN(ua.meterData.sectors[i].daily_avg, 1) }} L/day
                  </span>
                  <button class="btn-rm" @click="deleteReading(r.id)">
                    <i class="fas fa-times"></i>
                  </button>
                </div>
                <div v-if="ua.meterData.period_readings.length === 0" class="empty-readings">No readings yet.</div>

                <!-- Add reading form -->
                <div class="add-reading-form">
                  <div class="date-wrap">
                    <i class="fas fa-calendar-alt date-icon"></i>
                    <input
                      type="date"
                      v-model="ua.newReadingDate"
                      class="f-input r-date"
                      :min="ua.meterData.current_period.start"
                      :max="ua.meterData.current_period.end"
                    />
                  </div>
                  <MeterInput
                    v-model="ua.newReadingKl"
                    @change="ua.newReadingLitres = klStrToLitres(ua.newReadingKl)"
                  />
                  <div class="r-litres" v-if="ua.newReadingLitres">{{ fmtN(ua.newReadingLitres) }} L</div>
                  <button class="btn-save-reading" @click="addReading"
                    :disabled="!ua.newReadingDate">
                    <i class="fas fa-save"></i> Save
                  </button>
                </div>
                <div v-if="ua.readingError" class="msg-error">{{ ua.readingError }}</div>
              </div>

              <!-- Sectors -->
              <div v-if="ua.meterData.sectors.length > 0" class="sectors-section">
                <div class="sectors-label">Sectors</div>
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>From</th><th>To</th>
                      <th class="num">Block Days</th>
                      <th class="num">Usage (L)</th>
                      <th class="num">Daily Avg</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(s, i) in ua.meterData.sectors" :key="i">
                      <td>{{ fmt(s.start) }}</td>
                      <td>{{ fmt(s.end) }}</td>
                      <td class="num">{{ s.block_days }}</td>
                      <td class="num">{{ fmtN(s.total_usage) }}</td>
                      <td class="num">{{ fmtN(s.daily_avg, 1) }} L/day</td>
                    </tr>
                    <tr class="total-row">
                      <td colspan="2">Total</td>
                      <td class="num">{{ ua.meterData.sectors.reduce((a,s)=>a+s.block_days,0) }}</td>
                      <td class="num">{{ fmtN(ua.meterData.sectors.reduce((a,s)=>a+s.total_usage,0)) }}</td>
                      <td></td>
                    </tr>
                  </tbody>
                </table>
                <!-- Bar graph -->
                <div class="usage-graph" v-if="ua.meterData.sectors.length > 1">
                  <div class="graph-title">Daily Usage by Sector (L/day)</div>
                  <div class="graph-bars">
                    <div v-for="(s, i) in ua.meterData.sectors" :key="i" class="graph-bar-col">
                      <div class="graph-bar-num">{{ fmtN(s.daily_avg, 0) }}</div>
                      <div class="graph-bar" :style="{ height: graphBarH(s.daily_avg) + 'px' }"></div>
                      <div class="graph-bar-date">{{ fmtShort(s.end) }}</div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Stats bar -->
              <div class="stats-bar">
                <div class="stat-cell">
                  <div class="stat-label">Daily Usage</div>
                  <div class="stat-val">
                    {{ ua.meterData.sectors.length > 0
                      ? fmtN(ua.meterData.sectors[ua.meterData.sectors.length-1].daily_avg, 0) + ' L'
                      : '_ _' }}
                  </div>
                </div>
                <div class="stat-cell">
                  <div class="stat-label">Closing Provisional</div>
                  <div class="stat-val">
                    {{ ua.meterData.closing_provisional != null ? litresToKlStr(ua.meterData.closing_provisional) + ' kL' : '_ _' }}
                  </div>
                </div>
                <div class="stat-cell">
                  <div class="stat-label">Closing Actual</div>
                  <div class="stat-val">
                    {{ ua.meterData.closing_actual != null ? litresToKlStr(ua.meterData.closing_actual) + ' kL' : '_ _' }}
                  </div>
                </div>
              </div>
            </template>
          </div><!-- end current period -->

          <!-- ── Previous periods ── -->
          <div
            v-for="(p, pi) in ua.meterData.previous_periods"
            :key="p.id"
            class="period-block period-block--closed"
            :class="p.expanded ? 'period-block--expanded' : 'period-block--collapsed'"
          >
            <div class="period-hdr" @click="p.expanded = !p.expanded">
              <div class="period-hdr-left">
                <div class="period-hdr-title">
                  Previous Period
                  <span :class="['status-badge', 'status-' + (p.status || 'provisional').toLowerCase()]">
                    {{ p.status || 'PROVISIONAL' }}
                  </span>
                  <span class="period-hdr-dates">{{ fmt(p.period_start_date) }} → {{ fmt(p.period_end_date) }}</span>
                </div>
                <div v-show="!p.expanded" class="period-collapsed-summary">
                  <span class="cs-item">
                    <span class="cs-label">Consumption:</span>
                    <span class="cs-val">{{ litresToKlStr(p.consumption) }} kL</span>
                  </span>
                  <span class="cs-sep">·</span>
                  <span class="cs-item">
                    <span class="cs-label">Bill:</span>
                    <span class="cs-val cs-val--bill">R {{ fmtMoney(p.total_amount) }}</span>
                  </span>
                </div>
              </div>
              <div class="period-hdr-right">
                <span class="period-chevron">
                  <i :class="['fas', p.expanded ? 'fa-chevron-up' : 'fa-chevron-down']"></i>
                </span>
              </div>
            </div>

            <template v-if="p.expanded">
              <div class="anchor-grid">
                <div class="anchor-cell">
                  <div class="anchor-cell-label">Consumption</div>
                  <div class="anchor-cell-val">{{ litresToKlStr(p.consumption) }} kL</div>
                  <div class="anchor-cell-sub">{{ fmtN(p.consumption) }} L</div>
                </div>
                <div class="anchor-cell">
                  <div class="anchor-cell-label">Daily Avg</div>
                  <div class="anchor-cell-val">{{ p.daily_usage != null ? fmtN(p.daily_usage, 1) + ' L/day' : '_ _' }}</div>
                </div>
                <div class="anchor-cell">
                  <div class="anchor-cell-label">Closing Provisional</div>
                  <div class="anchor-cell-val" :class="p.closing_provisional ? 'val-provisional' : 'val-empty'">
                    {{ p.closing_provisional != null ? litresToKlStr(p.closing_provisional) : '_ _' }}
                  </div>
                </div>
                <div class="anchor-cell">
                  <div class="anchor-cell-label">Closing Actual</div>
                  <div class="anchor-cell-val" :class="p.closing_actual ? 'val-calculated' : 'val-empty'">
                    {{ p.closing_actual != null ? litresToKlStr(p.closing_actual) : '_ _' }}
                  </div>
                </div>
              </div>
              <div v-if="p.closing_provisional != null && p.closing_actual != null"
                class="recon-row"
                :class="p.closing_actual > p.closing_provisional ? 'recon--short' : 'recon--surplus'">
                Reconciliation: {{ p.closing_actual > p.closing_provisional ? 'Shortfall' : 'Surplus' }}
                {{ fmtN(Math.abs(p.closing_actual - p.closing_provisional)) }} L carried to next period.
              </div>
              <div class="stats-bar stats-bar--sm">
                <div class="stat-cell">
                  <div class="stat-label">Bill Total</div>
                  <div class="stat-val">R {{ fmtMoney(p.total_amount) }}</div>
                </div>
              </div>
            </template>
          </div>

        </template>
      </template>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import MeterInput from '@/components/MeterInput.vue'

const props = defineProps({
  users:           { type: Array,  default: () => [] },
  tariffTemplates: { type: Array,  default: () => [] },
  today:           { type: String, default: '' },
})

// ── Mode ──────────────────────────────────────────────────────────────────────
const mode = ref('test')
function setMode (m) { mode.value = m }

// ══════════════════════════════════════════════════════════
// kL ↔ Litres conversions
// Format: XXXX.XX  (kL · 4 whole digits, 2 fractional)
// 0001.50 = 1.5 kL = 1500 L
// ══════════════════════════════════════════════════════════
function klStrToLitres (klStr) {
  const v = parseFloat(klStr)
  return isNaN(v) ? 0 : Math.round(v * 1000)
}
function litresToKlStr (litres) {
  if (litres === null || litres === undefined) return '_ _'
  const kl   = litres / 1000
  const whole = Math.floor(kl).toString().padStart(4, '0')
  const frac  = Math.round((kl % 1) * 100).toString().padStart(2, '0')
  return `${whole}.${frac}`
}

// ══════════════════════════════════════════════════════════
// TEST MODE
// ══════════════════════════════════════════════════════════
const test = ref({
  billDay:           1,
  startMonth:        props.today ? props.today.slice(0, 7) : new Date().toISOString().slice(0, 7),
  templateId:        '',
  startReadingKl:    '0000.00',
  startReadingLitres: 0,
  startConfirmed:    false,
  periodStart:       '',
  periodEnd:         '',
  periodDays:        0,
  periods:           [],
})
const latestBill = ref(null)

function localDateStr (d) {
  return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`
}
function recomputeTestPeriod () {
  const { billDay, startMonth } = test.value
  if (!startMonth || !billDay) return
  test.value.periodStart = `${startMonth}-${String(billDay).padStart(2, '0')}`
  const [y, m] = startMonth.split('-').map(Number)
  let ny = y, nm = m + 1
  if (nm > 12) { nm = 1; ny++ }
  const lastDayNext = new Date(ny, nm, 0).getDate()
  const effDay = Math.min(billDay, lastDayNext)
  const d = new Date(ny, nm - 1, effDay)
  d.setDate(d.getDate() - 1)
  test.value.periodEnd  = localDateStr(d)
  test.value.periodDays = blockDays(test.value.periodStart, test.value.periodEnd)
}
function onTestTemplateChange () {
  const t = props.tariffTemplates.find(t => String(t.id) === String(test.value.templateId))
  if (t?.billing_day) { test.value.billDay = t.billing_day; recomputeTestPeriod() }
}

function confirmStartReading () {
  test.value.startReadingLitres = klStrToLitres(test.value.startReadingKl)
  test.value.startConfirmed     = true
  if (test.value.periods.length === 0 && test.value.periodStart) {
    test.value.periods.push(makePeriod(0, test.value.startReadingLitres, test.value.periodStart))
  }
}

function makePeriod (index, openingLitres, openingDate) {
  return {
    index,
    start:                       test.value.periodStart,
    end:                         test.value.periodEnd,
    blockDays:                   test.value.periodDays,
    openingLitres,
    openingDate,
    readings:                    [],
    sectors:                     [],
    provisionalClosingLitres:    null,
    calculatedClosingLitres:     null,
    provisionalClosingSnapshot:  null,  // litres at time straddle resolved (prev provisional)
    provisionalBillR:            null,  // Period N-1 bill at provisional consumption
    calculatedBillR:             null,  // Period N-1 bill at actual consumption
    adjustmentBroughtForward:    0,     // R difference carried into this period's bill
    inheritedDailyUsage:         null,  // daily usage from previous period (momentum)
    dailyUsage:                  null,
    calculating:                 false,
    calcError:                   '',
    stats:                       null,
    bill:                        null,  // result of calculatePeriod()
    expanded:                    true,
  }
}

function prevPeriod (pi) {
  return pi > 0 ? test.value.periods[pi - 1] : null
}

function addPeriod () {
  if (test.value.periods.length > 0) {
    test.value.periods[test.value.periods.length - 1].expanded = false
  }
  const last     = test.value.periods[test.value.periods.length - 1]
  const newStart = nextDay(last.end)
  const [sy, sm] = newStart.split('-').map(Number)
  const tmpMonth = test.value.startMonth
  test.value.startMonth = `${sy}-${String(sm).padStart(2,'0')}`
  recomputeTestPeriod()
  const openingLitres  = last.calculatedClosingLitres ?? last.provisionalClosingLitres ?? last.openingLitres
  const newPeriod      = makePeriod(test.value.periods.length, openingLitres, newStart)
  newPeriod.inheritedDailyUsage = last.dailyUsage   // carry momentum forward
  test.value.periods.push(newPeriod)
  test.value.startMonth = tmpMonth
  // Apply inherited momentum immediately so the new period shows a projection
  recomputePeriod(newPeriod, test.value.periods.length - 1)
}

function addReadingToPeriod (period) {
  const lastDate = period.readings.length > 0
    ? period.readings[period.readings.length - 1].date
    : period.openingDate
  period.readings.push({ date: lastDate, klStr: '', litres: 0, error: '' })
}

function onReadingInput (period, r, pi) {
  r.litres = klStrToLitres(r.klStr || '0000.00')
  recomputePeriod(period, pi)
}

function recomputePeriod (period, pi) {
  // If this is period N > 0, trigger straddle reconciliation with period N-1
  if (pi !== undefined && pi > 0) {
    reconcileStraddle(pi)  // fire-and-forget async
  }

  // Clear previous sequence errors
  period.readings.forEach(r => { r.error = '' })

  // Sort valid readings by date, then validate that values never decrease
  const valid = period.readings
    .filter(r => r.date && r.klStr && r.litres > 0)
    .sort((a, b) => a.date.localeCompare(b.date))

  if (valid.length === 0) {
    period.sectors = []
    period.provisionalClosingLitres = null
    period.dailyUsage               = null
    if (period.inheritedDailyUsage != null && period.inheritedDailyUsage > 0) {
      period.dailyUsage               = period.inheritedDailyUsage
      period.provisionalClosingLitres = Math.round(period.openingLitres + period.inheritedDailyUsage * period.blockDays)
    }
    return
  }

  // Sequence check: each reading must be ≥ the previous reading (or the opening)
  let prevLitres = period.openingLitres
  for (const r of valid) {
    if (r.litres < prevLitres) {
      r.error = `Must be ≥ ${litresToKlStr(prevLitres)} kL`
    } else {
      prevLitres = r.litres
    }
  }
  const sequential = valid.filter(r => !r.error)

  if (sequential.length === 0) {
    period.sectors = []
    period.provisionalClosingLitres = null
    period.dailyUsage               = null
    if (period.inheritedDailyUsage != null && period.inheritedDailyUsage > 0) {
      period.dailyUsage               = period.inheritedDailyUsage
      period.provisionalClosingLitres = Math.round(period.openingLitres + period.inheritedDailyUsage * period.blockDays)
    }
    return
  }

  const sectorInput = [
    { reading_date: period.openingDate, reading_value: period.openingLitres },
    ...sequential.map(r => ({ reading_date: r.date, reading_value: r.litres })),
  ]
  period.sectors   = buildSectors(sectorInput)
  const last       = sequential[sequential.length - 1]
  const usageSoFar = last.litres - period.openingLitres
  const days       = blockDays(period.openingDate, last.date)
  if (days > 0 && usageSoFar >= 0) {
    const rate = usageSoFar / days
    period.dailyUsage               = Math.round(rate)
    period.provisionalClosingLitres = Math.round(period.openingLitres + rate * period.blockDays)
    if (last.date === period.end) {
      period.calculatedClosingLitres  = last.litres
      period.provisionalClosingLitres = last.litres
    }
  }
  propagateMomentum(pi)
}

// Push daily-usage momentum forward into any consecutive empty periods
function propagateMomentum (fromPi) {
  if (fromPi === undefined) return
  for (let k = fromPi + 1; k < test.value.periods.length; k++) {
    const p = test.value.periods[k]
    if (p.readings.filter(r => r.litres > 0).length > 0) break  // stop at period with real readings
    p.inheritedDailyUsage   = test.value.periods[k - 1].dailyUsage
    if (p.inheritedDailyUsage != null && p.inheritedDailyUsage > 0) {
      p.dailyUsage               = p.inheritedDailyUsage
      p.provisionalClosingLitres = Math.round(p.openingLitres + p.inheritedDailyUsage * p.blockDays)
    }
  }
}

// ── Straddle reconciliation (cascade) ─────────────────────────────────────
// When period pi gets its first reading, we walk backward to find the last
// period that had actual readings (the "left anchor"). All silent periods
// in between are resolved proportionally in one pass.
//
// Rule: if a period had a provisional (it was projected), show provisional
// vs actual and carry the R difference forward. If a period was completely
// silent (no provisional existed), go straight to "calculated" — no ghost
// provisional is shown, no R adjustment needed for that period.
async function reconcileStraddle (pi) {
  if (pi === 0) return
  const currP = test.value.periods[pi]

  const currReadings = currP.readings
    .filter(r => r.date && r.litres > 0)
    .sort((a, b) => a.date.localeCompare(b.date))
  if (!currReadings.length) return

  const rightAnchor = currReadings[0]  // earliest reading in current period

  // ── Walk backward to find the nearest previous period with readings ──
  // Periods between leftPeriodIdx+1 and pi-1 are silent (guaranteed below).
  let leftPeriodIdx = -1
  let leftAnchor    = null
  for (let k = pi - 1; k >= 0; k--) {
    const p = test.value.periods[k]
    const pReadings = p.readings
      .filter(r => r.date && r.litres > 0)
      .sort((a, b) => a.date.localeCompare(b.date))
    if (pReadings.length > 0) {
      leftPeriodIdx = k
      leftAnchor    = pReadings[pReadings.length - 1]  // last reading in that period
      break
    }
    if (k === 0) {
      // Period 0 has no readings — fall back to its opening anchor (start reading)
      leftPeriodIdx = 0
      leftAnchor    = { date: p.openingDate, litres: p.openingLitres }
    }
  }
  if (leftAnchor === null) return

  const totalUsage = rightAnchor.litres - leftAnchor.litres
  const spanFrom   = nextDay(leftAnchor.date)
  const totalDays  = blockDays(spanFrom, rightAnchor.date)
  if (totalDays <= 0 || totalUsage < 0) return

  // ── Build one slice per period from leftPeriodIdx to pi-1 ──
  // Each slice covers the portion of the span that falls inside that period.
  const slices = []
  for (let k = leftPeriodIdx; k <= pi - 1; k++) {
    const p          = test.value.periods[k]
    const sliceStart = (k === leftPeriodIdx) ? spanFrom : p.start
    const sliceDays  = blockDays(sliceStart, p.end)
    slices.push({ k, p, sliceDays })
  }

  // ── Proportional usage → update calculated closings for each slice ──
  let prevClosing = leftAnchor.litres
  for (const { k, p, sliceDays } of slices) {
    // Update opening of intermediate silent periods
    if (k > leftPeriodIdx) {
      p.openingLitres = prevClosing
      p.openingDate   = p.start
    }

    // Only record a provisional snapshot when this period actually had a projection.
    // Silent periods (provisionalClosingLitres === null) go straight to calculated.
    p.provisionalClosingSnapshot = p.provisionalClosingLitres  // null if was silent

    p.calculatedClosingLitres = prevClosing + Math.floor(totalUsage * sliceDays / totalDays)
    prevClosing = p.calculatedClosingLitres
  }

  // ── Update current period's actual opening ──
  currP.openingLitres = prevClosing
  currP.openingDate   = currP.start

  // ── R adjustment (async): only for periods that had a provisional ──
  // Silent periods never had a bill issued, so no adjustment is owed.
  if (!test.value.templateId) return
  const tid = parseInt(test.value.templateId)

  const provisionalSlices = slices.filter(s => s.p.provisionalClosingSnapshot != null)
  if (provisionalSlices.length === 0) {
    currP.adjustmentBroughtForward = 0
    return
  }

  const diffs = await Promise.all(provisionalSlices.map(async ({ p }) => {
    const provConsumption = Math.max(0, p.provisionalClosingSnapshot - p.openingLitres)
    const actConsumption  = Math.max(0, p.calculatedClosingLitres    - p.openingLitres)
    const [provRes, actRes] = await Promise.all([
      apiPost('/admin/calculator/compute-charge', { tariff_template_id: tid, consumption_litres: provConsumption }),
      apiPost('/admin/calculator/compute-charge', { tariff_template_id: tid, consumption_litres: actConsumption }),
    ])
    if (provRes.success && actRes.success) {
      p.provisionalBillR = provRes.data.bill_total
      p.calculatedBillR  = actRes.data.bill_total
      return actRes.data.bill_total - provRes.data.bill_total
    }
    return 0
  }))

  currP.adjustmentBroughtForward = diffs.reduce((sum, d) => sum + d, 0)
}

function canCalculatePeriod (period) {
  // Allow calculating if there are readings OR if inherited momentum produced a provisional
  return test.value.templateId &&
    (period.readings.some(r => r.date && r.litres > 0) || period.provisionalClosingLitres != null)
}
async function calculatePeriod (pi) {
  const period = test.value.periods[pi]
  period.calculating = true; period.calcError = ''
  try {
    const valid       = period.readings.filter(r => r.date && r.litres > 0)
    // If no readings, use projected provisional as consumption (momentum-only periods)
    const consumption = valid.length > 0
      ? Math.max(0, valid[valid.length - 1].litres - period.openingLitres)
      : Math.max(0, (period.provisionalClosingLitres ?? 0) - period.openingLitres)
    const projected   = Math.max(0, (period.provisionalClosingLitres ?? (valid.length > 0 ? valid[valid.length - 1].litres : 0)) - period.openingLitres)
    const [curRes, proRes] = await Promise.all([
      apiPost('/admin/calculator/compute-charge', { tariff_template_id: parseInt(test.value.templateId), consumption_litres: consumption }),
      apiPost('/admin/calculator/compute-charge', { tariff_template_id: parseInt(test.value.templateId), consumption_litres: projected }),
    ])
    if (curRes.success && proRes.success) {
      const adjustmentR = period.adjustmentBroughtForward ?? 0
      period.stats = {
        currentR:   curRes.data.bill_total,
        projectedR: proRes.data.bill_total + adjustmentR,
        adjustmentR,
      }
      period.bill = {
        ...proRes.data,
        adjustment_brought_forward: adjustmentR || null,
        bill_total: proRes.data.bill_total + adjustmentR,
      }
    } else {
      period.calcError = curRes.message || proRes.message || 'Calculation failed'
    }
  } catch (e) { period.calcError = e.message }
  finally     { period.calculating = false }
}

function formatAdjustment (period) {
  if (period.calculatedClosingLitres == null || period.provisionalClosingLitres == null) return '_ _'
  const diff = period.calculatedClosingLitres - period.provisionalClosingLitres
  return (diff >= 0 ? '+' : '') + fmtN(diff) + ' L'
}
function adjustmentClass (period) {
  if (period.calculatedClosingLitres == null || period.provisionalClosingLitres == null) return 'val-empty'
  const diff = period.calculatedClosingLitres - period.provisionalClosingLitres
  return diff > 0 ? 'val-shortfall' : diff < 0 ? 'val-surplus' : ''
}

// Client-side sector builder
function buildSectors (readings) {
  const sorted = [...readings].sort((a, b) => a.reading_date.localeCompare(b.reading_date))
  const sectors = []
  for (let i = 0; i < sorted.length - 1; i++) {
    const r1     = sorted[i]
    const r2     = sorted[i + 1]
    const sStart = i === 0 ? r1.reading_date : nextDay(r1.reading_date)
    const bd     = blockDays(sStart, r2.reading_date)
    const usage  = Math.max(0, Math.round(Number(r2.reading_value) - Number(r1.reading_value)))
    sectors.push({ start: sStart, end: r2.reading_date, start_reading: Number(r1.reading_value),
      end_reading: Number(r2.reading_value), total_usage: usage, block_days: bd,
      daily_avg: bd > 0 ? Math.round(usage / bd * 10) / 10 : 0 })
  }
  return sectors
}

// ══════════════════════════════════════════════════════════
// USER + ACCOUNT MODE
// ══════════════════════════════════════════════════════════
const ua = ref({
  userId: '', accountId: '', meterId: '', meterData: null,
  currentExpanded: true,
  newReadingDate: props.today || '', newReadingKl: '', newReadingLitres: 0, readingError: '',
})
const filteredAccounts = computed(() =>
  props.users.find(u => String(u.id) === String(ua.value.userId))?.accounts || []
)
const filteredMeters = computed(() =>
  filteredAccounts.value.find(a => String(a.id) === String(ua.value.accountId))?.meters || []
)
function onUserChange    () { ua.value.accountId = ''; ua.value.meterId = ''; ua.value.meterData = null }
function onAccountChange () { ua.value.meterId   = '';                         ua.value.meterData = null }
async function loadMeter () {
  if (!ua.value.meterId) return
  const res = await apiFetch(`/admin/calculator/meter/${ua.value.meterId}`)
  if (res.success) {
    const data = res.data
    if (data.previous_periods) {
      data.previous_periods = data.previous_periods.map(p => ({ ...p, expanded: false }))
    }
    ua.value.meterData    = data
    ua.value.currentExpanded = true
  }
}
async function addReading () {
  ua.value.readingError = ''
  const litres = klStrToLitres(ua.value.newReadingKl)
  const res    = await apiPost('/admin/calculator/reading', {
    meter_id: ua.value.meterId, reading_date: ua.value.newReadingDate, reading_value: litres,
  })
  if (!res.success) { ua.value.readingError = res.message || 'Failed to save'; return }
  ua.value.newReadingKl = ''; ua.value.newReadingLitres = 0
  await loadMeter()
}
async function deleteReading (id) {
  await apiDelete(`/admin/calculator/reading/${id}`)
  await loadMeter()
}
function graphBarH (dailyAvg) {
  const max = Math.max(...(ua.value.meterData?.sectors || []).map(s => s.daily_avg), 1)
  return Math.max(4, Math.round((dailyAvg / max) * 100))
}

// ── Date helpers ──────────────────────────────────────────
function blockDays (start, end) {
  return Math.round((new Date(end+'T00:00:00') - new Date(start+'T00:00:00')) / 86400000) + 1
}
function nextDay (date) {
  const d = new Date(date+'T00:00:00'); d.setDate(d.getDate()+1)
  return localDateStr(d)
}
function fmt (d) {
  if (!d) return '—'
  return new Date(d+'T00:00:00').toLocaleDateString('en-ZA', { day: 'numeric', month: 'long', year: 'numeric' })
}
function fmtShort (d) {
  if (!d) return ''
  return new Date(d+'T00:00:00').toLocaleDateString('en-ZA', { day: 'numeric', month: 'short' })
}
function fmtN (n, dp = 0) {
  const v = parseFloat(n ?? 0)
  return isNaN(v) ? '0' : v.toLocaleString('en-ZA', { minimumFractionDigits: dp, maximumFractionDigits: dp })
}
function fmtMoney (n) {
  const v = parseFloat(String(n ?? '0').replace(/,/g, ''))
  return isNaN(v) ? '0.00' : v.toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

// ── HTTP ──────────────────────────────────────────────────
async function apiFetch (url) { return (await window.axios.get(url)).data }
async function apiPost  (url, data) {
  try { return (await window.axios.post(url, data)).data }
  catch (e) { return e.response?.data || { success: false, message: e.message } }
}
async function apiDelete (url) {
  try { return (await window.axios.delete(url)).data }
  catch (e) { return { success: false } }
}

// Init
recomputeTestPeriod()
</script>

<style scoped>
/* ── Base ──────────────────────────────────────────────────────────────────── */
.cp {
  max-width: 960px;
  margin: 0 auto;
  padding: 1.5rem 1.5rem 5rem;
  font-family: 'Nunito', sans-serif;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  color: #1a2b3c;
}

/* ── Header ─────────────────────────────────────────────────────────────────── */
.cp-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
  padding-bottom: 0.75rem;
  border-bottom: 2px solid #B0D3DF;
}
.cp-title { font-size: 1.5rem; font-weight: 800; color: #1a2b3c; }
.cp-sub   { font-size: 0.72rem; color: #a0aec0; margin-top: 1px; letter-spacing: 0.04em; }
.cp-tabs  { display: flex; gap: 0; border: 2px solid #B0D3DF; border-radius: 8px; overflow: hidden; }
.cp-tab   {
  padding: 0.45rem 1.4rem;
  background: #fff;
  border: none;
  font-size: 0.85rem;
  font-weight: 700;
  color: #718096;
  cursor: pointer;
  transition: all 0.15s;
}
.cp-tab + .cp-tab { border-left: 2px solid #B0D3DF; }
.cp-tab--on { background: #2d3748; color: #fff; }

/* ── Cards ──────────────────────────────────────────────────────────────────── */
.card {
  background: #fff;
  border-radius: 10px;
  padding: 1.25rem 1.5rem;
  box-shadow: 0 2px 8px rgba(50, 148, 184, 0.10), 0 1px 3px rgba(0,0,0,0.05);
  border: 1px solid #e8f4f8;
}
.section-label {
  font-size: 0.72rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: #3294B8;
  margin-bottom: 1rem;
}

/* ── Form fields ────────────────────────────────────────────────────────────── */
.fields-row { display: flex; gap: 1rem; flex-wrap: wrap; }
.field      { display: flex; flex-direction: column; min-width: 130px; flex: 1; }
.field--grow { flex: 2; }
.f-label    { font-size: 0.72rem; font-weight: 700; color: #718096; margin-bottom: 0.3rem; }
.f-input {
  padding: 0.5rem 0.75rem;
  border: 1.5px solid #B0D3DF;
  border-radius: 7px;
  font-size: 0.88rem;
  font-family: 'Nunito', sans-serif;
  color: #1a2b3c;
  background: #fff;
  box-shadow: 0 1px 3px rgba(50, 148, 184, 0.08);
  transition: border-color 0.15s, box-shadow 0.15s;
  width: 100%;
  box-sizing: border-box;
}
.f-input:focus   { border-color: #3294B8; box-shadow: 0 0 0 3px rgba(50,148,184,0.12); outline: none; }
.f-input:disabled { background: #f7fafb; cursor: not-allowed; color: #a0aec0; }

/* ── Date input with calendar icon ─────────────────────────────────────────── */
.date-wrap {
  position: relative;
  display: flex;
  align-items: center;
}
.date-icon {
  position: absolute;
  left: 0.65rem;
  color: #3294B8;
  font-size: 0.78rem;
  pointer-events: none;
  z-index: 1;
}
.date-wrap .f-input {
  padding-left: 2rem;
}

/* ── Period chips ───────────────────────────────────────────────────────────── */
.period-chip-row { margin-top: 0.85rem; display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
.chip-period {
  display: inline-block; padding: 0.25rem 0.75rem;
  background: #ebf7fc; color: #2b7fa3; border: 1px solid #B0D3DF;
  border-radius: 20px; font-size: 0.78rem; font-weight: 700;
}
.chip-days {
  display: inline-block; padding: 0.2rem 0.55rem;
  background: #f7fafb; color: #718096; border: 1px solid #e2e8f0;
  border-radius: 20px; font-size: 0.74rem; font-weight: 600;
  margin-left: 0.3rem;
}
.chip-open {
  display: inline-block; padding: 0.15rem 0.55rem;
  background: #f0fff4; color: #276749; border: 1px solid #9ae6b4;
  border-radius: 4px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; margin-left: 0.4rem;
}

/* ── Start Reading row ──────────────────────────────────────────────────────── */
.start-reading-row { display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; }
.start-reading-actions { display: flex; flex-direction: column; gap: 0.5rem; justify-content: center; }
.confirm-hint { font-size: 0.78rem; color: #718096; max-width: 220px; line-height: 1.4; }
.btn-confirm {
  padding: 0.5rem 1.5rem; background: #3294B8; color: #fff; border: none; border-radius: 7px;
  font-size: 0.88rem; font-weight: 700; cursor: pointer; transition: background 0.15s; align-self: flex-start;
}
.btn-confirm:hover { background: #2a7a9e; }
.confirmed-tag {
  font-size: 0.88rem; font-weight: 700; color: #2f855a; background: #f0fff4;
  border: 1.5px solid #9ae6b4; border-radius: 7px; padding: 0.5rem 1rem;
}

/* ── Period blocks ──────────────────────────────────────────────────────────── */
.period-block {
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 2px 8px rgba(50, 148, 184, 0.10), 0 1px 3px rgba(0,0,0,0.05);
  border: 1px solid #e8f4f8;
  border-left: 4px solid #3294B8;
  display: flex; flex-direction: column;
  overflow: hidden;
}
.period-block--closed   { border-left-color: #B0D3DF; }
.period-block--expanded { gap: 0; }

/* ── Period header ──────────────────────────────────────────────────────────── */
.period-hdr {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.9rem 1.25rem;
  cursor: pointer;
  user-select: none;
  transition: background 0.12s;
  background: #fff;
}
.period-hdr:hover { background: #f7fbfd; }
.period-block--collapsed .period-hdr { padding-bottom: 0.85rem; }
.period-hdr-left  { display: flex; flex-direction: column; gap: 0.3rem; flex: 1; }
.period-hdr-right { display: flex; align-items: center; gap: 0.75rem; flex-shrink: 0; padding-top: 0.1rem; }
.period-hdr-title {
  font-size: 0.95rem; font-weight: 800; color: #1a2b3c;
  display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;
}
.period-hdr-dates {
  font-size: 0.8rem; font-weight: 600; color: #718096;
  display: inline-flex; align-items: center; gap: 0.25rem; flex-wrap: wrap;
}

/* ── Collapsed summary line ─────────────────────────────────────────────────── */
.period-collapsed-summary {
  display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;
  font-size: 0.78rem; color: #718096;
}
.cs-sep   { color: #B0D3DF; font-weight: 700; }
.cs-label { color: #a0aec0; }
.cs-val   { font-weight: 700; color: #2d3748; font-family: 'Courier New', monospace; }
.cs-val--bill { color: #276749; font-family: 'Nunito', sans-serif; }
.cs-item  { display: inline-flex; align-items: center; gap: 0.25rem; }

/* ── Chevron ────────────────────────────────────────────────────────────────── */
.period-chevron {
  color: #B0D3DF; font-size: 0.8rem; transition: color 0.15s;
}
.period-hdr:hover .period-chevron { color: #3294B8; }

/* ── Opening label row ──────────────────────────────────────────────────────── */
.period-opening-row {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.45rem 1.25rem;
  background: #f0f8fb;
  border-top: 1px solid #e8f4f8;
  border-bottom: 1px solid #e8f4f8;
  flex-wrap: wrap;
}
.por-label {
  font-size: 0.7rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: #3294B8;
}
.por-val {
  font-family: 'Courier New', monospace;
  font-size: 0.88rem;
  font-weight: 700;
  color: #1a2b3c;
}
.por-was {
  font-size: 0.72rem;
  color: #a0aec0;
  font-style: italic;
  margin-left: 0.3rem;
}

/* ── Stats bar ──────────────────────────────────────────────────────────────── */
.stats-bar {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  background: #B0D3DF;
}
.stats-bar--sm { grid-template-columns: 1fr; }
.stat-cell {
  padding: 0.75rem 1rem;
  text-align: center;
  border-right: 1px solid rgba(255,255,255,0.5);
}
.stat-cell:last-child { border-right: none; }
.stat-label { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: #1a4f6e; }
.stat-val   { font-size: 1rem; font-weight: 800; color: #1a2b3c; margin-top: 3px; }

/* ── Adjustment notice ──────────────────────────────────────────────────────── */
.adjustment-notice {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.45rem 1.25rem;
  font-size: 0.8rem;
  flex-wrap: wrap;
}
.adjustment-notice i { font-size: 0.75rem; }
.adj-shortfall { background: #fff5f5; color: #c53030; border-top: 1px solid #fed7d7; }
.adj-surplus   { background: #f0fff4; color: #276749; border-top: 1px solid #c6f6d5; }

/* ── Expanded content side padding ─────────────────────────────────────────── */
.period-block--expanded .readings-section,
.period-block--expanded .sectors-section,
.period-block--expanded .period-actions,
.period-block--expanded .msg-error,
.period-block--expanded .anchor-grid,
.period-block--expanded .recon-row {
  margin: 0 1.25rem;
}
.period-block--expanded .stats-bar--sm { margin-bottom: 0; }
/* Period billing spans full width — no side margin */
.period-block--expanded .period-billing { margin: 0; }

/* ── Closing bar ────────────────────────────────────────────────────────────── */
.closing-bar {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  background: #f0f4f6;
  border-top: 1px solid #e2e8f0;
  margin-top: 0.75rem;
}
.closing-bar--resolved {
  background: #eaf6f0;
  border-top-color: #9ae6b4;
}
.closing-cell {
  padding: 0.65rem 1rem;
  text-align: center;
  border-right: 1px solid #e2e8f0;
}
.closing-cell:last-child { border-right: none; }
.closing-cell-label {
  font-size: 0.68rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #718096;
  margin-bottom: 0.25rem;
}
.closing-cell-val {
  font-size: 0.96rem;
  font-weight: 800;
  font-family: 'Courier New', monospace;
  color: #1a2b3c;
}
.closing-cell-sub {
  font-size: 0.68rem;
  color: #a0aec0;
  margin-top: 2px;
}

/* ── Anchor grid ────────────────────────────────────────────────────────────── */
.anchor-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.75rem;
  padding: 0.75rem 0;
}
@media (max-width: 700px) { .anchor-grid { grid-template-columns: repeat(2, 1fr); } }
.anchor-cell {
  background: #f7fafb;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  padding: 0.65rem 0.85rem;
}
.anchor-cell--open    { background: #ebf7fc; border-color: #B0D3DF; }
.anchor-cell--pending { background: #fffbeb; border-color: #fef08a; }
.anchor-cell-label {
  font-size: 0.68rem; font-weight: 800; text-transform: uppercase;
  letter-spacing: 0.05em; color: #718096; margin-bottom: 0.35rem;
}
.anchor-cell--open .anchor-cell-label    { color: #3294B8; }
.anchor-cell--pending .anchor-cell-label { color: #b7791f; }
.anchor-cell-val  { font-size: 1.05rem; font-weight: 800; font-family: 'Courier New', monospace; color: #1a2b3c; }
.anchor-cell-sub  { font-size: 0.68rem; color: #a0aec0; margin-top: 2px; }
.val-empty        { color: #a0aec0; font-style: italic; font-size: 0.95rem; }
.val-provisional  { color: #c05621; }
.val-calculated   { color: #2f855a; }
.val-shortfall    { color: #c53030; }
.val-surplus      { color: #276749; }

/* ── Readings ───────────────────────────────────────────────────────────────── */
.readings-section { display: flex; flex-direction: column; gap: 0.5rem; padding: 0.75rem 0 0.5rem; }
.readings-header  { display: flex; align-items: baseline; gap: 0.5rem; }
.readings-header-label { font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #3294B8; }
.readings-header-hint  { font-size: 0.7rem; color: #a0aec0; }

.reading-row {
  display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;
  padding: 0.35rem 0; border-bottom: 1px dashed #e8f4f8;
}
.reading-row:last-of-type { border-bottom: none; }
.r-date   { width: 152px; flex: 0 0 152px; }
.r-litres, .r-litres-display { font-size: 0.76rem; color: #a0aec0; white-space: nowrap; }
.r-date-display  { font-size: 0.84rem; font-weight: 700; color: #2d3748; min-width: 150px; }
.r-kl-display    { font-family: 'Courier New', monospace; font-size: 0.88rem; font-weight: 700; color: #3294B8; }
.r-sector-avg    { font-size: 0.76rem; color: #718096; }
.btn-rm {
  background: none; border: none; color: #e53e3e; font-size: 0.88rem; cursor: pointer; padding: 0.1rem 0.3rem;
}

.empty-readings { font-size: 0.82rem; color: #a0aec0; font-style: italic; padding: 0.25rem 0; }
.reading-row--error { background: #fff5f5; border-radius: 6px; padding-left: 0.4rem; margin-left: -0.4rem; }
.r-seq-error {
  font-size: 0.74rem; color: #c53030; display: flex; align-items: center;
  gap: 0.3rem; white-space: nowrap; font-weight: 600;
}
.add-reading-form { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1.5px solid #ebf7fc; }

/* ── Sectors ────────────────────────────────────────────────────────────────── */
.sectors-section { display: flex; flex-direction: column; gap: 0.5rem; padding-bottom: 0.25rem; }
.sectors-label { font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #718096; padding-top: 0.25rem; }

/* ── Actions ────────────────────────────────────────────────────────────────── */
.period-actions { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; padding: 0.5rem 0; }
.btn-add-reading {
  padding: 0.42rem 1rem;
  background: transparent; border: 2px solid #3294B8; border-radius: 7px;
  color: #3294B8; font-size: 0.84rem; font-weight: 700; cursor: pointer; transition: all 0.15s;
  display: flex; align-items: center; gap: 0.4rem;
}
.btn-add-reading:hover { background: #3294B8; color: #fff; }
.btn-calc {
  padding: 0.45rem 1.75rem;
  background: #2d3748; color: #fff; border: none; border-radius: 7px;
  font-size: 0.88rem; font-weight: 800; cursor: pointer; transition: background 0.15s;
}
.btn-calc:hover:not(:disabled) { background: #1a202c; }
.btn-calc:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-add-period {
  padding: 0.38rem 1rem;
  background: #3294B8; color: #fff; border: none; border-radius: 7px;
  font-size: 0.82rem; font-weight: 700; cursor: pointer; transition: background 0.15s; white-space: nowrap;
}
.btn-add-period:hover { background: #2a7a9e; }
.btn-add-period-bottom {
  padding: 0.45rem 1.5rem;
  background: #3294B8; color: #fff; border: none; border-radius: 7px;
  font-size: 0.88rem; font-weight: 700; cursor: pointer; transition: background 0.15s; align-self: flex-start;
  display: flex; align-items: center; gap: 0.4rem;
}
.btn-add-period-bottom:hover { background: #2a7a9e; }
.btn-save-reading {
  padding: 0.42rem 1rem;
  background: #3294B8; color: #fff; border: none; border-radius: 7px;
  font-size: 0.84rem; font-weight: 700; cursor: pointer; transition: background 0.15s;
  display: flex; align-items: center; gap: 0.35rem;
}
.btn-save-reading:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-save-reading:hover:not(:disabled) { background: #2a7a9e; }

/* ── Tables ─────────────────────────────────────────────────────────────────── */
.data-table { width: 100%; border-collapse: collapse; font-size: 0.84rem; }
.data-table th {
  background: #f0f8fb; padding: 0.45rem 0.75rem; text-align: left;
  font-size: 0.74rem; font-weight: 800; color: #3294B8; border-bottom: 2px solid #B0D3DF;
}
.data-table td { padding: 0.4rem 0.75rem; border-bottom: 1px solid #f0f8fb; color: #2d3748; }
.data-table tr:hover td { background: #f7fbfd; }
.data-table .num { text-align: right; font-variant-numeric: tabular-nums; }
.data-table .total-row td { font-weight: 800; background: #f0f8fb !important; border-top: 2px solid #B0D3DF; }

/* ── Billing section (inside period block) ──────────────────────────────────── */
.period-billing {
  background: #1a2b3c;
  border-top: 2px solid #2d3748;
  padding: 1rem 1.25rem 1.25rem;
}
.period-billing-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.78rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: #B0D3DF;
  margin-bottom: 0.85rem;
}
.period-billing-header i { font-size: 0.82rem; color: #3294B8; }
.period-billing .bill-stat {
  background: rgba(255,255,255,0.06);
  border-color: rgba(176,211,223,0.2);
}
.period-billing .bill-stat-label { color: #B0D3DF; }
.period-billing .bill-stat-val   { color: #fff; }
.period-billing .bill-stat--total { background: #3294B8; border-color: #3294B8; }
.period-billing .tier-label { color: #B0D3DF; }
.period-billing .data-table th { background: rgba(255,255,255,0.05); color: #B0D3DF; border-color: rgba(176,211,223,0.3); }
.period-billing .data-table td { color: #e2e8f0; border-color: rgba(255,255,255,0.06); }
.period-billing .data-table tr:hover td { background: rgba(255,255,255,0.05); }
.period-billing .data-table .total-row td { background: rgba(255,255,255,0.08) !important; border-color: rgba(176,211,223,0.3); }

.bill-grid { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1.25rem; }
.bill-stat {
  flex: 1; min-width: 130px; padding: 0.75rem 1rem;
  background: #f7fafb; border: 1.5px solid #e2e8f0; border-radius: 8px;
}
.bill-stat--total { background: #1a2b3c; border-color: #1a2b3c; }
.bill-stat--total .bill-stat-label, .bill-stat--total .bill-stat-val { color: #fff; }
.bill-stat-label { font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: #718096; }
.bill-stat-val   { font-size: 1.05rem; font-weight: 800; color: #1a2b3c; margin-top: 3px; }
.tier-section    { margin-top: 0.5rem; }
.tier-label      { font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #718096; margin-bottom: 0.5rem; }

/* ── Graph ──────────────────────────────────────────────────────────────────── */
.usage-graph  { margin-top: 0.75rem; }
.graph-title  { font-size: 0.7rem; font-weight: 800; color: #718096; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem; }
.graph-bars   { display: flex; align-items: flex-end; gap: 5px; height: 140px; padding-bottom: 24px; position: relative; }
.graph-bar-col { display: flex; flex-direction: column; align-items: center; justify-content: flex-end; flex: 1; min-width: 24px; height: 100%; }
.graph-bar-num  { font-size: 0.62rem; color: #718096; margin-bottom: 2px; white-space: nowrap; }
.graph-bar { width: 100%; max-width: 44px; background: linear-gradient(180deg, #3294B8, #2a7a9e); border-radius: 4px 4px 0 0; transition: height 0.3s; }
.graph-bar-date { font-size: 0.62rem; color: #a0aec0; white-space: nowrap; margin-top: 2px; position: absolute; bottom: 0; }

/* ── Reconciliation ─────────────────────────────────────────────────────────── */
.recon-row {
  padding: 0.5rem 0.85rem; border-radius: 6px; font-size: 0.82rem; font-weight: 600; margin-bottom: 0.5rem;
}
.recon--short   { background: #fff5f5; color: #c53030; border-left: 3px solid #fc8181; }
.recon--surplus { background: #f0fff4; color: #276749; border-left: 3px solid #68d391; }

/* ── Status badges ──────────────────────────────────────────────────────────── */
.status-badge { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.68rem; font-weight: 800; text-transform: uppercase; margin-left: 0.3rem; }
.status-provisional { background: #fffbeb; color: #b7791f; border: 1px solid #f6e05e; }
.status-calculated  { background: #ebf8ff; color: #2b6cb0; border: 1px solid #90cdf4; }
.status-actual      { background: #f0fff4; color: #276749; border: 1px solid #9ae6b4; }

/* ── Messages ───────────────────────────────────────────────────────────────── */
.msg-error {
  padding: 0.5rem 0.75rem; background: #fff5f5; border: 1.5px solid #fc8181;
  border-radius: 6px; color: #c53030; font-size: 0.83rem; margin-bottom: 0.25rem;
}

/* ── Responsive ─────────────────────────────────────────────────────────────── */
@media (max-width: 600px) {
  .cp { padding: 1rem; }
  .anchor-grid { grid-template-columns: repeat(2, 1fr); }
  .stats-bar { grid-template-columns: 1fr; }
  .bill-grid { flex-direction: column; }
  .closing-bar { grid-template-columns: 1fr; }
}
</style>
