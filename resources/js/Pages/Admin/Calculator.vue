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

      <!-- ══════════ TEST MODE SETUP ══════════ -->
      <template v-if="mode === 'test'">
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
              <input type="month" v-model="test.startMonth" class="f-input" @change="recomputeTestPeriod" />
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
          <!-- Current date override row -->
          <div class="current-date-row">
            <span class="current-date-label"><i class="fas fa-calendar-day"></i> Current Date</span>
            <input type="date" v-model="test.currentDate" class="f-input current-date-input"
              :min="test.periods.length ? test.periods[test.periods.length - 1].start : test.periodStart"
              :max="props.today || localDateStr(new Date())"
            />
            <button
              :class="['btn-date-toggle', test.currentDateActive ? 'btn-date-toggle--on' : 'btn-date-toggle--off']"
              @click="test.currentDateActive = !test.currentDateActive"
            >
              <i :class="['fas', test.currentDateActive ? 'fa-toggle-on' : 'fa-toggle-off']"></i>
              {{ test.currentDateActive ? 'Active' : 'Inactive' }}
            </button>
            <span v-if="test.currentDateActive" class="current-date-hint">
              Simulating date: <strong>{{ fmt(test.currentDate) }}</strong>
            </span>
          </div>

          <div v-if="test.periodStart" class="period-chip-row">
            <span class="chip-period">{{ fmt(test.periodStart) }} → {{ fmt(test.periodEnd) }}</span>
            <span class="chip-days">{{ test.periodDays }} block days</span>
            <span v-if="hasWater" class="chip-meter chip-meter--water"><i class="fas fa-tint"></i> Water</span>
            <span v-if="hasElec"  class="chip-meter chip-meter--elec"><i class="fas fa-bolt"></i> Electricity</span>
          </div>
        </div>

      </template>

      <!-- ══════════ ACCOUNT MODE SETUP ══════════ -->
      <template v-if="mode === 'account'">
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
              <select v-model="ua.accountId" class="f-input" :disabled="!ua.userId" @change="loadAccount">
                <option value="">— Select Account —</option>
                <option v-for="a in filteredAccounts" :key="a.id" :value="a.id">
                  {{ a.account_name }} ({{ a.account_number }})
                </option>
              </select>
            </div>
          </div>
          <div v-if="ua.loading" class="ua-loading">
            <i class="fas fa-circle-notch fa-spin"></i> Loading account data…
          </div>
          <div v-if="ua.accountData" class="ua-meta">
            <span class="ua-meta-item">
              <span class="ua-meta-label">Bill Day</span>
              <span class="ua-meta-val">{{ ua.accountData.account.bill_day }}</span>
            </span>
            <span class="ua-meta-item" v-if="ua.accountData.tariff">
              <span class="ua-meta-label">Tariff</span>
              <span class="ua-meta-val">{{ ua.accountData.tariff.template_name }}</span>
            </span>
            <span v-for="m in ua.accountData.meters" :key="m.id" class="ua-meta-item">
              <span class="ua-meta-label">
                <i :class="m.meter_type === 'water' ? 'fas fa-tint' : 'fas fa-bolt'"></i>
                {{ m.meter_type === 'water' ? 'Water' : 'Electricity' }}
              </span>
              <span class="ua-meta-val">{{ m.meter_number }}</span>
            </span>
          </div>
        </div>
      </template>

      <!-- ══════════════════════════════════════════════════
           TOP-LEVEL METER TABS (Water / Electricity)
           Visible as soon as a tariff is selected in test mode, or account is loaded
           ══════════════════════════════════════════════════ -->
      <div
        v-if="(mode === 'test' && !!test.periodStart) || (mode === 'account' && activePeriods.length > 0)"
        class="top-meter-tabs"
      >
        <button
          :class="['top-meter-tab top-meter-tab--water', (mode==='test' ? test.activeMeterTab : ua.activeMeterTab) === 'water' ? 'top-meter-tab--on' : '']"
          @click="mode==='test' ? (test.activeMeterTab='water') : (ua.activeMeterTab='water')"
        >
          <i class="fas fa-tint"></i> Water
        </button>
        <button
          :class="['top-meter-tab top-meter-tab--elec', (mode==='test' ? test.activeMeterTab : ua.activeMeterTab) === 'electricity' ? 'top-meter-tab-elec--on' : '']"
          @click="mode==='test' ? (test.activeMeterTab='electricity') : (ua.activeMeterTab='electricity')"
        >
          <i class="fas fa-bolt"></i> Electricity
        </button>
      </div>

      <!-- ══════════════════════════════════════════════════
           SHARED PERIOD BLOCKS (test + account use same template)
           ══════════════════════════════════════════════════ -->
      <template v-if="activePeriods.length > 0">
        <div
          v-for="(period, pi) in activePeriods"
          :key="pi"
          class="period-block"
          :class="[
            period.expanded ? 'period-block--expanded' : 'period-block--collapsed',
            pi < activePeriods.length - 1 ? 'period-block--closed' : 'period-block--open',
          ]"
        >
          <!-- ── Collapsible header ── -->
          <div class="period-hdr" @click="onPeriodHeaderClick(period, pi)">
            <div class="period-hdr-left">
              <div class="period-hdr-title">
                Period {{ pi + 1 }}
                <span v-if="pi === activePeriods.length - 1 && mode === 'account'" class="chip-open">OPEN</span>
                <span class="period-hdr-dates">
                  {{ fmt(period.start) }} → {{ fmt(period.end) }}
                  <span class="chip-days">{{ period.blockDays }} days</span>
                </span>
              </div>
              <!-- Collapsed summary -->
              <div v-show="!period.expanded" class="period-collapsed-summary">
                <span v-if="period.water" class="cs-item">
                  <i class="fas fa-tint" style="color:#3294B8;font-size:.7rem;"></i>
                  <span class="cs-val">
                    {{ period.water.calculatedClosingLitres != null
                      ? litresToKlStr(period.water.calculatedClosingLitres)
                      : period.water.provisionalClosingLitres != null
                        ? litresToKlStr(period.water.provisionalClosingLitres) : '_ _' }} kL
                  </span>
                </span>
                <span v-if="period.electricity" class="cs-sep">·</span>
                <span v-if="period.electricity" class="cs-item">
                  <i class="fas fa-bolt" style="color:#d69e2e;font-size:.7rem;"></i>
                  <span class="cs-val">
                    {{ period.electricity.calculatedClosingKwh != null
                      ? fmtN(period.electricity.calculatedClosingKwh)
                      : period.electricity.provisionalClosingKwh != null
                        ? fmtN(period.electricity.provisionalClosingKwh) : '_ _' }} kWh
                  </span>
                </span>
                <template v-if="period.bill">
                  <span class="cs-sep">·</span>
                  <span class="cs-item">
                    <span class="cs-label">Bill:</span>
                    <span class="cs-val cs-val--bill">R {{ fmtMoney(period.bill.grand_total) }}</span>
                  </span>
                </template>
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


            <!-- ══ WATER CONTENT ══ -->
            <!-- No-water message when tariff has no water support -->
            <div v-if="activeMeter(period) === 'water' && (mode === 'test' ? !hasWater : !period.water)" class="tab-no-data">
              <i class="fas fa-info-circle"></i>
              Chosen tariff template does not contain water data.
            </div>
            <!-- Water meter initialization form (test mode, not yet initialized in this or earlier period) -->
            <div v-else-if="activeMeter(period) === 'water' && mode === 'test' && !isMeterInitialized(pi, 'water')" class="meter-init-form meter-init-form--water">
              <div class="init-form-title"><i class="fas fa-tint"></i> Initialize Water Meter</div>
              <div class="init-form-row">
                <MeterInput v-model="period._wInitReading" />
                <div class="date-wrap">
                  <i class="fas fa-calendar-alt date-icon"></i>
                  <input type="date" v-model="period._wInitDate" class="f-input r-date"
                    :min="period.start" :max="period.end" />
                </div>
                <button class="btn-init btn-init--water" @click="confirmMeterInit(period, pi, 'water')">
                  <i class="fas fa-check"></i> Initialize
                </button>
              </div>
            </div>
            <template v-else-if="activeMeter(period) === 'water' && period.water">

              <!-- Insufficient data notice (account mode, single initialization reading only) -->
              <div v-if="period.water.insufficientData && mode === 'account'" class="insufficient-data-notice">
                <i class="fas fa-exclamation-triangle"></i>
                Unable to compute. A minimum of two readings are required for this app to successfully compute.
              </div>

              <!-- Opening -->
              <div class="period-opening-row">
                <span class="por-label">{{ pi === 0 && mode === 'test' ? 'Start Reading' : 'Opening Reading' }}</span>
                <span class="por-val">{{ litresToKlStr(period.water.openingLitres) }} kL</span>
                <span
                  v-if="pi > 0 && prevPeriod(pi)?.water?.provisionalClosingSnapshot != null"
                  class="por-was"
                >
                  Provisional — {{ litresToKlStr(prevPeriod(pi).water.provisionalClosingSnapshot) }} updated
                </span>
              </div>

              <!-- Stats bar -->
              <div class="stats-bar">
                <div class="stat-cell">
                  <div class="stat-label">Daily Usage</div>
                  <div class="stat-val">{{ period.water.dailyUsage != null ? fmtN(period.water.dailyUsage, 0) + ' L' : '_ _' }}</div>
                </div>
                <div class="stat-cell">
                  <div class="stat-label">Current Usage</div>
                  <div class="stat-val">{{ period.water.stats && period.water.stats.currentR > 0 ? 'R ' + fmtMoney(period.water.stats.currentR) : '_ _' }}</div>
                </div>
                <div class="stat-cell">
                  <div class="stat-label">Projected Usage</div>
                  <div class="stat-val">{{ period.water.stats ? 'R ' + fmtMoney(period.water.stats.projectedR) : '_ _' }}</div>
                </div>
              </div>

              <!-- Read Day countdown strip (current period only) -->
              <template v-if="pi === activePeriods.length - 1">
                <div v-if="readDayStatus(period).inWindow || readDayStatus(period).daysTo <= 0"
                  :class="['read-day-strip', readDayStatus(period).daysTo < 0 ? 'read-day-strip--overdue' : readDayStatus(period).daysTo === 0 ? 'read-day-strip--today' : 'read-day-strip--soon']"
                >
                  <i :class="['fas', readDayStatus(period).daysTo < 0 ? 'fa-exclamation-circle' : 'fa-clock']"></i>
                  <span v-if="readDayStatus(period).daysTo > 0">
                    Read Day in <strong>{{ readDayStatus(period).daysTo }}</strong> day{{ readDayStatus(period).daysTo === 1 ? '' : 's' }}
                    <span class="rds-date">({{ fmt(readDayStatus(period).readDay) }})</span>
                  </span>
                  <span v-else-if="readDayStatus(period).daysTo === 0">
                    <strong>Read Day is today</strong> — please read your meter now.
                    <span class="rds-date">({{ fmt(readDayStatus(period).readDay) }})</span>
                  </span>
                  <span v-else>
                    Read Day was <strong>{{ Math.abs(readDayStatus(period).daysTo) }}</strong> day{{ Math.abs(readDayStatus(period).daysTo) === 1 ? '' : 's' }} ago — last read {{ readDayStatus(period).daysSinceLast }} day{{ readDayStatus(period).daysSinceLast === 1 ? '' : 's' }} ago.
                  </span>
                </div>
              </template>

              <!-- Adjustment notice -->
              <div
                v-if="period.water.adjustmentBroughtForward"
                class="adjustment-notice"
                :class="period.water.adjustmentBroughtForward > 0 ? 'adj-shortfall' : 'adj-surplus'"
              >
                <i class="fas fa-exchange-alt"></i>
                Adjustment from Period {{ pi }} carried forward:
                <strong>{{ period.water.adjustmentBroughtForward > 0 ? '+' : '' }}R {{ fmtMoney(Math.abs(period.water.adjustmentBroughtForward)) }}</strong>
              </div>

              <!-- Readings -->
              <div class="readings-section">
                <div class="readings-header">
                  <span class="readings-header-label">Readings</span>
                  <span class="readings-header-hint">{{ mode === 'test' ? 'enter in kL · format 0000.00' : 'kL' }}</span>
                </div>
                <!-- Test mode: editable -->
                <template v-if="mode === 'test'">
                  <div
                    v-for="(r, ri) in period.water.readings"
                    :key="ri"
                    class="reading-row"
                    :class="r.error && 'reading-row--error'"
                  >
                    <div class="date-wrap">
                      <i class="fas fa-calendar-alt date-icon"></i>
                      <input type="date" v-model="r.date" class="f-input r-date"
                        :min="period.start" :max="period.end"
                        @change="recomputePeriodWater(period, pi)" />
                    </div>
                    <MeterInput v-model="r.klStr" @change="onWaterInput(period, r, pi)" />
                    <div class="r-litres" v-if="r.litres && !r.error">{{ fmtN(r.litres) }} L</div>
                    <div class="r-seq-error" v-if="r.error">
                      <i class="fas fa-exclamation-triangle"></i> {{ r.error }}
                    </div>
                    <button class="btn-rm" @click="period.water.readings.splice(ri, 1); recomputePeriodWater(period, pi)">
                      <i class="fas fa-times"></i>
                    </button>
                  </div>
                  <div v-if="period.water.readings.length === 0" class="empty-readings">
                    No readings yet — click "+ Add Reading"
                  </div>
                </template>
                <!-- Account mode: read-only -->
                <template v-else>
                  <div v-for="r in period.water.readings" :key="r.id" class="reading-row">
                    <span class="r-date-display">{{ fmt(r.date) }}</span>
                    <span class="r-kl-display">{{ litresToKlStr(r.value) }} kL</span>
                    <span class="r-litres-display">{{ fmtN(r.value) }} L</span>
                  </div>
                  <div v-if="period.water.readings.length === 0" class="empty-readings">No intra-period readings.</div>
                </template>
              </div>

              <!-- Sectors -->
              <div v-if="period.water.sectors.length" class="sectors-section">
                <div class="sectors-label">Sectors</div>
                <table class="data-table">
                  <thead>
                    <tr><th>From</th><th>To</th><th class="num">Block Days</th><th class="num">Usage (L)</th><th class="num">Daily Avg</th></tr>
                  </thead>
                  <tbody>
                    <tr v-for="(s, si) in period.water.sectors" :key="si">
                      <td>{{ fmt(s.start) }}</td><td>{{ fmt(s.end) }}</td>
                      <td class="num">{{ s.block_days }}</td>
                      <td class="num">{{ fmtN(s.total_usage) }}</td>
                      <td class="num">{{ fmtN(s.daily_avg, 1) }} L/day</td>
                    </tr>
                    <tr class="total-row">
                      <td colspan="2">Total</td>
                      <td class="num">{{ period.water.sectors.reduce((a,s)=>a+s.block_days,0) }}</td>
                      <td class="num">{{ fmtN(period.water.sectors.reduce((a,s)=>a+s.total_usage,0)) }}</td>
                      <td></td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Closing bar (water) -->
              <div class="closing-bar" :class="period.water.calculatedClosingLitres != null && 'closing-bar--resolved'">
                <div class="closing-cell">
                  <div class="closing-cell-label">Closing provisional</div>
                  <div class="closing-cell-val val-provisional">
                    {{ period.water.provisionalClosingLitres != null ? litresToKlStr(period.water.provisionalClosingLitres) : '_ _' }}
                  </div>
                  <div class="closing-cell-sub" v-if="period.water.provisionalBillR != null">R {{ fmtMoney(period.water.provisionalBillR) }}</div>
                  <div class="closing-cell-sub" v-else-if="period.water.provisionalClosingLitres != null">kL</div>
                </div>
                <div class="closing-cell">
                  <div class="closing-cell-label">Closing calculated</div>
                  <div class="closing-cell-val" :class="period.water.calculatedClosingLitres != null ? 'val-calculated' : 'val-empty'">
                    {{ period.water.calculatedClosingLitres != null ? litresToKlStr(period.water.calculatedClosingLitres) : '_ _' }}
                  </div>
                  <div class="closing-cell-sub" v-if="period.water.calculatedBillR != null">R {{ fmtMoney(period.water.calculatedBillR) }}</div>
                  <div class="closing-cell-sub" v-else-if="period.water.calculatedClosingLitres != null">kL</div>
                </div>
                <div class="closing-cell">
                  <div class="closing-cell-label">Adjustment</div>
                  <div class="closing-cell-val" :class="waterAdjClass(period)">{{ formatWaterAdj(period) }}</div>
                  <div class="closing-cell-sub" v-if="period.water.calculatedClosingLitres != null">
                    {{ (period.water.calculatedClosingLitres - period.water.provisionalClosingLitres) >= 0 ? 'shortfall' : 'surplus' }}
                  </div>
                </div>
              </div>
            </template><!-- /water -->

            <!-- ══ ELECTRICITY CONTENT ══ -->
            <!-- No-electricity message when tariff has no electricity support -->
            <div v-if="activeMeter(period) === 'electricity' && (mode === 'test' ? !hasElec : !period.electricity)" class="tab-no-data">
              <i class="fas fa-info-circle"></i>
              Chosen tariff template does not contain electricity data.
            </div>
            <!-- Electricity meter initialization form (test mode, not yet initialized in this or earlier period) -->
            <div v-else-if="activeMeter(period) === 'electricity' && mode === 'test' && !isMeterInitialized(pi, 'electricity')" class="meter-init-form meter-init-form--elec">
              <div class="init-form-title"><i class="fas fa-bolt"></i> Initialize Electricity Meter</div>
              <div class="init-form-row">
                <ElecInput v-model="period._eInitReading" />
                <div class="date-wrap">
                  <i class="fas fa-calendar-alt date-icon"></i>
                  <input type="date" v-model="period._eInitDate" class="f-input r-date"
                    :min="period.start" :max="period.end" />
                </div>
                <button class="btn-init btn-init--elec" @click="confirmMeterInit(period, pi, 'electricity')">
                  <i class="fas fa-check"></i> Initialize
                </button>
              </div>
            </div>
            <template v-else-if="activeMeter(period) === 'electricity' && period.electricity">

              <!-- Insufficient data notice (account mode, single initialization reading only) -->
              <div v-if="period.electricity.insufficientData && mode === 'account'" class="insufficient-data-notice">
                <i class="fas fa-exclamation-triangle"></i>
                Unable to compute. A minimum of two readings are required for this app to successfully compute.
              </div>

              <!-- Opening -->
              <div class="period-opening-row period-opening-row--elec">
                <span class="por-label por-label--elec">{{ pi === 0 && mode === 'test' ? 'Start Reading' : 'Opening Reading' }}</span>
                <span class="por-val">{{ fmtN(period.electricity.openingKwh) }} kWh</span>
              </div>

              <!-- Stats bar (electricity) -->
              <div class="stats-bar stats-bar--elec">
                <div class="stat-cell">
                  <div class="stat-label">Daily Usage</div>
                  <div class="stat-val">{{ period.electricity.dailyUsage != null ? fmtN(period.electricity.dailyUsage, 0) + ' kWh' : '_ _' }}</div>
                </div>
                <div class="stat-cell">
                  <div class="stat-label">Current Usage</div>
                  <div class="stat-val">{{ period.electricity.stats && period.electricity.stats.currentR > 0 ? 'R ' + fmtMoney(period.electricity.stats.currentR) : '_ _' }}</div>
                </div>
                <div class="stat-cell">
                  <div class="stat-label">Projected Usage</div>
                  <div class="stat-val">{{ period.electricity.stats ? 'R ' + fmtMoney(period.electricity.stats.projectedR) : '_ _' }}</div>
                </div>
              </div>

              <!-- Readings (electricity) -->
              <div class="readings-section">
                <div class="readings-header">
                  <span class="readings-header-label">Readings</span>
                  <span class="readings-header-hint">{{ mode === 'test' ? 'kWh · 6 digits' : 'kWh' }}</span>
                </div>
                <template v-if="mode === 'test'">
                  <div
                    v-for="(r, ri) in period.electricity.readings"
                    :key="ri"
                    class="reading-row"
                    :class="r.error && 'reading-row--error'"
                  >
                    <div class="date-wrap">
                      <i class="fas fa-calendar-alt date-icon"></i>
                      <input type="date" v-model="r.date" class="f-input r-date"
                        :min="period.start" :max="period.end"
                        @change="recomputePeriodElec(period, pi)" />
                    </div>
                    <ElecInput v-model="r.kwh" @change="onElecInput(period, r, pi)" />
                    <div class="r-litres" v-if="r.kwhInt && !r.error">{{ fmtN(r.kwhInt) }} kWh</div>
                    <div class="r-seq-error" v-if="r.error">
                      <i class="fas fa-exclamation-triangle"></i> {{ r.error }}
                    </div>
                    <button class="btn-rm" @click="period.electricity.readings.splice(ri, 1); recomputePeriodElec(period, pi)">
                      <i class="fas fa-times"></i>
                    </button>
                  </div>
                  <div v-if="period.electricity.readings.length === 0" class="empty-readings">
                    No readings yet — click "+ Add Reading"
                  </div>
                </template>
                <template v-else>
                  <div v-for="r in period.electricity.readings" :key="r.id" class="reading-row">
                    <span class="r-date-display">{{ fmt(r.date) }}</span>
                    <span class="r-kl-display" style="color:#d69e2e;">{{ fmtN(r.value) }} kWh</span>
                  </div>
                  <div v-if="period.electricity.readings.length === 0" class="empty-readings">No intra-period readings.</div>
                </template>
              </div>

              <!-- Sectors (electricity) -->
              <div v-if="period.electricity.sectors.length" class="sectors-section">
                <div class="sectors-label">Sectors</div>
                <table class="data-table">
                  <thead>
                    <tr><th>From</th><th>To</th><th class="num">Block Days</th><th class="num">Usage (kWh)</th><th class="num">kWh/day</th></tr>
                  </thead>
                  <tbody>
                    <tr v-for="(s, si) in period.electricity.sectors" :key="si">
                      <td>{{ fmt(s.start) }}</td><td>{{ fmt(s.end) }}</td>
                      <td class="num">{{ s.block_days }}</td>
                      <td class="num">{{ fmtN(s.total_usage) }}</td>
                      <td class="num">{{ fmtN(s.daily_avg, 1) }} kWh/day</td>
                    </tr>
                    <tr class="total-row">
                      <td colspan="2">Total</td>
                      <td class="num">{{ period.electricity.sectors.reduce((a,s)=>a+s.block_days,0) }}</td>
                      <td class="num">{{ fmtN(period.electricity.sectors.reduce((a,s)=>a+s.total_usage,0)) }}</td>
                      <td></td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Closing bar (electricity) -->
              <div class="closing-bar closing-bar--elec" :class="period.electricity.calculatedClosingKwh != null && 'closing-bar--resolved'">
                <div class="closing-cell">
                  <div class="closing-cell-label">Closing provisional</div>
                  <div class="closing-cell-val val-provisional">
                    {{ period.electricity.provisionalClosingKwh != null ? fmtN(period.electricity.provisionalClosingKwh) : '_ _' }}
                  </div>
                  <div class="closing-cell-sub" v-if="period.electricity.provisionalClosingKwh != null">kWh</div>
                </div>
                <div class="closing-cell">
                  <div class="closing-cell-label">Closing calculated</div>
                  <div class="closing-cell-val" :class="period.electricity.calculatedClosingKwh != null ? 'val-calculated' : 'val-empty'">
                    {{ period.electricity.calculatedClosingKwh != null ? fmtN(period.electricity.calculatedClosingKwh) : '_ _' }}
                  </div>
                  <div class="closing-cell-sub" v-if="period.electricity.calculatedClosingKwh != null">kWh</div>
                </div>
                <div class="closing-cell">
                  <div class="closing-cell-label">Adjustment</div>
                  <div class="closing-cell-val" :class="elecAdjClass(period)">{{ formatElecAdj(period) }}</div>
                  <div class="closing-cell-sub" v-if="period.electricity.calculatedClosingKwh != null">
                    {{ (period.electricity.calculatedClosingKwh - period.electricity.provisionalClosingKwh) >= 0 ? 'shortfall' : 'surplus' }}
                  </div>
                </div>
              </div>
            </template><!-- /electricity -->

            <!-- Period actions -->
            <div class="period-actions">
              <button v-if="mode === 'test' && !!period[activeMeter(period)]" class="btn-add-reading" @click="addReadingToPeriod(period)">
                <i class="fas fa-plus"></i> Add Reading
              </button>
              <button class="btn-calc" @click="calcPeriod(pi)"
                :disabled="!canCalcPeriod(period, pi) || period.calculating">
                <i v-if="period.calculating" class="fas fa-circle-notch fa-spin"></i>
                {{ period.calculating ? 'Calculating…' : 'Calculate' }}
              </button>
              <button v-if="period.bill" class="btn-view-bill" @click="period.showBill = !period.showBill">
                <i class="fas" :class="period.showBill ? 'fa-eye-slash' : 'fa-file-invoice-dollar'"></i>
                {{ period.showBill ? 'Hide Bill' : 'View Bill' }}
              </button>
              <span v-if="!canCalcPeriod(period, pi) && !period.calculating" class="calc-block-hint">
                <i class="fas fa-info-circle"></i> {{ calcBlockReason(period, pi) }}
              </span>
            </div>
            <div v-if="period.calcError" class="msg-error">{{ period.calcError }}</div>

            <!-- ══ BILL — shown only when View Bill is clicked ══ -->
            <div v-if="period.bill && period.showBill" class="period-billing">
              <div class="period-billing-header">
                <i class="fas fa-file-invoice-dollar"></i>
                Bill · Period {{ pi + 1 }}: {{ fmt(period.start) }} → {{ fmt(period.end) }}
              </div>

              <!-- Water section -->
              <div v-if="period.bill.water" class="bill-meter-section">
                <div class="bill-meter-hdr bill-meter-hdr--water">
                  <i class="fas fa-tint"></i> Water
                  <span class="bill-meter-consumption">
                    {{ fmtN(period.bill.water.consumption_litres) }} L · {{ fmtKl(period.bill.water.consumption_kl) }} kL
                  </span>
                </div>
                <div class="bill-grid">
                  <div class="bill-stat"><div class="bill-stat-label">Usage Charge</div><div class="bill-stat-val">R {{ fmtMoney(period.bill.water.usage_charge) }}</div></div>
                  <div class="bill-stat"><div class="bill-stat-label">VAT</div><div class="bill-stat-val">R {{ fmtMoney(period.bill.water.vat_amount) }}</div></div>
                  <div class="bill-stat"><div class="bill-stat-label">Water Subtotal</div><div class="bill-stat-val">R {{ fmtMoney(period.bill.water.usage_charge + period.bill.water.vat_amount) }}</div></div>
                </div>
                <div v-if="period.bill.water.tier_breakdown?.length" class="tier-section">
                  <div class="tier-label">Tier Breakdown</div>
                  <table class="data-table">
                    <thead><tr><th>Tier</th><th class="num">Units (kL)</th><th class="num">Rate (R/kL)</th><th class="num">Charge</th></tr></thead>
                    <tbody>
                      <tr v-for="(t, i) in period.bill.water.tier_breakdown" :key="i">
                        <td>Tier {{ i + 1 }}</td>
                        <td class="num">{{ fmtKl(t.units_kl) }}</td>
                        <td class="num">{{ t.rate }}</td>
                        <td class="num">R {{ fmtMoney(t.amount) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Electricity section -->
              <div v-if="period.bill.electricity" class="bill-meter-section">
                <div class="bill-meter-hdr bill-meter-hdr--elec">
                  <i class="fas fa-bolt"></i> Electricity
                  <span class="bill-meter-consumption">{{ fmtN(period.bill.electricity.consumption_litres) }} kWh</span>
                </div>
                <div class="bill-grid">
                  <div class="bill-stat"><div class="bill-stat-label">Usage Charge</div><div class="bill-stat-val">R {{ fmtMoney(period.bill.electricity.usage_charge) }}</div></div>
                  <div class="bill-stat"><div class="bill-stat-label">VAT</div><div class="bill-stat-val">R {{ fmtMoney(period.bill.electricity.vat_amount) }}</div></div>
                  <div class="bill-stat"><div class="bill-stat-label">Electricity Subtotal</div><div class="bill-stat-val">R {{ fmtMoney(period.bill.electricity.usage_charge + period.bill.electricity.vat_amount) }}</div></div>
                </div>
                <div v-if="period.bill.electricity.tier_breakdown?.length" class="tier-section">
                  <div class="tier-label">Tier Breakdown</div>
                  <table class="data-table">
                    <thead><tr><th>Tier</th><th class="num">Units (kWh)</th><th class="num">Rate (R/kWh)</th><th class="num">Charge</th></tr></thead>
                    <tbody>
                      <tr v-for="(t, i) in period.bill.electricity.tier_breakdown" :key="i">
                        <td>Tier {{ i + 1 }}</td>
                        <td class="num">{{ fmtN(t.units_kl, 0) }}</td>
                        <td class="num">{{ t.rate }}</td>
                        <td class="num">R {{ fmtMoney(t.amount) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Fixed / generic charges -->
              <div v-if="period.bill.fixed_breakdown?.length" class="bill-meter-section">
                <div class="bill-meter-hdr bill-meter-hdr--generic">
                  <i class="fas fa-list-ul"></i> Fixed Charges
                </div>
                <div class="bill-grid">
                  <div v-for="(f, fi) in period.bill.fixed_breakdown" :key="fi" class="bill-stat">
                    <div class="bill-stat-label">{{ f.name }}</div>
                    <div class="bill-stat-val">R {{ fmtMoney(f.amount) }}</div>
                  </div>
                </div>
              </div>

              <!-- Adjustment b/f — detailed breakdown -->
              <div v-if="period.bill.adjustment_brought_forward" class="bill-meter-section bill-adj-section">
                <div class="bill-meter-hdr bill-meter-hdr--adj">
                  <i class="fas fa-exchange-alt"></i> Adjustment b/f
                  <span :class="['bill-meter-consumption', period.bill.adjustment_brought_forward > 0 ? 'val-shortfall' : 'val-surplus']">
                    {{ period.bill.adjustment_brought_forward > 0 ? '+' : '' }}R {{ fmtMoney(Math.abs(period.bill.adjustment_brought_forward)) }}
                  </span>
                </div>

                <!-- Per-period detail rows -->
                <template v-if="period.bill.adjustment_detail?.length">
                  <div v-for="(d, di) in period.bill.adjustment_detail" :key="di" class="adj-detail-row">
                    <div class="adj-detail-period">
                      <i class="fas fa-calendar-alt"></i>
                      Period {{ d.periodNum }} &nbsp;·&nbsp; {{ fmt(d.periodStart) }} → {{ fmt(d.periodEnd) }}
                    </div>
                    <div class="adj-detail-grid">
                      <div class="adj-cell">
                        <div class="adj-cell-label">Provisioned</div>
                        <div class="adj-cell-val">{{ fmtN(d.provisionedLitres) }} L</div>
                        <div class="adj-cell-sub">R {{ fmtMoney(d.provisionalR) }}</div>
                      </div>
                      <div class="adj-cell">
                        <div class="adj-cell-label">Actual</div>
                        <div class="adj-cell-val">{{ fmtN(d.actualLitres) }} L</div>
                        <div class="adj-cell-sub">R {{ fmtMoney(d.actualR) }}</div>
                      </div>
                      <div class="adj-cell">
                        <div class="adj-cell-label">Difference</div>
                        <div :class="['adj-cell-val', d.diffLitres >= 0 ? 'val-shortfall' : 'val-surplus']">
                          {{ d.diffLitres >= 0 ? '+' : '' }}{{ fmtN(d.diffLitres) }} L
                        </div>
                        <div :class="['adj-cell-sub', d.diffR >= 0 ? 'val-shortfall' : 'val-surplus']">
                          {{ d.diffR >= 0 ? '+' : '' }}R {{ fmtMoney(Math.abs(d.diffR)) }}
                        </div>
                      </div>
                    </div>
                    <div class="adj-reason">
                      <i class="fas fa-info-circle"></i> {{ d.reason }}
                    </div>
                  </div>
                </template>
              </div>

              <!-- Grand total -->
              <div class="bill-grand-total">
                <div class="bgt-row" v-if="period.bill.water">
                  <span>Water</span>
                  <span>R {{ fmtMoney(period.bill.water.usage_charge + period.bill.water.vat_amount) }}</span>
                </div>
                <div class="bgt-row" v-if="period.bill.electricity">
                  <span>Electricity</span>
                  <span>R {{ fmtMoney(period.bill.electricity.usage_charge + period.bill.electricity.vat_amount) }}</span>
                </div>
                <div class="bgt-row" v-if="period.bill.fixed_total">
                  <span>Fixed Charges</span>
                  <span>R {{ fmtMoney(period.bill.fixed_total) }}</span>
                </div>
                <div v-if="period.bill.adjustment_brought_forward" class="bgt-row">
                  <span>Adjustment b/f</span>
                  <span :class="period.bill.adjustment_brought_forward > 0 ? 'val-shortfall' : 'val-surplus'">
                    {{ period.bill.adjustment_brought_forward > 0 ? '+' : '' }}R {{ fmtMoney(Math.abs(period.bill.adjustment_brought_forward)) }}
                  </span>
                </div>
                <div class="bgt-total">
                  <span>TOTAL</span>
                  <span>R {{ fmtMoney(period.bill.grand_total) }}</span>
                </div>
              </div>
            </div><!-- /bill -->

          </template><!-- /expanded -->
        </div><!-- /period-block -->

        <!-- Add Period button (test mode only, shown when at least one meter initialized on last period) -->
        <button
          v-if="mode === 'test' && test.periods.length > 0 && (test.periods[test.periods.length-1].water !== null || test.periods[test.periods.length-1].electricity !== null)"
          class="btn-add-period-bottom"
          :disabled="test.periods[test.periods.length-1]?.calculating"
          @click="addPeriod"
        >
          <i v-if="test.periods[test.periods.length-1]?.calculating" class="fas fa-circle-notch fa-spin"></i>
          <i v-else class="fas fa-plus"></i>
          {{ test.periods[test.periods.length-1]?.calculating ? 'Calculating…' : 'Add Period' }}
        </button>

      </template><!-- /activePeriods -->

    </div>

    <!-- ══ ALARM MODAL (ALM-001: No Period Reading) ══ -->
    <Teleport to="body">
      <div v-if="alarmModal.show" class="alarm-overlay" @click.self="alarmModal.show = false">
        <div class="alarm-modal">
          <div class="alarm-modal-header">
            <div class="alarm-modal-title">
              <i class="fas fa-exclamation-triangle"></i>
              Meter Reading Alert
            </div>
            <button class="alarm-modal-close" @click="alarmModal.show = false">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="alarm-modal-body">
            <div v-for="(item, i) in alarmModal.items" :key="i" class="alarm-modal-item">
              <div :class="['alarm-modal-icon', item.meter === 'water' ? 'alarm-modal-icon--water' : 'alarm-modal-icon--elec']">
                <i :class="['fas', item.meter === 'water' ? 'fa-tint' : 'fa-bolt']"></i>
              </div>
              <div class="alarm-modal-text">
                <div class="alarm-modal-msg">{{ item.message }}</div>
                <div class="alarm-modal-sub">
                  <i class="fas fa-clock"></i> Last reading was <strong>{{ item.daysSince }} day{{ item.daysSince !== 1 ? 's' : '' }}</strong> ago
                  &nbsp;·&nbsp; <span class="alarm-ref">{{ item.ref }}</span>
                </div>
              </div>
            </div>
          </div>
          <div class="alarm-modal-footer">
            <button class="alarm-dismiss" @click="alarmModal.show = false">
              <i class="fas fa-check"></i> Dismiss
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </AdminLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import MeterInput  from '@/components/MeterInput.vue'
import ElecInput   from '@/components/ElecInput.vue'

// ── Alarm state (ALM-001) ─────────────────────────────────────────────────────
const alarmModal = ref({ show: false, items: [] })

const props = defineProps({
  users:           { type: Array,  default: () => [] },
  tariffTemplates: { type: Array,  default: () => [] },
  today:           { type: String, default: '' },
})

// ── Mode ──────────────────────────────────────────────────────────────────────
const mode = ref('test')
function setMode (m) { mode.value = m }

// ── kL ↔ Litres ──────────────────────────────────────────────────────────────
function klStrToLitres (klStr) {
  const v = parseFloat(klStr)
  return isNaN(v) ? 0 : Math.round(v * 1000)
}
function litresToKlStr (litres) {
  if (litres === null || litres === undefined) return '_ _'
  const kl    = litres / 1000
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
  periodStart:       '',
  periodEnd:         '',
  periodDays:        0,
  periods:           [],
  activeMeterTab:    'water',   // top-level tab: 'water' | 'electricity'
  currentDate:       props.today || localDateStr(new Date()),
  currentDateActive: false,
})

// Effective "today": uses the test-mode date override when active, otherwise the server date.
// Account mode always uses props.today (real server date).
const effectiveToday = computed(() => {
  if (mode.value === 'test' && test.value.currentDateActive && test.value.currentDate) {
    return test.value.currentDate
  }
  return props.today || localDateStr(new Date())
})

// Keep currentDate clamped when the feature is active. Only runs when currentDateActive
// so inactive current date does not play a role (avoids watcher ping-pong when new period start > today).
watch(
  () => [test.value.periods.length, test.value.currentDate, test.value.currentDateActive],
  () => {
    if (!test.value.currentDateActive) return
    const realToday   = props.today || localDateStr(new Date())
    const lastPeriod  = test.value.periods[test.value.periods.length - 1]
    const minDate     = lastPeriod?.start ?? test.value.periodStart ?? ''
    let clamped = test.value.currentDate
    if (minDate && clamped < minDate) clamped = minDate
    if (clamped > realToday)         clamped = realToday
    if (clamped !== test.value.currentDate) test.value.currentDate = clamped
  },
  { immediate: false }
)

// ── Date helpers ─────────────────────────────────────────────────────────────
function dateAddDays (dateStr, days) {
  const d = new Date(dateStr + 'T00:00:00')
  d.setDate(d.getDate() + days)
  return localDateStr(d)
}

// Read Day = period.end − 4 days  =  bill_day − 5 days within the period's close month.
// Returns status info used by the countdown strip and ALM-002.
function readDayStatus (period) {
  const readDay   = dateAddDays(period.end, -4)
  const today     = effectiveToday.value
  const todayDate = new Date(today + 'T00:00:00')
  const rdDate    = new Date(readDay + 'T00:00:00')
  const daysTo    = Math.floor((rdDate - todayDate) / 86_400_000)  // negative = overdue

  // Find the last actual reading in this period (any meter, test or account mode)
  let lastReadDate = null
  for (const mKey of ['water', 'electricity']) {
    const m = period[mKey]
    if (!m) continue
    const readings = m.readings ?? []
    for (const r of readings) {
      const d = r.date
      if (d && (!lastReadDate || d > lastReadDate)) lastReadDate = d
    }
  }
  // Fall back to opening date if no readings exist
  if (!lastReadDate) {
    const openDate = period.water?.openingDate ?? period.electricity?.openingDate ?? period.start
    lastReadDate = openDate
  }

  const lastReadDt  = new Date(lastReadDate + 'T00:00:00')
  const daysSinceLast = Math.floor((todayDate - lastReadDt) / 86_400_000)

  // Countdown window: show strip from 5 days before read day until read day
  const countdownStart = dateAddDays(readDay, -5)
  const inWindow       = today >= countdownStart && today <= readDay

  return { readDay, daysTo, lastReadDate, daysSinceLast, inWindow }
}

// Tariff meter flags
const hasWater = computed(() => {
  if (!test.value.templateId) return true
  const t = props.tariffTemplates.find(t => String(t.id) === String(test.value.templateId))
  return t ? (t.is_water !== false && Number(t.is_water) !== 0) : true
})
const hasElec = computed(() => {
  if (!test.value.templateId) return false
  const t = props.tariffTemplates.find(t => String(t.id) === String(test.value.templateId))
  return t ? !!t.is_electricity : false
})

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
  const effDay      = Math.min(billDay, lastDayNext)
  const d = new Date(ny, nm - 1, effDay)
  d.setDate(d.getDate() - 1)
  test.value.periodEnd  = localDateStr(d)
  test.value.periodDays = blockDays(test.value.periodStart, test.value.periodEnd)
  // Auto-create first period shell as soon as tariff + dates are set
  if (test.value.templateId && test.value.periods.length === 0) {
    test.value.periods = [makeEmptyPeriod(0, test.value.periodStart, test.value.periodEnd, test.value.periodDays)]
  }
}

function onTestTemplateChange () {
  const t = props.tariffTemplates.find(t => String(t.id) === String(test.value.templateId))
  if (t?.billing_day) { test.value.billDay = t.billing_day }
  recomputeTestPeriod()
  // Reset periods when tariff changes
  test.value.periods = test.value.periodStart
    ? [makeEmptyPeriod(0, test.value.periodStart, test.value.periodEnd, test.value.periodDays)]
    : []
  test.value.activeMeterTab = 'water'
}

// ── Period helpers ────────────────────────────────────────────────────────────
function makeEmptyPeriod (index, start, end, days) {
  return {
    index,
    start,
    end,
    blockDays:   days,
    expanded:    true,
    water:       null,
    electricity: null,
    showBill:    false,
    bill:        null,
    calculating: false,
    calcError:   '',
    // Transient init state — cleared after confirmation
    _wInitReading: '0000.00',
    _wInitDate:    start,
    _eInitReading: '000000',
    _eInitDate:    start,
  }
}

function makeWaterMeter (litres, date) {
  return {
    openingLitres:              litres,
    openingDate:                date,
    readings:                   [],
    sectors:                    [],
    provisionalClosingLitres:   null,
    calculatedClosingLitres:    null,
    provisionalClosingSnapshot: null,
    provisionalBillR:           null,
    calculatedBillR:            null,
    adjustmentBroughtForward:   0,
    adjustmentDetail:           null,
    inheritedDailyUsage:        null,
    dailyUsage:                 null,
    stats:                      null,
  }
}

function makeElecMeter (kwh, date) {
  return {
    openingKwh:              kwh,
    openingDate:             date,
    readings:                [],
    sectors:                 [],
    provisionalClosingKwh:   null,
    calculatedClosingKwh:    null,
    dailyUsage:              null,
    adjustmentBroughtForward: 0,
    inheritedDailyUsage:     null,
    stats:                   null,
  }
}

// Check whether a meter type has been initialized in period[0..pi]
function isMeterInitialized (pi, meterType) {
  return test.value.periods.slice(0, pi + 1).some(p => p[meterType] !== null)
}

// Confirm initialization of a meter for a specific period
function confirmMeterInit (period, pi, meterType) {
  if (meterType === 'water') {
    const litres = klStrToLitres(period._wInitReading || '0000.00')
    const date   = period._wInitDate   || period.start
    period.water = {
      openingLitres:              litres,
      openingDate:                date,
      readings:                   [],
      sectors:                    [],
      provisionalClosingLitres:   null,
      calculatedClosingLitres:    null,
      provisionalClosingSnapshot: null,
      provisionalBillR:           null,
      calculatedBillR:            null,
      adjustmentBroughtForward:   0,
      adjustmentDetail:           null,
      inheritedDailyUsage:        null,
      dailyUsage:                 null,
      stats:                      null,
    }
    recomputePeriodWater(period, pi)
  } else {
    const kwh  = parseInt(period._eInitReading || '0') || 0
    const date = period._eInitDate || period.start
    period.electricity = {
      openingKwh:              kwh,
      openingDate:             date,
      readings:                [],
      sectors:                 [],
      provisionalClosingKwh:   null,
      calculatedClosingKwh:    null,
      dailyUsage:              null,
      adjustmentBroughtForward: 0,
      inheritedDailyUsage:     null,
      stats:                   null,
    }
    recomputePeriodElec(period, pi)
  }
}


function prevPeriod (pi) {
  return pi > 0 ? activePeriods.value[pi - 1] : null
}

// ── Period header click — expand/collapse + alarm check ───────────────────────
function onPeriodHeaderClick (period, pi) {
  const wasExpanded = period.expanded
  period.expanded = !period.expanded
  if (!wasExpanded && period.expanded) checkPeriodAlarms(period, pi)
}

// ALM-001 & ALM-002: Reading alarms
// ALM-001 — No Period Reading: no readings at all since period start > 5 days ago.
// ALM-002 — Reading Overdue: last reading (or period opening) was > 5 days ago.
// Both fire only on the current (last) period.
function checkPeriodAlarms (period, pi) {
  if (pi !== activePeriods.value.length - 1) return   // only current (last) period

  const today     = new Date(effectiveToday.value + 'T00:00:00')
  const threshold = 5
  const items     = []

  const periodStart          = new Date(period.start + 'T00:00:00')
  const daysSincePeriodStart = Math.floor((today - periodStart) / 86_400_000)

  // Only fire if the period has actually begun (start date is in the past)
  if (daysSincePeriodStart <= 0) return

  const { daysSinceLast } = readDayStatus(period)

  for (const [mKey, label] of [['water', 'water'], ['electricity', 'electricity']]) {
    const m = period[mKey]
    if (!m) continue

    if (m.readings.length === 0 && daysSincePeriodStart > threshold) {
      // ALM-001: no readings at all since period started
      items.push({
        meter:     mKey,
        message:   `No readings exist for this period — please read your ${label} meter.`,
        daysSince: daysSincePeriodStart,
        ref:       'ALM-001',
      })
    } else if (m.readings.length > 0 && daysSinceLast > threshold) {
      // ALM-002: readings exist but last one was > 5 days ago
      items.push({
        meter:     mKey,
        message:   `Last ${label} reading was ${daysSinceLast} days ago — your next reading is overdue.`,
        daysSince: daysSinceLast,
        ref:       'ALM-002',
      })
    }
  }

  if (items.length) alarmModal.value = { show: true, items }
}

async function addPeriod () {
  const periods = test.value.periods
  if (!periods.length) return

  // Auto-calculate the outgoing period before closing it
  const lastPi = periods.length - 1
  if (canCalcPeriod(periods[lastPi], lastPi) && !periods[lastPi].bill) {
    await calcPeriod(lastPi)
    periods[lastPi].showBill = true
  }

  periods[periods.length - 1].expanded = false

  const last     = periods[periods.length - 1]
  const newStart = nextDay(last.end)
  const [sy, sm] = newStart.split('-').map(Number)
  const tmpMonth = test.value.startMonth
  test.value.startMonth = `${sy}-${String(sm).padStart(2,'0')}`
  recomputeTestPeriod()

  const newP = makeEmptyPeriod(periods.length, test.value.periodStart, test.value.periodEnd, test.value.periodDays)

  // Carry forward water meter if already initialized
  if (last.water !== null) {
    const openingLitres = last.water.calculatedClosingLitres ?? last.water.provisionalClosingLitres ?? last.water.openingLitres
    newP.water = makeWaterMeter(openingLitres, newStart)
    newP.water.inheritedDailyUsage = last.water.dailyUsage
  }

  // Carry forward electricity meter if already initialized
  if (last.electricity !== null) {
    const openingKwh = last.electricity.calculatedClosingKwh ?? last.electricity.provisionalClosingKwh ?? last.electricity.openingKwh
    newP.electricity = makeElecMeter(openingKwh, newStart)
    newP.electricity.inheritedDailyUsage = last.electricity.dailyUsage
  }

  periods.push(newP)
  test.value.startMonth = tmpMonth
  if (newP.water)       recomputePeriodWater(newP, periods.length - 1)
  if (newP.electricity) recomputePeriodElec(newP, periods.length - 1)
}

function addReadingToPeriod (period) {
  const tab = activeMeter(period)
  const m   = period[tab]
  if (!m) return
  const lastDate = m.readings.length > 0
    ? m.readings[m.readings.length - 1].date
    : (m.openingDate || period.start)
  if (tab === 'water') {
    m.readings.push({ date: lastDate, klStr: '0000.00', litres: 0, error: '' })
  } else {
    m.readings.push({ date: lastDate, kwh: '000000', kwhInt: 0, error: '' })
  }
}

// ── Water recompute ───────────────────────────────────────────────────────────
function onWaterInput (period, r, pi) {
  r.litres = klStrToLitres(r.klStr || '0000.00')
  recomputePeriodWater(period, pi)
}

function recomputePeriodWater (period, pi) {
  const w = period.water
  if (!w) return
  if (pi !== undefined && pi > 0) reconcileStraddleWater(pi)

  w.readings.forEach(r => { r.error = '' })
  const valid = w.readings
    .filter(r => r.date && r.klStr && r.litres > 0)
    .sort((a, b) => a.date.localeCompare(b.date))

  if (valid.length === 0) {
    w.sectors = []; w.dailyUsage = w.inheritedDailyUsage ?? null
    // Close provisionally with opening (0 or inherited) — subject to reconciliation when 2nd reading is obtained
    w.provisionalClosingLitres = w.inheritedDailyUsage != null && w.inheritedDailyUsage > 0
      ? Math.round(w.openingLitres + w.inheritedDailyUsage * period.blockDays)
      : w.openingLitres
    return
  }

  let prevLitres = w.openingLitres
  for (const r of valid) {
    if (r.litres < prevLitres) { r.error = `Must be ≥ ${litresToKlStr(prevLitres)} kL` }
    else { prevLitres = r.litres }
  }
  const sequential = valid.filter(r => !r.error)
  if (!sequential.length) {
    w.sectors = []; w.dailyUsage = w.inheritedDailyUsage ?? null
    w.provisionalClosingLitres = w.inheritedDailyUsage != null && w.inheritedDailyUsage > 0
      ? Math.round(w.openingLitres + w.inheritedDailyUsage * period.blockDays)
      : w.openingLitres
    return
  }

  const sectorInput = [
    { reading_date: w.openingDate, reading_value: w.openingLitres },
    ...sequential.map(r => ({ reading_date: r.date, reading_value: r.litres })),
  ]
  w.sectors = buildSectors(sectorInput)
  const last       = sequential[sequential.length - 1]
  const usageSoFar = last.litres - w.openingLitres
  const days       = blockDays(w.openingDate, last.date)
  if (days > 0 && usageSoFar >= 0) {
    const rate = usageSoFar / days
    w.dailyUsage             = Math.round(rate)
    w.provisionalClosingLitres = Math.round(w.openingLitres + rate * period.blockDays)
    if (last.date === period.end) {
      w.calculatedClosingLitres  = last.litres
      w.provisionalClosingLitres = last.litres
    }
  }
  propagateMomentumWater(pi)
}

function propagateMomentumWater (fromPi) {
  for (let k = fromPi + 1; k < test.value.periods.length; k++) {
    const p = test.value.periods[k]
    const pw = p.water
    if (!pw) break
    if (pw.readings.filter(r => r.litres > 0).length > 0) break
    pw.inheritedDailyUsage = test.value.periods[k - 1].water?.dailyUsage ?? null
    if (pw.inheritedDailyUsage != null && pw.inheritedDailyUsage > 0) {
      pw.dailyUsage             = pw.inheritedDailyUsage
      pw.provisionalClosingLitres = Math.round(pw.openingLitres + pw.inheritedDailyUsage * p.blockDays)
    }
  }
}

// ── Straddle reconciliation (water only) ─────────────────────────────────────
async function reconcileStraddleWater (pi) {
  if (pi === 0) return
  const currP  = test.value.periods[pi]
  const currW  = currP.water
  if (!currW) return

  const currReadings = currW.readings
    .filter(r => r.date && r.litres > 0)
    .sort((a, b) => a.date.localeCompare(b.date))
  if (!currReadings.length) return

  const rightAnchor = currReadings[0]

  let leftPeriodIdx = -1
  let leftAnchor    = null
  for (let k = pi - 1; k >= 0; k--) {
    const p = test.value.periods[k]
    const pw = p.water
    if (!pw) continue
    const pReadings = pw.readings
      .filter(r => r.date && r.litres > 0)
      .sort((a, b) => a.date.localeCompare(b.date))
    if (pReadings.length > 0) {
      leftPeriodIdx = k; leftAnchor = pReadings[pReadings.length - 1]; break
    }
    if (k === 0) {
      leftPeriodIdx = 0; leftAnchor = { date: pw.openingDate, litres: pw.openingLitres }
    }
  }
  if (leftAnchor === null) return

  const totalUsage = rightAnchor.litres - leftAnchor.litres
  if (totalUsage < 0) return

  // Use full period block days per period (opening is anchor; allocation by period length).
  const slices = []
  for (let k = leftPeriodIdx; k <= pi - 1; k++) {
    const p = test.value.periods[k]
    const sliceDays = p.blockDays ?? blockDays(p.start, p.end)
    slices.push({ k, p, sliceDays })
  }
  const totalDays = slices.reduce((sum, s) => sum + s.sliceDays, 0)
  if (totalDays <= 0) return

  // Base allocation: floor for each (PD 4.0). Distribute remainder to max-length periods so equal block days get equal litres.
  const baseAlloc = slices.map(s => Math.floor(totalUsage * s.sliceDays / totalDays))
  let remainder = totalUsage - baseAlloc.reduce((a, b) => a + b, 0)
  const maxDays = Math.max(...slices.map(s => s.sliceDays))
  const maxIdx = slices.map((s, i) => (s.sliceDays === maxDays ? i : -1)).filter(i => i >= 0)
  let r = 0
  while (remainder > 0 && maxIdx.length > 0) {
    baseAlloc[maxIdx[r % maxIdx.length]]++
    remainder--
    r++
  }

  let prevClosing = leftAnchor.litres
  for (let idx = 0; idx < slices.length; idx++) {
    const { k, p } = slices[idx]
    const pw = p.water
    if (!pw) continue
    if (k > leftPeriodIdx) { pw.openingLitres = prevClosing; pw.openingDate = p.start }
    pw.provisionalClosingSnapshot = pw.provisionalClosingLitres
    const alloc = baseAlloc[idx]
    pw.calculatedClosingLitres = prevClosing + alloc
    prevClosing = pw.calculatedClosingLitres
  }

  currW.openingLitres = prevClosing
  currW.openingDate   = currP.start

  if (!test.value.templateId) return
  const tid = parseInt(test.value.templateId)
  const provisionalSlices = slices.filter(s => s.p.water?.provisionalClosingSnapshot != null)
  if (!provisionalSlices.length) { currW.adjustmentBroughtForward = 0; return }

  const details = await Promise.all(provisionalSlices.map(async ({ k, p }) => {
    const pw = p.water
    const provC = Math.max(0, (pw.provisionalClosingSnapshot ?? 0) - pw.openingLitres)
    const actC  = Math.max(0, pw.calculatedClosingLitres - pw.openingLitres)
    const [provRes, actRes] = await Promise.all([
      apiPost('/admin/calculator/compute-charge', { tariff_template_id: tid, consumption_litres: provC }),
      apiPost('/admin/calculator/compute-charge', { tariff_template_id: tid, consumption_litres: actC }),
    ])
    if (provRes.success && actRes.success) {
      pw.provisionalBillR = provRes.data.bill_total
      pw.calculatedBillR  = actRes.data.bill_total
      return {
        periodNum:         k + 1,
        periodStart:       p.start,
        periodEnd:         p.end,
        provisionedLitres: provC,
        actualLitres:      actC,
        diffLitres:        actC - provC,
        provisionalR:      provRes.data.bill_total,
        actualR:           actRes.data.bill_total,
        diffR:             actRes.data.bill_total - provRes.data.bill_total,
        reason:            'Meter reading absent or irregular',
      }
    }
    return null
  }))

  const validDetails = details.filter(Boolean)
  currW.adjustmentBroughtForward = validDetails.reduce((sum, d) => sum + d.diffR, 0)
  currW.adjustmentDetail         = validDetails.length ? validDetails : null
}

// ── Electricity recompute ─────────────────────────────────────────────────────
function onElecInput (period, r, pi) {
  r.kwhInt = parseInt(r.kwh) || 0
  recomputePeriodElec(period, pi)
}

function recomputePeriodElec (period, pi) {
  const e = period.electricity
  if (!e) return

  e.readings.forEach(r => { r.error = '' })
  const valid = e.readings
    .filter(r => r.date && r.kwh && r.kwhInt > 0)
    .sort((a, b) => a.date.localeCompare(b.date))

  if (valid.length === 0) {
    e.sectors = []; e.dailyUsage = e.inheritedDailyUsage ?? null
    e.provisionalClosingKwh = e.inheritedDailyUsage != null && e.inheritedDailyUsage > 0
      ? Math.round(e.openingKwh + e.inheritedDailyUsage * period.blockDays)
      : e.openingKwh
    return
  }

  let prevKwh = e.openingKwh
  for (const r of valid) {
    if (r.kwhInt < prevKwh) { r.error = `Must be ≥ ${fmtN(prevKwh)} kWh` }
    else { prevKwh = r.kwhInt }
  }
  const sequential = valid.filter(r => !r.error)
  if (!sequential.length) {
    e.sectors = []; e.dailyUsage = e.inheritedDailyUsage ?? null
    e.provisionalClosingKwh = e.inheritedDailyUsage != null && e.inheritedDailyUsage > 0
      ? Math.round(e.openingKwh + e.inheritedDailyUsage * period.blockDays)
      : e.openingKwh
    return
  }

  const sectorInput = [
    { reading_date: e.openingDate, reading_value: e.openingKwh },
    ...sequential.map(r => ({ reading_date: r.date, reading_value: r.kwhInt })),
  ]
  e.sectors = buildSectors(sectorInput)
  const last       = sequential[sequential.length - 1]
  const usageSoFar = last.kwhInt - e.openingKwh
  const days       = blockDays(e.openingDate, last.date)
  if (days > 0 && usageSoFar >= 0) {
    const rate = usageSoFar / days
    e.dailyUsage           = Math.round(rate)
    e.provisionalClosingKwh = Math.round(e.openingKwh + rate * period.blockDays)
    if (last.date === period.end) {
      e.calculatedClosingKwh  = last.kwhInt
      e.provisionalClosingKwh = last.kwhInt
    }
  }
}

// ── Adjustment helpers ────────────────────────────────────────────────────────
function waterAdjClass (period) {
  const w = period.water
  if (!w || w.calculatedClosingLitres == null || w.provisionalClosingLitres == null) return 'val-empty'
  const diff = w.calculatedClosingLitres - w.provisionalClosingLitres
  return diff > 0 ? 'val-shortfall' : diff < 0 ? 'val-surplus' : ''
}
function formatWaterAdj (period) {
  const w = period.water
  if (!w || w.calculatedClosingLitres == null || w.provisionalClosingLitres == null) return '_ _'
  const diff = w.calculatedClosingLitres - w.provisionalClosingLitres
  return (diff >= 0 ? '+' : '') + fmtN(diff) + ' L'
}
function elecAdjClass (period) {
  const e = period.electricity
  if (!e || e.calculatedClosingKwh == null || e.provisionalClosingKwh == null) return 'val-empty'
  const diff = e.calculatedClosingKwh - e.provisionalClosingKwh
  return diff > 0 ? 'val-shortfall' : diff < 0 ? 'val-surplus' : ''
}
function formatElecAdj (period) {
  const e = period.electricity
  if (!e || e.calculatedClosingKwh == null || e.provisionalClosingKwh == null) return '_ _'
  const diff = e.calculatedClosingKwh - e.provisionalClosingKwh
  return (diff >= 0 ? '+' : '') + fmtN(diff) + ' kWh'
}

// ── Calculate period bill (test + account unified) ────────────────────────────
function calcBlockReason (period, pi) {
  const tariffId = mode.value === 'test'
    ? test.value.templateId
    : ua.value.accountData?.tariff?.id
  if (!tariffId) {
    return mode.value === 'test'
      ? 'Select a tariff template in Setup first'
      : 'This account has no tariff template assigned'
  }
  if (!period.water && !period.electricity) return 'No meters on this period'
  const allInsufficient = [period.water, period.electricity]
    .filter(Boolean)
    .every(m => m.insufficientData)
  if (allInsufficient) return 'Insufficient readings — two readings minimum required'
  // Sequential Gate (PD Section 1.0): previous period must have a bill first
  if (pi > 0) {
    const prev = activePeriods.value[pi - 1]
    if (prev && !prev.bill) return `Period ${pi} must be calculated before this period`
  }
  return ''
}

function canCalcPeriod (period, pi) {
  return calcBlockReason(period, pi) === ''
}

async function calcPeriod (pi) {
  const period   = activePeriods.value[pi]
  const tariffId = mode.value === 'test'
    ? parseInt(test.value.templateId)
    : ua.value.accountData?.tariff?.id
  const accountId = mode.value === 'account'
    ? ua.value.accountData?.account?.id
    : null

  if (!tariffId) { period.calcError = 'No tariff selected.'; return }
  await computePeriodBill(period, tariffId, accountId)
}

async function computePeriodBill (period, tariffId, accountId = null) {
  period.calculating = true; period.calcError = ''; period.bill = null
  try {
    const bill = {
      water:                     null,
      electricity:               null,
      fixed_breakdown:           [],
      fixed_total:               0,
      adjustment_brought_forward: null,
      adjustment_detail:         null,
      grand_total:               0,
    }

    // ── Water ────────────────────────────────────────────────────────────────
    if (period.water) {
      const w     = period.water
      const openV = w.openingLitres ?? 0

      // Actual consumption to date (last reading - opening).
      // Readings use r.litres in test mode and r.value in account mode — read whichever is present.
      const lastReading     = w.readings
        .filter(r => (r.litres ?? r.value ?? 0) > 0)
        .sort((a, b) => a.date.localeCompare(b.date)).pop()
      const lastValue       = lastReading ? (lastReading.litres ?? lastReading.value ?? openV) : openV
      const currConsumption = Math.max(0, Math.round(lastValue - openV))

      // Bill consumption: best available closing (calculated → provisional → last reading)
      const billClosing     = w.calculatedClosingLitres ?? w.provisionalClosingLitres ?? lastValue
      const billConsumption = Math.max(0, Math.round(billClosing - openV))

      // Projected: provisional closing extrapolated to full period
      const projClosing     = w.provisionalClosingLitres ?? billClosing
      const projConsumption = Math.max(0, Math.round(projClosing - openV))
      const adj             = w.adjustmentBroughtForward ?? 0

      // Three calls: bill (with fixed), projected, current-to-date (skip if same as bill)
      const needCurrCall = currConsumption !== billConsumption
      const apiCalls = [
        apiPost('/admin/calculator/compute-charge', {
          tariff_template_id: tariffId,
          consumption_litres: billConsumption,
          consumption_unit:   'litres',
          include_fixed:      true,
          account_id:         accountId,
        }),
        apiPost('/admin/calculator/compute-charge', {
          tariff_template_id: tariffId,
          consumption_litres: projConsumption,
          consumption_unit:   'litres',
          include_fixed:      false,
        }),
        needCurrCall
          ? apiPost('/admin/calculator/compute-charge', {
              tariff_template_id: tariffId,
              consumption_litres: currConsumption,
              consumption_unit:   'litres',
              include_fixed:      false,
            })
          : Promise.resolve(null),
      ]
      const [billRes, projRes, currRes] = await Promise.all(apiCalls)

      if (billRes.success) {
        bill.water           = billRes.data
        bill.fixed_breakdown = billRes.data.fixed_breakdown || []
        bill.fixed_total     = billRes.data.fixed_total || 0
        const currData = (needCurrCall && currRes?.success) ? currRes.data : billRes.data
        w.stats = {
          currentR:   currData.usage_charge + currData.vat_amount,
          projectedR: (projRes.success ? projRes.data.bill_total : billRes.data.bill_total) + adj,
        }
      } else {
        period.calcError = `Water API error: ${billRes.message || 'unknown'}`
      }
      bill.adjustment_brought_forward = adj || null
      bill.adjustment_detail          = w.adjustmentDetail ?? null
    }

    // ── Electricity ──────────────────────────────────────────────────────────
    if (period.electricity) {
      const e     = period.electricity
      const openV = e.openingKwh ?? 0

      // Same normalization as water: read r.kwhInt (test) or r.value (account) whichever is present.
      const lastReadingE  = e.readings
        .filter(r => (r.kwhInt ?? r.value ?? 0) > 0)
        .sort((a, b) => a.date.localeCompare(b.date)).pop()
      const lastValueE    = lastReadingE ? (lastReadingE.kwhInt ?? lastReadingE.value ?? openV) : openV
      const currConsE     = Math.max(0, Math.round(lastValueE - openV))

      const billClosingE  = e.calculatedClosingKwh ?? e.provisionalClosingKwh ?? lastValueE
      const consumption   = Math.max(0, Math.round(billClosingE - openV))
      const projClosingE  = e.provisionalClosingKwh ?? billClosingE
      const projCons      = Math.max(0, Math.round(projClosingE - openV))

      const needCurrCallE = currConsE !== consumption
      const apiCallsE = [
        apiPost('/admin/calculator/compute-charge', {
          tariff_template_id: tariffId,
          consumption_litres: consumption,
          consumption_unit:   'kwh',
          include_fixed:      false,
        }),
        apiPost('/admin/calculator/compute-charge', {
          tariff_template_id: tariffId,
          consumption_litres: projCons,
          consumption_unit:   'kwh',
          include_fixed:      false,
        }),
        needCurrCallE
          ? apiPost('/admin/calculator/compute-charge', {
              tariff_template_id: tariffId,
              consumption_litres: currConsE,
              consumption_unit:   'kwh',
              include_fixed:      false,
            })
          : Promise.resolve(null),
      ]
      const [billResE, projResE, currResE] = await Promise.all(apiCallsE)

      if (billResE.success) {
        bill.electricity = billResE.data
        const currDataE = (needCurrCallE && currResE?.success) ? currResE.data : billResE.data
        e.stats = {
          currentR:   currDataE.usage_charge + currDataE.vat_amount,
          projectedR: projResE.success ? projResE.data.usage_charge + projResE.data.vat_amount : billResE.data.usage_charge + billResE.data.vat_amount,
        }
      }
    }

    // Grand total
    const waterTotal  = bill.water        ? (bill.water.usage_charge + bill.water.vat_amount) : 0
    const elecTotal   = bill.electricity  ? (bill.electricity.usage_charge + bill.electricity.vat_amount) : 0
    const fixedTotal  = bill.fixed_total  || 0
    const adj         = bill.adjustment_brought_forward || 0
    bill.grand_total  = round2(waterTotal + elecTotal + fixedTotal + adj)

    period.bill = bill
  } catch (e) { period.calcError = e.message }
  finally     { period.calculating = false }
}

// ══════════════════════════════════════════════════════════
// USER + ACCOUNT MODE
// ══════════════════════════════════════════════════════════
const ua = ref({
  userId:         '',
  accountId:      '',
  loading:        false,
  accountData:    null,
  periods:        [],
  activeMeterTab: 'water',   // top-level tab: 'water' | 'electricity'
})

const filteredAccounts = computed(() =>
  props.users.find(u => String(u.id) === String(ua.value.userId))?.accounts || []
)

function onUserChange () {
  ua.value.accountId = ''; ua.value.accountData = null; ua.value.periods = []
}

async function loadAccount () {
  if (!ua.value.accountId) return
  ua.value.loading = true; ua.value.accountData = null; ua.value.periods = []; ua.value.activeMeterTab = 'water'
  try {
    const res = await apiFetch(`/admin/calculator/account/${ua.value.accountId}`)
    if (res.success) {
      ua.value.accountData = res.data
      ua.value.periods     = reconstructPeriods(res.data)
    }
  } catch { /* ignore */ }
  finally { ua.value.loading = false }
}

function uaPeriodStart (dateStr, billDay) {
  const d      = new Date(dateStr + 'T00:00:00')
  const yr     = d.getFullYear(); const mo = d.getMonth()
  const dInMo  = new Date(yr, mo + 1, 0).getDate()
  const cand   = new Date(yr, mo, Math.min(billDay, dInMo))
  if (cand <= d) return localDateStr(cand)
  const pMo = mo === 0 ? 11 : mo - 1; const pYr = mo === 0 ? yr - 1 : yr
  return localDateStr(new Date(pYr, pMo, Math.min(billDay, new Date(pYr, pMo + 1, 0).getDate())))
}
function uaPeriodEnd (startStr, billDay) {
  const s  = new Date(startStr + 'T00:00:00')
  const nMo = (s.getMonth() + 1) % 12
  const nYr = s.getMonth() === 11 ? s.getFullYear() + 1 : s.getFullYear()
  const nxS = new Date(nYr, nMo, Math.min(billDay, new Date(nYr, nMo + 1, 0).getDate()))
  nxS.setDate(nxS.getDate() - 1)
  return localDateStr(nxS)
}

function reconstructPeriods (data) {
  const { account, meters } = data
  const billDay = account.bill_day || 1
  const today   = props.today || localDateStr(new Date())

  // Find the earliest initialization reading across all meters
  let earliest = null
  for (const m of meters) {
    if (m.readings.length > 0) {
      if (!earliest || m.readings[0].date < earliest) earliest = m.readings[0].date
    }
  }
  if (!earliest) return []

  const firstStart = uaPeriodStart(earliest, billDay)
  const periods    = []
  let   curStart   = firstStart

  // Per-meter chained state: opening value/date/dailyUsage carries forward from
  // each period's close into the next period's open. This mirrors exactly how
  // test mode chains periods — no DB lookup for openings after Period 1.
  const meterState = {}
  for (const m of meters) {
    const initReading = m.readings.filter(r => r.date <= firstStart).slice(-1)[0] ?? null
    meterState[m.id] = initReading
      ? { value: initReading.value, date: initReading.date, dailyUsage: null }
      : null
  }

  while (curStart <= today) {
    const end       = uaPeriodEnd(curStart, billDay)
    const nextStart = nextDay(end)

    const period = {
      start: curStart, end, blockDays: blockDays(curStart, end),
      expanded: false,
      water: null, electricity: null,
      showBill: false,
      calculating: false, calcError: '', bill: null,
    }

    for (const m of meters) {
      const opening = meterState[m.id]
      if (!opening) continue

      // Intra-period readings: date falls within [curStart, end].
      // For the very first period, exclude the initialization reading itself
      // (it is the opening anchor, not a consumption event).
      const isFirstPeriod = curStart === firstStart
      const periodReadings = m.readings.filter(r => {
        if (isFirstPeriod && r.date <= opening.date) return false
        return r.date >= curStart && r.date <= end
      })

      let sectors          = []
      let provisionalClosing = null
      let calculatedClosing  = null
      let dailyUsage         = opening.dailyUsage   // inherit momentum from previous period
      let insufficientData   = false

      if (periodReadings.length > 0) {
        // Build sectors using the chained opening as the anchor point.
        // Opening date may predate curStart when a prior period had no readings —
        // the sector will correctly span across that gap.
        const sectorInput = [
          { reading_date: opening.date, reading_value: opening.value },
          ...periodReadings.map(r => ({ reading_date: r.date, reading_value: r.value })),
        ]
        sectors = buildSectors(sectorInput)
        const last     = periodReadings[periodReadings.length - 1]
        const usage    = last.value - opening.value
        const daysSpan = blockDays(opening.date, last.date)
        if (daysSpan > 0 && usage >= 0) {
          dailyUsage         = Math.round(usage / daysSpan)
          provisionalClosing = Math.round(opening.value + dailyUsage * period.blockDays)
          if (last.date === end) {
            calculatedClosing  = last.value
            provisionalClosing = last.value
          }
        }
      } else if (dailyUsage != null && dailyUsage > 0) {
        // No readings this period — project forward using inherited momentum
        provisionalClosing = Math.round(opening.value + dailyUsage * period.blockDays)
      } else {
        // No readings and no momentum: calculation is impossible.
        // Requires at least two readings to establish a consumption rate.
        insufficientData = true
      }

      // Advance the chained state for the next period.
      // Only advance the anchor DATE when a closing was computed — if no close
      // exists (insufficientData), preserve the original opening date so the next
      // period that receives a reading measures the correct span from the real anchor.
      meterState[m.id] = {
        value:      provisionalClosing ?? opening.value,
        date:       provisionalClosing != null ? end : opening.date,
        dailyUsage,
      }

      if (m.meter_type === 'water') {
        period.water = {
          openingLitres:               opening.value,
          openingDate:                 opening.date,
          readings:                    periodReadings,
          sectors,
          provisionalClosingLitres:    provisionalClosing,
          calculatedClosingLitres:     calculatedClosing,
          provisionalClosingSnapshot:  null,
          provisionalBillR:            null,
          calculatedBillR:             null,
          adjustmentBroughtForward:    0,
          adjustmentDetail:            null,
          inheritedDailyUsage:         null,
          dailyUsage,
          insufficientData,
          stats:                       null,
        }
      } else if (m.meter_type === 'electricity') {
        period.electricity = {
          openingKwh:              opening.value,
          openingDate:             opening.date,
          readings:                periodReadings,
          sectors,
          provisionalClosingKwh:   provisionalClosing,
          calculatedClosingKwh:    calculatedClosing,
          dailyUsage,
          insufficientData,
          adjustmentBroughtForward: 0,
          stats:                   null,
        }
      }
    }

    if (period.water || period.electricity) periods.push(period)
    curStart = nextStart
  }

  if (periods.length) periods[periods.length - 1].expanded = true
  return periods
}

// ── Shared helpers ────────────────────────────────────────────────────────────
const activePeriods = computed(() =>
  mode.value === 'test' ? test.value.periods : ua.value.periods
)

function activeMeter (_period) {
  if (mode.value === 'test') return test.value.activeMeterTab || 'water'
  return ua.value.activeMeterTab || 'water'
}

function buildSectors (readings) {
  const sorted = [...readings].sort((a, b) => a.reading_date.localeCompare(b.reading_date))
  const sectors = []
  for (let i = 0; i < sorted.length - 1; i++) {
    const r1     = sorted[i]; const r2 = sorted[i + 1]
    const sStart = i === 0 ? r1.reading_date : nextDay(r1.reading_date)
    const bd     = blockDays(sStart, r2.reading_date)
    const usage  = Math.max(0, Math.round(Number(r2.reading_value) - Number(r1.reading_value)))
    sectors.push({
      start: sStart, end: r2.reading_date,
      start_reading: Number(r1.reading_value), end_reading: Number(r2.reading_value),
      total_usage: usage, block_days: bd, daily_avg: bd > 0 ? Math.round(usage / bd * 10) / 10 : 0,
    })
  }
  return sectors
}

// ── Date helpers ──────────────────────────────────────────────────────────────
function blockDays (start, end) {
  return Math.round((new Date(end+'T00:00:00') - new Date(start+'T00:00:00')) / 86400000) + 1
}
function nextDay (date) {
  const d = new Date(date+'T00:00:00'); d.setDate(d.getDate()+1); return localDateStr(d)
}
function fmt (d) {
  if (!d) return '—'
  return new Date(d+'T00:00:00').toLocaleDateString('en-ZA', { day: 'numeric', month: 'long', year: 'numeric' })
}
function fmtN (n, dp = 0) {
  const v = parseFloat(n ?? 0)
  return isNaN(v) ? '0' : v.toLocaleString('en-ZA', { minimumFractionDigits: dp, maximumFractionDigits: dp })
}
function fmtKl (val, dp = 3) {
  return parseFloat(val ?? 0).toFixed(dp)
}
function fmtMoney (n) {
  const v = parseFloat(String(n ?? '0').replace(/,/g, ''))
  return isNaN(v) ? '0.00' : v.toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
function round2 (n) { return Math.round((n + Number.EPSILON) * 100) / 100 }

// ── HTTP ──────────────────────────────────────────────────────────────────────
async function apiFetch (url) { return (await window.axios.get(url)).data }
async function apiPost  (url, data) {
  try { return (await window.axios.post(url, data)).data }
  catch (e) { return e.response?.data || { success: false, message: e.message } }
}

// Init
recomputeTestPeriod()
</script>

<style scoped>
/* ── Base ──────────────────────────────────────────────────────────────────── */
.cp {
  max-width: 960px; margin: 0 auto; padding: 1.5rem 1.5rem 5rem;
  font-family: 'Nunito', sans-serif; display: flex; flex-direction: column; gap: 1.25rem; color: #1a2b3c;
}

/* ── Header ─────────────────────────────────────────────────────────────────── */
.cp-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; padding-bottom: 0.75rem; border-bottom: 2px solid #B0D3DF; }
.cp-title  { font-size: 1.5rem; font-weight: 800; color: #1a2b3c; }
.cp-sub    { font-size: 0.72rem; color: #a0aec0; margin-top: 1px; letter-spacing: 0.04em; }
.cp-tabs   { display: flex; gap: 0; border: 2px solid #B0D3DF; border-radius: 8px; overflow: hidden; }
.cp-tab    { padding: 0.45rem 1.4rem; background: #fff; border: none; font-size: 0.85rem; font-weight: 700; color: #718096; cursor: pointer; transition: all 0.15s; }
.cp-tab + .cp-tab { border-left: 2px solid #B0D3DF; }
.cp-tab--on { background: #2d3748; color: #fff; }

/* ── Cards ──────────────────────────────────────────────────────────────────── */
.card { background: #fff; border-radius: 10px; padding: 1.25rem 1.5rem; box-shadow: 0 2px 8px rgba(50,148,184,.10), 0 1px 3px rgba(0,0,0,.05); border: 1px solid #e8f4f8; }
.section-label { font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; color: #3294B8; margin-bottom: 1rem; }

/* ── Form fields ────────────────────────────────────────────────────────────── */
.fields-row { display: flex; gap: 1rem; flex-wrap: wrap; }
.field      { display: flex; flex-direction: column; min-width: 130px; flex: 1; }
.field--grow { flex: 2; }
.f-label    { font-size: 0.72rem; font-weight: 700; color: #718096; margin-bottom: 0.3rem; }
.f-input    { padding: 0.5rem 0.75rem; border: 1.5px solid #B0D3DF; border-radius: 7px; font-size: 0.88rem; font-family: 'Nunito', sans-serif; color: #1a2b3c; background: #fff; box-shadow: 0 1px 3px rgba(50,148,184,.08); transition: border-color 0.15s, box-shadow 0.15s; width: 100%; box-sizing: border-box; }
.f-input:focus   { border-color: #3294B8; box-shadow: 0 0 0 3px rgba(50,148,184,.12); outline: none; }
.f-input:disabled { background: #f7fafb; cursor: not-allowed; color: #a0aec0; }

/* ── Period chips ───────────────────────────────────────────────────────────── */
.period-chip-row { margin-top: 0.85rem; display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
.chip-period { display: inline-block; padding: 0.25rem 0.75rem; background: #ebf7fc; color: #2b7fa3; border: 1px solid #B0D3DF; border-radius: 20px; font-size: 0.78rem; font-weight: 700; }
.chip-days   { display: inline-block; padding: 0.2rem 0.55rem; background: #f7fafb; color: #718096; border: 1px solid #e2e8f0; border-radius: 20px; font-size: 0.74rem; font-weight: 600; }
.chip-open   { display: inline-block; padding: 0.15rem 0.55rem; background: #f0fff4; color: #276749; border: 1px solid #9ae6b4; border-radius: 4px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; margin-left: 0.4rem; }
.chip-meter  { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.2rem 0.65rem; border-radius: 20px; font-size: 0.74rem; font-weight: 700; }
.chip-meter--water { background: #ebf7fc; color: #2b7fa3; border: 1px solid #B0D3DF; }
.chip-meter--elec  { background: #fffbeb; color: #b7791f; border: 1px solid #fef08a; }

/* ── Meter initialization form (inside period block) ────────────────────────── */
.meter-init-form        { padding: 1.1rem 1.25rem; border-top: 1px solid #e8f4f8; }
.meter-init-form--water { background: #f0f8fb; }
.meter-init-form--elec  { background: #fffbeb; border-top-color: #fef3c7; }
.init-form-title {
  font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em;
  margin-bottom: 0.85rem; display: flex; align-items: center; gap: 0.4rem;
}
.meter-init-form--water .init-form-title { color: #3294B8; }
.meter-init-form--elec  .init-form-title { color: #b7791f; }
.init-form-row { display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap; }
.btn-init {
  padding: 0.5rem 1.25rem; border: none; border-radius: 7px;
  font-size: 0.88rem; font-weight: 700; cursor: pointer; transition: all 0.15s;
  display: flex; align-items: center; gap: 0.4rem;
}
.btn-init--water { background: #3294B8; color: #fff; }
.btn-init--water:hover { background: #2a7a9e; }
.btn-init--elec  { background: #b7791f; color: #fff; }
.btn-init--elec:hover  { background: #975a16; }

/* ── View Bill button ───────────────────────────────────────────────────────── */
.btn-view-bill {
  padding: 0.45rem 1.5rem; background: #1a2b3c; color: #B0D3DF; border: 2px solid #2d3748;
  border-radius: 7px; font-size: 0.88rem; font-weight: 700; cursor: pointer;
  transition: all 0.15s; display: flex; align-items: center; gap: 0.45rem;
}
.btn-view-bill:hover:not(:disabled) { background: #2d3748; color: #fff; border-color: #3294B8; }
.btn-view-bill:disabled { opacity: 0.45; cursor: not-allowed; }

/* ── Account mode meta ──────────────────────────────────────────────────────── */
.ua-loading { margin-top: 0.75rem; font-size: 0.84rem; color: #3294B8; display: flex; align-items: center; gap: 0.5rem; }
.ua-meta    { display: flex; gap: 1.25rem; flex-wrap: wrap; margin-top: 0.85rem; padding-top: 0.75rem; border-top: 1px solid #e8f4f8; }
.ua-meta-item { display: flex; flex-direction: column; gap: 0.15rem; }
.ua-meta-label { font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: #3294B8; }
.ua-meta-val   { font-size: 0.88rem; font-weight: 700; color: #1a2b3c; }

/* ── Meter tabs ─────────────────────────────────────────────────────────────── */
.meter-tabs {
  display: flex; border-bottom: 2px solid #e8f4f8; margin: 0;
}
.meter-tab {
  padding: 0.55rem 1.5rem; background: none; border: none; font-size: 0.84rem; font-weight: 700;
  color: #718096; cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -2px;
  display: flex; align-items: center; gap: 0.4rem; transition: color .15s, border-color .15s;
}
.meter-tab:hover { color: #3294B8; }
.meter-tab--on   { color: #3294B8; border-bottom-color: #3294B8; }
.meter-tab--elec:hover { color: #b7791f; }
.meter-tab-elec--on    { color: #b7791f !important; border-bottom-color: #d69e2e !important; }

/* ── Top-level meter tabs (Water / Electricity) ─────────────────────────────── */
.top-meter-tabs {
  display: flex; gap: 0.5rem;
  background: #fff; border-radius: 10px;
  padding: 0.5rem 1rem;
  box-shadow: 0 2px 8px rgba(50,148,184,.10), 0 1px 3px rgba(0,0,0,.05);
  border: 1px solid #e8f4f8;
}
.top-meter-tab {
  flex: 1; padding: 0.65rem 1rem; border: 2px solid #e8f4f8; border-radius: 8px;
  background: #f7fbfd; font-size: 0.9rem; font-weight: 700; color: #718096;
  cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem;
  transition: all .15s;
}
.top-meter-tab:hover { background: #e8f4f8; }
.top-meter-tab--water.top-meter-tab--on {
  background: #e8f4f8; color: #3294B8; border-color: #3294B8;
}
.top-meter-tab--elec.top-meter-tab-elec--on {
  background: #fefcbf; color: #b7791f; border-color: #d69e2e;
}

/* ── Period blocks ──────────────────────────────────────────────────────────── */
.period-block { background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(50,148,184,.10), 0 1px 3px rgba(0,0,0,.05); border: 1px solid #e8f4f8; border-left: 4px solid #3294B8; display: flex; flex-direction: column; overflow: hidden; }
.period-block--closed   { border-left-color: #B0D3DF; }
.period-block--expanded { gap: 0; }

/* ── Period header ──────────────────────────────────────────────────────────── */
.period-hdr { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.75rem; padding: 0.9rem 1.25rem; cursor: pointer; user-select: none; transition: background 0.12s; background: #fff; }
.period-hdr:hover { background: #f7fbfd; }
.period-block--collapsed .period-hdr { padding-bottom: 0.85rem; }
.period-hdr-left   { display: flex; flex-direction: column; gap: 0.3rem; flex: 1; }
.period-hdr-right  { display: flex; align-items: center; gap: 0.75rem; flex-shrink: 0; padding-top: 0.1rem; }
.period-hdr-title  { font-size: 0.95rem; font-weight: 800; color: #1a2b3c; display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap; }
.period-hdr-dates  { font-size: 0.8rem; font-weight: 600; color: #718096; display: inline-flex; align-items: center; gap: 0.25rem; flex-wrap: wrap; }
.period-collapsed-summary { display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap; font-size: 0.78rem; color: #718096; }
.cs-sep   { color: #B0D3DF; font-weight: 700; }
.cs-label { color: #a0aec0; }
.cs-val   { font-weight: 700; color: #2d3748; font-family: 'Courier New', monospace; }
.cs-val--bill { color: #276749; font-family: 'Nunito', sans-serif; }
.cs-item  { display: inline-flex; align-items: center; gap: 0.25rem; }
.period-chevron { color: #B0D3DF; font-size: 0.8rem; transition: color 0.15s; }
.period-hdr:hover .period-chevron { color: #3294B8; }

/* ── Opening row ────────────────────────────────────────────────────────────── */
.period-opening-row { display: flex; align-items: center; gap: 0.6rem; padding: 0.45rem 1.25rem; background: #f0f8fb; border-top: 1px solid #e8f4f8; border-bottom: 1px solid #e8f4f8; flex-wrap: wrap; }
.period-opening-row--elec { background: #fffbeb; border-top-color: #fef3c7; border-bottom-color: #fef3c7; }
.por-label { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.07em; color: #3294B8; }
.por-label--elec { color: #b7791f; }
.por-val   { font-family: 'Courier New', monospace; font-size: 0.88rem; font-weight: 700; color: #1a2b3c; }
.por-was   { font-size: 0.72rem; color: #a0aec0; font-style: italic; margin-left: 0.3rem; }

/* ── Stats bar ──────────────────────────────────────────────────────────────── */
.stats-bar       { display: grid; grid-template-columns: repeat(3, 1fr); background: #2d6b8a; }
.stats-bar--elec { background: #744210; }
.stat-cell       { padding: 0.75rem 1rem; text-align: center; border-right: 1px solid rgba(255,255,255,.5); }
.stat-cell:last-child { border-right: none; }
.stat-label { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: rgba(255,255,255,0.8); }
.stat-val   { font-size: 1rem; font-weight: 800; color: #fff; margin-top: 3px; }

/* ── Adjustment notice ──────────────────────────────────────────────────────── */
.adjustment-notice { display: flex; align-items: center; gap: 0.4rem; padding: 0.45rem 1.25rem; font-size: 0.8rem; flex-wrap: wrap; }
.adj-shortfall { background: #fff5f5; color: #c53030; border-top: 1px solid #fed7d7; }
.adj-surplus   { background: #f0fff4; color: #276749; border-top: 1px solid #c6f6d5; }

/* ── Readings ───────────────────────────────────────────────────────────────── */
.readings-section { display: flex; flex-direction: column; gap: 0.5rem; padding: 0.75rem 1.25rem 0.5rem; }
.readings-header  { display: flex; align-items: baseline; gap: 0.5rem; }
.readings-header-label { font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #3294B8; }
.readings-header-hint  { font-size: 0.7rem; color: #a0aec0; }
.reading-row { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; padding: 0.35rem 0; border-bottom: 1px dashed #e8f4f8; }
.reading-row:last-of-type { border-bottom: none; }
.r-date  { width: 152px; flex: 0 0 152px; }
.r-litres, .r-litres-display { font-size: 0.76rem; color: #a0aec0; white-space: nowrap; }
.r-date-display { font-size: 0.84rem; font-weight: 700; color: #2d3748; min-width: 150px; }
.r-kl-display   { font-family: 'Courier New', monospace; font-size: 0.88rem; font-weight: 700; color: #3294B8; }
.btn-rm { background: none; border: none; color: #e53e3e; font-size: 0.88rem; cursor: pointer; padding: 0.1rem 0.3rem; }
.empty-readings { font-size: 0.82rem; color: #a0aec0; font-style: italic; padding: 0.25rem 0; }
.insufficient-data-notice { display: flex; align-items: flex-start; gap: 0.6rem; background: #fffbeb; border: 1px solid #f6d860; border-radius: 8px; padding: 0.75rem 1rem; margin: 0.75rem 0; color: #92400e; font-size: 0.88rem; font-weight: 500; }
.insufficient-data-notice .fas { color: #d97706; margin-top: 0.1rem; flex-shrink: 0; }

/* Current Date override row (test setup) */
.current-date-row { display: flex; align-items: center; gap: 0.75rem; margin-top: 0.75rem; flex-wrap: wrap; }
.current-date-label { font-size: 0.82rem; font-weight: 600; color: #4a5568; white-space: nowrap; }
.current-date-input { width: 160px !important; }
.btn-date-toggle { display: flex; align-items: center; gap: 0.4rem; padding: 0.35rem 1rem; border: none; border-radius: 20px; font-size: 0.82rem; font-weight: 700; cursor: pointer; transition: background 0.15s; }
.btn-date-toggle--off { background: #e2e8f0; color: #718096; }
.btn-date-toggle--on  { background: #3294B8; color: #fff; }
.current-date-hint { font-size: 0.8rem; color: #2d6b8a; }

/* Read Day countdown strip */
.read-day-strip { display: flex; align-items: center; gap: 0.6rem; padding: 0.55rem 1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 500; margin: 0.5rem 0; }
.read-day-strip .fas { flex-shrink: 0; font-size: 1rem; }
.rds-date { font-weight: 400; opacity: 0.75; margin-left: 0.3rem; }
.read-day-strip--soon    { background: #fffbeb; border: 1px solid #f6d860; color: #92400e; }
.read-day-strip--soon .fas { color: #d97706; }
.read-day-strip--today   { background: #fff5f5; border: 1px solid #fc8181; color: #742a2a; }
.read-day-strip--today .fas { color: #e53e3e; }
.read-day-strip--overdue { background: #fff5f5; border: 1px solid #fc8181; color: #742a2a; }
.read-day-strip--overdue .fas { color: #e53e3e; }
.reading-row--error { background: #fff5f5; border-radius: 6px; padding-left: 0.4rem; margin-left: -0.4rem; }
.r-seq-error { font-size: 0.74rem; color: #c53030; display: flex; align-items: center; gap: 0.3rem; white-space: nowrap; font-weight: 600; }

/* ── Date input with calendar icon ─────────────────────────────────────────── */
.date-wrap  { position: relative; display: flex; align-items: center; }
.date-icon  { position: absolute; left: 0.65rem; color: #3294B8; font-size: 0.78rem; pointer-events: none; z-index: 1; }
.date-wrap .f-input { padding-left: 2rem; }

/* ── Sectors ────────────────────────────────────────────────────────────────── */
.sectors-section { display: flex; flex-direction: column; gap: 0.5rem; padding: 0 1.25rem 0.75rem; }
.sectors-label   { font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #718096; padding-top: 0.25rem; }

/* ── Closing bar ────────────────────────────────────────────────────────────── */
.closing-bar { display: grid; grid-template-columns: repeat(3, 1fr); background: #f0f4f6; border-top: 1px solid #e2e8f0; margin-top: 0.75rem; }
.closing-bar--elec    { background: #2d2d0a; }
.closing-bar--resolved { background: #eaf6f0; border-top-color: #9ae6b4; }
.closing-bar--elec.closing-bar--resolved { background: #1a3a1a; }
.closing-cell { padding: 0.65rem 1rem; text-align: center; border-right: 1px solid rgba(255,255,255,.3); }
.closing-cell:last-child { border-right: none; }
.closing-cell-label { font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: #718096; margin-bottom: 0.25rem; }
.closing-bar--elec .closing-cell-label { color: #a0aec0; }
.closing-cell-val { font-size: 0.96rem; font-weight: 800; font-family: 'Courier New', monospace; color: #1a2b3c; }
.closing-bar--elec .closing-cell-val { color: #e2e8f0; }
.closing-cell-sub { font-size: 0.68rem; color: #a0aec0; margin-top: 2px; }
.val-empty       { color: #a0aec0; font-style: italic; font-size: 0.95rem; }
.val-provisional { color: #c05621; }
.val-calculated  { color: #2f855a; }
.val-shortfall   { color: #c53030; }
.val-surplus     { color: #276749; }

/* ── Actions ────────────────────────────────────────────────────────────────── */
.period-actions { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; padding: 0.5rem 1.25rem; }
.btn-add-reading { padding: 0.42rem 1rem; background: transparent; border: 2px solid #3294B8; border-radius: 7px; color: #3294B8; font-size: 0.84rem; font-weight: 700; cursor: pointer; transition: all 0.15s; display: flex; align-items: center; gap: 0.4rem; }
.btn-add-reading:hover { background: #3294B8; color: #fff; }
.btn-calc { padding: 0.45rem 1.75rem; background: #3294B8; color: #fff; border: none; border-radius: 7px; font-size: 0.88rem; font-weight: 800; cursor: pointer; transition: background 0.15s; display: flex; align-items: center; gap: 0.4rem; }
.btn-calc:hover:not(:disabled) { background: #2a7a9e; }
.btn-calc:disabled { opacity: 0.45; cursor: not-allowed; }
.btn-add-period-bottom { padding: 0.45rem 1.5rem; background: #3294B8; color: #fff; border: none; border-radius: 7px; font-size: 0.88rem; font-weight: 700; cursor: pointer; transition: background 0.15s; align-self: flex-start; display: flex; align-items: center; gap: 0.4rem; }
.btn-add-period-bottom:hover:not(:disabled) { background: #2a7a9e; }
.btn-add-period-bottom:disabled { opacity: 0.5; cursor: not-allowed; }
.calc-block-hint { font-size: 0.78rem; color: #c05621; display: flex; align-items: center; gap: 0.3rem; font-weight: 600; }
.tab-no-data { display: flex; align-items: center; gap: 0.6rem; padding: 1.25rem 1.5rem; font-size: 0.88rem; color: #718096; font-style: italic; border-top: 1px solid #e8f4f8; }

/* ── Tables ─────────────────────────────────────────────────────────────────── */
.data-table { width: 100%; border-collapse: collapse; font-size: 0.84rem; }
.data-table th { background: #f0f8fb; padding: 0.45rem 0.75rem; text-align: left; font-size: 0.74rem; font-weight: 800; color: #3294B8; border-bottom: 2px solid #B0D3DF; }
.data-table td { padding: 0.4rem 0.75rem; border-bottom: 1px solid #f0f8fb; color: #2d3748; }
.data-table tr:hover td { background: #f7fbfd; }
.data-table .num { text-align: right; font-variant-numeric: tabular-nums; }
.data-table .total-row td { font-weight: 800; background: #f0f8fb !important; border-top: 2px solid #B0D3DF; }

/* ── Period billing section ─────────────────────────────────────────────────── */
.period-billing { background: #1a2b3c; border-top: 2px solid #2d3748; }
.period-billing-header { display: flex; align-items: center; gap: 0.5rem; font-size: 0.78rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.07em; color: #B0D3DF; padding: 0.85rem 1.25rem 0.5rem; }
.period-billing-header i { font-size: 0.82rem; color: #3294B8; }

.bill-meter-section { border-bottom: 1px solid rgba(255,255,255,.08); padding: 0.75rem 1.25rem; }
.bill-meter-section:last-child { border-bottom: none; }
.bill-meter-hdr { display: flex; align-items: center; gap: 0.5rem; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.07em; margin-bottom: 0.65rem; }
.bill-meter-hdr--water   { color: #90cdf4; }
.bill-meter-hdr--elec    { color: #f6d860; }
.bill-meter-hdr--generic { color: #b794f4; }
.bill-meter-hdr--adj     { color: #fbd38d; }

/* ── Adjustment detail ── */
.bill-adj-section { border-top: 1px solid rgba(255,255,255,.1); }
.adj-detail-row { background: rgba(255,255,255,.04); border-radius: 6px; padding: 0.7rem 0.85rem; margin-top: 0.5rem; }
.adj-detail-period { font-size: 0.78rem; font-weight: 700; color: #e2e8f0; margin-bottom: 0.55rem; display: flex; align-items: center; gap: 0.4rem; }
.adj-detail-period i { color: #fbd38d; }
.adj-detail-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin-bottom: 0.5rem; }
.adj-cell { }
.adj-cell-label { font-size: 0.67rem; text-transform: uppercase; letter-spacing: 0.06em; color: #718096; margin-bottom: 0.2rem; }
.adj-cell-val   { font-size: 0.88rem; font-weight: 800; color: #e2e8f0; }
.adj-cell-sub   { font-size: 0.76rem; font-weight: 600; margin-top: 0.1rem; }
.adj-reason { font-size: 0.75rem; color: #a0aec0; display: flex; align-items: center; gap: 0.35rem; border-top: 1px solid rgba(255,255,255,.08); padding-top: 0.45rem; margin-top: 0.1rem; }
.adj-reason i { color: #fbd38d; }
.bill-meter-consumption  { font-size: 0.72rem; font-weight: 600; color: #718096; margin-left: auto; font-family: 'Courier New', monospace; }

.bill-grid { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 0.75rem; }
.bill-stat { flex: 1; min-width: 120px; padding: 0.65rem 0.85rem; background: rgba(255,255,255,.05); border: 1px solid rgba(176,211,223,.15); border-radius: 7px; }
.bill-stat-label { font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: #B0D3DF; }
.bill-stat-val   { font-size: 1rem; font-weight: 800; color: #fff; margin-top: 3px; }
.tier-section { margin-top: 0.25rem; }
.tier-label   { font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #B0D3DF; margin-bottom: 0.4rem; }
.period-billing .data-table th { background: rgba(255,255,255,.05); color: #B0D3DF; border-color: rgba(176,211,223,.3); }
.period-billing .data-table td { color: #e2e8f0; border-color: rgba(255,255,255,.06); }
.period-billing .data-table tr:hover td { background: rgba(255,255,255,.05); }
.period-billing .data-table .total-row td { background: rgba(255,255,255,.08) !important; border-color: rgba(176,211,223,.3); }

/* ── Grand total ────────────────────────────────────────────────────────────── */
.bill-grand-total { padding: 0.75rem 1.25rem; background: rgba(50,148,184,.12); border-top: 1px solid rgba(176,211,223,.2); display: flex; flex-direction: column; gap: 0.25rem; }
.bgt-row  { display: flex; justify-content: space-between; font-size: 0.84rem; color: #B0D3DF; }
.bgt-total { display: flex; justify-content: space-between; font-size: 1.1rem; font-weight: 800; color: #fff; padding-top: 0.4rem; border-top: 1px solid rgba(255,255,255,.15); margin-top: 0.1rem; }

/* ── Error / misc ───────────────────────────────────────────────────────────── */
.msg-error { padding: 0.5rem 0.75rem; background: #fff5f5; border: 1.5px solid #fc8181; border-radius: 6px; color: #c53030; font-size: 0.83rem; margin: 0 1.25rem 0.25rem; }

/* ── Responsive ─────────────────────────────────────────────────────────────── */
@media (max-width: 600px) {
  .cp { padding: 1rem; }
  .stats-bar { grid-template-columns: 1fr; }
  .closing-bar { grid-template-columns: 1fr; }
  .bill-grid { flex-direction: column; }
}

/* ── Alarm Modal (ALM-001) ──────────────────────────────────────────────────── */
.alarm-overlay {
  position: fixed; inset: 0; background: rgba(10,20,40,.55); z-index: 9000;
  display: flex; align-items: center; justify-content: center; padding: 1rem;
}
.alarm-modal {
  background: #fff; border-radius: 14px; width: 100%; max-width: 480px;
  box-shadow: 0 12px 40px rgba(0,0,0,.25); overflow: hidden;
  font-family: 'Nunito', sans-serif;
}
.alarm-modal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 1rem 1.25rem; background: #fef3c7; border-bottom: 1px solid #fde68a;
}
.alarm-modal-title {
  display: flex; align-items: center; gap: 0.5rem;
  font-weight: 800; font-size: 1rem; color: #92400e;
}
.alarm-modal-title i { font-size: 1.1rem; }
.alarm-modal-close {
  background: none; border: none; cursor: pointer; color: #92400e;
  font-size: 1rem; padding: 0.2rem; border-radius: 4px;
}
.alarm-modal-close:hover { background: #fde68a; }
.alarm-modal-body { padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem; }
.alarm-modal-item { display: flex; gap: 0.85rem; align-items: flex-start; }
.alarm-modal-icon {
  width: 2.4rem; height: 2.4rem; border-radius: 50%; display: flex;
  align-items: center; justify-content: center; flex-shrink: 0; font-size: 1rem;
}
.alarm-modal-icon--water { background: #e8f4f8; color: #2a7a9e; }
.alarm-modal-icon--elec  { background: #fef3c7; color: #92400e; }
.alarm-modal-msg { font-weight: 700; font-size: 0.92rem; color: #1a2b3c; margin-bottom: 0.3rem; }
.alarm-modal-sub { font-size: 0.78rem; color: #718096; display: flex; align-items: center; gap: 0.3rem; flex-wrap: wrap; }
.alarm-ref { font-family: 'Courier New', monospace; font-weight: 700; color: #2a7a9e;
  background: #e8f4f8; padding: 0.1rem 0.4rem; border-radius: 4px; }
.alarm-modal-footer { padding: 0.9rem 1.25rem; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; }
.alarm-dismiss {
  padding: 0.45rem 1.5rem; background: #2d3748; color: #fff; border: none;
  border-radius: 8px; font-size: 0.88rem; font-weight: 700; cursor: pointer;
  display: flex; align-items: center; gap: 0.4rem; transition: background 0.15s;
}
.alarm-dismiss:hover { background: #1a202c; }
</style>
