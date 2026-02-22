<template>
    <div class="billing-calculator-wrapper">
        <!-- Top Input Fields -->
        <div class="card shadow mb-3">
            <div class="card-body py-2">
                <div class="row">
                    <div class="col-3">
                        <div class="form-group mb-2">
                            <label class="mb-0" style="font-size: 0.85rem;">Start Month</label>
                            <input type="month" class="form-control form-control-sm" v-model="startMonth" />
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group mb-2">
                            <label class="mb-0" style="font-size: 0.85rem;">Meter Start Reading</label>
                            <input type="number" class="form-control form-control-sm" v-model.number="meterStartReading" step="0.01" />
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group mb-2">
                            <label class="mb-0" style="font-size: 0.85rem;">Set Bill Day</label>
                            <input type="number" class="form-control form-control-sm" v-model.number="billDay" min="1" max="31" />
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group mb-2">
                            <label class="mb-0" style="font-size: 0.85rem;">Meter Start Day</label>
                            <select class="form-control form-control-sm" v-model.number="meterStartDay" :disabled="!hasValidPeriod">
                                <option value="">-- Select Day --</option>
                                <option v-for="day in periodDays" :key="day.value" :value="day.value">
                                    {{ day.label }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tariff Template Validation -->
        <div class="card shadow mb-3">
            <div class="card-header py-2">
                <h6 class="m-0 font-weight-bold" style="font-size: 0.95rem;">Tariff Template Validation</h6>
            </div>
            <div class="card-body py-2">
                <div class="row">
                    <div class="col-4">
                        <div class="form-group mb-2">
                            <label class="mb-0" style="font-size: 0.85rem;">Select Period:</label>
                            <select class="form-control form-control-sm" v-model="selectedPeriodId">
                                <option value="">-- Select Period --</option>
                                <option v-for="period in periods" :key="period.period_id" :value="period.period_id">
                                    {{ period.period_label || `Period ${period.period_id}` }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="col-5">
                        <div class="form-group mb-2">
                            <label class="mb-0" style="font-size: 0.85rem;">Select Tariff Template:</label>
                            <select class="form-control form-control-sm" v-model="selectedTariffTemplateId" :disabled="loadingTemplates">
                                <option value="">-- Select Template --</option>
                                <option v-for="template in tariffTemplateOptions" :key="template.value" :value="template.value">
                                    {{ template.label }} ({{ template.region }})
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group mb-2">
                            <label class="mb-0" style="font-size: 0.85rem;">&nbsp;</label>
                            <button class="btn btn-primary btn-sm btn-block" @click="handleGenerateBill" :disabled="generatingBill || !selectedPeriodId || !selectedTariffTemplateId">
                                <i class="fas fa-calculator mr-1"></i> Generate Bill
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Generated Bill Results -->
                <div v-if="generatedBill" class="mt-2">
                    <div class="alert alert-success py-2" style="font-size: 0.85rem;">
                        <div class="row">
                            <div class="col-6">
                                <p class="mb-1"><strong>Period:</strong> {{ generatedBill.period.period_label }}</p>
                                <p class="mb-1"><strong>Tariff:</strong> {{ generatedBill.tariff.template_name }} ({{ generatedBill.tariff.region }})</p>
                                <p class="mb-0"><strong>Consumption:</strong> {{ generatedBill.consumption.volume_kl || generatedBill.consumption.volume_kwh }} {{ generatedBill.consumption.volume_kl ? 'kL' : 'kWh' }}</p>
                            </div>
                            <div class="col-6">
                                <p class="mb-1"><strong>Usage:</strong> R{{ formatNumber(generatedBill.charges.usage_charge, 2) }}</p>
                                <p class="mb-1"><strong>Fixed:</strong> R{{ formatNumber(generatedBill.charges.fixed_charges, 2) }}</p>
                                <p class="mb-1"><strong>VAT ({{ generatedBill.charges.vat_rate }}%):</strong> R{{ formatNumber(generatedBill.charges.vat_amount, 2) }}</p>
                                <p class="mb-0"><strong>Total:</strong> R{{ formatNumber(generatedBill.charges.total, 2) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-if="generateBillError" class="mt-2">
                    <div class="alert alert-danger py-2" style="font-size: 0.85rem;">{{ generateBillError }}</div>
                </div>
            </div>
        </div>

        <!-- Tier Configuration -->
        <div class="card shadow mb-3">
            <div class="card-header py-2">
                <div class="row align-items-center">
                    <div class="col">
                        <h6 class="m-0 font-weight-bold" style="font-size: 0.95rem;">Tier Configuration</h6>
                    </div>
                    <div class="col-auto">
                        <span v-if="linkedTariffTemplate" class="badge badge-success mr-2">
                            <i class="fas fa-link mr-1"></i> Linked: {{ linkedTariffTemplate.template_name }}
                        </span>
                        <button v-if="!linkedTariffTemplate" class="btn btn-info btn-sm" @click="showLinkTariffModal = true">
                            <i class="fas fa-link mr-1"></i> Link Tariff Template
                        </button>
                        <button v-else class="btn btn-warning btn-sm" @click="unlinkTariffTemplate">
                            <i class="fas fa-unlink mr-1"></i> Unlink
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body py-2">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm mb-2" style="font-size: 0.85rem;">
                        <thead class="thead-light">
                            <tr>
                                <th style="width: 25%;">Tier</th>
                                <th style="width: 25%;">Max Litres</th>
                                <th style="width: 35%;">Rate per Kilolitre</th>
                                <th style="width: 15%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(tier, index) in tiers" :key="index">
                                <td>
                                    <input type="text" class="form-control form-control-sm" v-model="tier.label" placeholder="Tier" :readonly="linkedTariffTemplate">
                                </td>
                                <td>
                                    <input type="number" class="form-control form-control-sm" v-model.number="tier.max_units" placeholder="Max" :readonly="linkedTariffTemplate">
                                </td>
                                <td>
                                    <input type="number" class="form-control form-control-sm" v-model.number="tier.rate_per_unit" step="0.01" :readonly="linkedTariffTemplate">
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-danger btn-sm" @click="removeTier(index)" title="Remove" :disabled="linkedTariffTemplate">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="tiers.length === 0">
                                <td colspan="4" class="text-center text-muted py-2">No tiers. Click "Add Tier" below or link a tariff template.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div>
                    <button class="btn btn-secondary btn-sm" @click="addTier" :disabled="linkedTariffTemplate">
                        <i class="fas fa-plus mr-1"></i> Add Tier
                    </button>
                    <button class="btn btn-primary btn-sm ml-2" @click="handleSeedData" :disabled="loading || linkedTariffTemplate">
                        <i class="fas fa-sparkles mr-1"></i> Load Default
                    </button>
                </div>
            </div>
        </div>

        <!-- Link Tariff Template Modal -->
        <div class="modal fade" :class="{ 'show': showLinkTariffModal }" :style="{ display: showLinkTariffModal ? 'block' : 'none' }" tabindex="-1" role="dialog" v-if="showLinkTariffModal" @click.self="showLinkTariffModal = false">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Link Tariff Template</h5>
                        <button type="button" class="close" @click="showLinkTariffModal = false">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Select Tariff Template:</label>
                            <select class="form-control" v-model="selectedTariffForLinking" :disabled="loadingTariffDetails">
                                <option value="">-- Select Template --</option>
                                <option v-for="template in tariffTemplateOptions" :key="template.value" :value="template.value">
                                    {{ template.label }} ({{ template.region }})
                                </option>
                            </select>
                        </div>
                        <div v-if="loadingTariffDetails" class="text-center py-2">
                            <div class="spinner-border spinner-border-sm" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="showLinkTariffModal = false">Cancel</button>
                        <button type="button" class="btn btn-primary" @click="linkTariffTemplate" :disabled="!selectedTariffForLinking || loadingTariffDetails">
                            Link Template
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade" :class="{ 'show': showLinkTariffModal }" v-if="showLinkTariffModal"></div>

        <!-- Periods List -->
        <div class="card shadow mb-3">
            <div class="card-header py-2">
                <div class="row align-items-center">
                    <div class="col">
                        <h6 class="m-0 font-weight-bold" style="font-size: 0.95rem;">Periods</h6>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-success btn-sm mr-2" @click="saveToLocalStorage" title="Save Data">
                            <i class="fas fa-save mr-1"></i> Save
                        </button>
                        <button class="btn btn-primary btn-sm" @click="addNewPeriod">
                            <i class="fas fa-plus mr-1"></i> Add New Period
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body py-2">
                <div v-for="(period, periodIndex) in periodsList" :key="period.id" class="mb-3">
                    <div class="card border" :class="{ 'border-primary': periodIndex === activePeriodIndex }">
                        <div class="card-header py-2" style="cursor: pointer;" @click="togglePeriod(periodIndex)">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h6 class="mb-0 font-weight-bold" style="font-size: 0.9rem;">
                                        <i class="fas" :class="period.isExpanded ? 'fa-chevron-down' : 'fa-chevron-right'"></i>
                                        {{ period.label }} 
                                        <span v-if="periodIndex === activePeriodIndex" class="badge badge-primary ml-2">Active</span>
                                    </h6>
                                    <small class="text-muted">{{ period.dateRange }}</small>
                                </div>
                                <div class="col-auto">
                                    <button class="btn btn-sm btn-outline-primary mr-2" @click.stop="setActivePeriod(periodIndex)">
                                        Select
                                    </button>
                                    <button 
                                        v-if="periodIndex === periodsList.length - 1 && periodsList.length > 1" 
                                        class="btn btn-sm btn-outline-danger" 
                                        @click.stop="deleteLastPeriod"
                                        title="Delete Last Period"
                                    >
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div v-show="period.isExpanded" class="card-body py-2">
                            <!-- Meter Readings for this period -->
                            <div v-for="(reading, readingIndex) in period.readings" :key="readingIndex" class="row mb-2">
                                <div class="col-4">
                                    <label class="mb-0" style="font-size: 0.85rem;">Day</label>
                                    <select class="form-control form-control-sm" v-model.number="reading.day" :disabled="!hasValidPeriod">
                                        <option value="">-- Select Day --</option>
                                        <option v-for="day in getPeriodDays(period.startMonth)" :key="day.value" :value="day.value">
                                            {{ day.label }}
                                        </option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="mb-0" style="font-size: 0.85rem;">Reading</label>
                                    <input type="number" class="form-control form-control-sm" v-model.number="reading.reading" placeholder="Reading" step="0.01" />
                                </div>
                                <div class="col-2 d-flex align-items-end">
                                    <button class="btn btn-danger btn-sm mr-1" @click="removePeriodReading(periodIndex, readingIndex)" v-if="period.readings.length > 1" title="Delete Row">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <a href="#" class="text-primary" @click.prevent="addPeriodReadingRow(periodIndex)" style="font-size: 0.9rem;" v-if="readingIndex === period.readings.length - 1">
                                        Add row
                                    </a>
                                </div>
                            </div>
                            <!-- Projected/Provisional/Calculated/Actual reading display -->
                            <div v-if="period.readings.length > 0" class="mt-2 pt-2 border-top">
                                <small class="text-muted">
                                    <strong>{{ getPeriodReadingStatusLabel(period) }}:</strong> 
                                    {{ formatNumberWithSpaces(getPeriodProjectedReading(period)) }}
                                </small>
                            </div>

                            <!-- Period Summary - Four Column Red Box (shown when period is expanded) -->
                            <div v-if="period.isExpanded" class="mt-3">
                                <!-- Period Status Badge and Warnings -->
                                <div class="mb-2 d-flex align-items-center flex-wrap">
                                    <span class="badge mr-2" :class="getPeriodStatusBadgeClass(periodIndex)">
                                        {{ getPeriodSummaryValue(periodIndex, 'period_status') || 'PROJECTED' }}
                                    </span>
                                    <!-- Usage State Badge (from Pipeline Step 4) -->
                                    <span v-if="getPeriodSummaryValue(periodIndex, 'usage_state') && getPeriodSummaryValue(periodIndex, 'usage_state') !== 'CONTINUOUS'" 
                                          class="badge mr-2"
                                          :class="{
                                              'badge-secondary': getPeriodSummaryValue(periodIndex, 'usage_state') === 'INSUFFICIENT',
                                              'badge-warning': getPeriodSummaryValue(periodIndex, 'usage_state') === 'DISCONTINUOUS',
                                              'badge-danger': getPeriodSummaryValue(periodIndex, 'usage_state') === 'INVALID'
                                          }">
                                        <i class="fas mr-1" :class="{
                                            'fa-hourglass-half': getPeriodSummaryValue(periodIndex, 'usage_state') === 'INSUFFICIENT',
                                            'fa-pause-circle': getPeriodSummaryValue(periodIndex, 'usage_state') === 'DISCONTINUOUS',
                                            'fa-exclamation-circle': getPeriodSummaryValue(periodIndex, 'usage_state') === 'INVALID'
                                        }"></i>
                                        {{ getPeriodSummaryValue(periodIndex, 'usage_state') }}
                                    </span>
                                    <span v-if="getPeriodSummaryValue(periodIndex, 'opening_is_provisional')" class="badge badge-warning mr-2">
                                        <i class="fas fa-exclamation-triangle mr-1"></i> Opening is Provisional
                                    </span>
                                    <span v-if="getPeriodSummaryValue(periodIndex, 'monotonic_floor_applied')" class="badge badge-info mr-2">
                                        <i class="fas fa-level-up-alt mr-1"></i> Floor Applied
                                    </span>
                                    <span v-if="getPeriodSummaryValue(periodIndex, 'low_confidence')" class="badge badge-danger mr-2">
                                        <i class="fas fa-question-circle mr-1"></i> Low Confidence
                                    </span>
                                    <small v-if="!getPeriodSummaryValue(periodIndex, 'can_calculate')" class="text-muted ml-2">
                                        {{ getPeriodSummaryValue(periodIndex, 'message') || 'The app needs two readings to calculate.' }}
                                    </small>
                                </div>
                                
                                <!-- Low Reading Warning Alert -->
                                <div v-if="getPeriodSummaryValue(periodIndex, 'low_reading_warning')?.detected" class="alert alert-warning py-2 mb-2" style="font-size: 0.85rem;">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <i class="fas fa-exclamation-triangle mr-2"></i>
                                            <strong>Low Reading Detected:</strong>
                                            {{ getPeriodSummaryValue(periodIndex, 'low_reading_warning')?.message }}
                                            <br>
                                            <small class="text-muted">
                                                Actual: {{ formatNumberWithSpaces(getPeriodSummaryValue(periodIndex, 'low_reading_warning')?.actual_reading) }} L | 
                                                Expected: {{ formatNumberWithSpaces(getPeriodSummaryValue(periodIndex, 'low_reading_warning')?.expected_reading) }} L |
                                                {{ getPeriodSummaryValue(periodIndex, 'low_reading_warning')?.difference_percent }}% below expected
                                            </small>
                                        </div>
                                        <div>
                                            <button class="btn btn-sm btn-success mr-1" @click="confirmLowReading(periodIndex, true)">
                                                <i class="fas fa-check"></i> Yes, Correct
                                            </button>
                                            <button class="btn btn-sm btn-danger" @click="confirmLowReading(periodIndex, false)">
                                                <i class="fas fa-times"></i> No, Fix It
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- ============================================================
                                     ORIGINAL RED SECTION - 4-Column Summary Display
                                     (Restored - DO NOT REMOVE)
                                     ============================================================ -->
                                <div class="alert alert-danger mb-0 py-3" style="background-color: #dc3545; border-color: #dc3545;">
                                    <div class="row text-center">
                                        <div class="col-3">
                                            <div style="font-size: 0.85rem; font-weight: 500; color: white; margin-bottom: 0.5rem;">Projected</div>
                                            <div class="h3 mb-0" style="font-weight: bold; color: white;">
                                                {{ formatNumberWithSpaces(getPeriodSummaryValue(periodIndex, 'total_usage') || getPeriodSummaryValue(periodIndex, 'projected_reading')) }}
                                                <span style="font-size: 0.7rem; font-weight: normal;"> L</span>
                                            </div>
                                        </div>
                                        <div class="col-3">
                                            <div style="font-size: 0.85rem; font-weight: 500; color: white; margin-bottom: 0.5rem;">Daily Usage</div>
                                            <div class="h3 mb-0" style="font-weight: bold; color: white;">
                                                {{ formatNumberWithSpaces(getPeriodSummaryValue(periodIndex, 'daily_usage')) }}
                                                <span style="font-size: 0.7rem; font-weight: normal;"> L</span>
                                            </div>
                                        </div>
                                        <div class="col-3">
                                            <div style="font-size: 0.85rem; font-weight: 500; color: white; margin-bottom: 0.5rem;">Daily Cost</div>
                                            <div class="h3 mb-0" style="font-weight: bold; color: white;">
                                                R{{ formatNumber(getPeriodSummaryValue(periodIndex, 'daily_cost'), 2) }}
                                            </div>
                                        </div>
                                        <div class="col-3">
                                            <div style="font-size: 0.85rem; font-weight: 500; color: white; margin-bottom: 0.5rem;">Projected Total</div>
                                            <div class="h3 mb-0" style="font-weight: bold; color: white;">
                                                R{{ formatNumber(getPeriodSummaryValue(periodIndex, 'projected_total'), 2) }}
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- CONFIDENCE INDICATOR (ADDED - for DISCONTINUOUS/INVALID states) -->
                                    <div v-if="getPeriodSummaryValue(periodIndex, 'is_low_confidence')" 
                                         class="mt-2 text-center" style="border-top: 1px solid rgba(255,255,255,0.3); padding-top: 0.5rem;">
                                        <span class="badge badge-warning">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                            {{ getPeriodSummaryValue(periodIndex, 'confidence_level') || 'ESTIMATED' }}
                                        </span>
                                        <small class="text-white-50 ml-2">Projections informational only. Billing based on proven usage.</small>
                                    </div>
                                    
                                    <!-- ADJUSTMENT ROW (ADDED - Ledger adjustment from previous period) -->
                                    <div v-if="getPeriodSummaryValue(periodIndex, 'adjustment_brought_forward') && getPeriodSummaryValue(periodIndex, 'adjustment_brought_forward') !== 0" 
                                         class="mt-2 text-center" style="border-top: 1px solid rgba(255,255,255,0.3); padding-top: 0.5rem;">
                                        <span :class="getPeriodSummaryValue(periodIndex, 'adjustment_brought_forward') < 0 ? 'badge badge-success' : 'badge badge-warning'">
                                            <i class="fas mr-1" :class="getPeriodSummaryValue(periodIndex, 'adjustment_brought_forward') < 0 ? 'fa-minus-circle' : 'fa-plus-circle'"></i>
                                            Adjustment from {{ getPeriodSummaryValue(periodIndex, 'adjustment_source_label') || 'Previous Period' }}:
                                            {{ getPeriodSummaryValue(periodIndex, 'adjustment_brought_forward') < 0 ? '-' : '+' }}R{{ formatNumber(Math.abs(getPeriodSummaryValue(periodIndex, 'adjustment_brought_forward')), 2) }}
                                            ({{ formatNumberWithSpaces(Math.abs(getPeriodSummaryValue(periodIndex, 'adjustment_quantity') || 0)) }} L)
                                        </span>
                                    </div>
                                    
                                    <!-- RECONCILIATION INFO (ADDED - Shows what WOULD have been billed) -->
                                    <div v-if="getPeriodSummaryValue(periodIndex, 'cost_adjustment') !== null && getPeriodSummaryValue(periodIndex, 'cost_adjustment') !== 0" 
                                         class="mt-2 text-center" style="border-top: 1px solid rgba(255,255,255,0.3); padding-top: 0.5rem; font-size: 0.8rem;">
                                        <span class="text-white-50">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            Provisioned: R{{ formatNumber(getPeriodSummaryValue(periodIndex, 'provisioned_cost'), 2) }} |
                                            Should Have Been: R{{ formatNumber(getPeriodSummaryValue(periodIndex, 'calculated_cost'), 2) }} |
                                            <span :class="getPeriodSummaryValue(periodIndex, 'cost_adjustment') < 0 ? 'text-success' : 'text-warning'">
                                                {{ getPeriodSummaryValue(periodIndex, 'cost_adjustment') < 0 ? 'Credit' : 'Debit' }}: 
                                                {{ getPeriodSummaryValue(periodIndex, 'cost_adjustment') < 0 ? '-' : '+' }}R{{ formatNumber(Math.abs(getPeriodSummaryValue(periodIndex, 'cost_adjustment')), 2) }}
                                            </span>
                                            (carried forward)
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Detailed Metrics Row -->
                                <div class="row mt-2 px-2" style="font-size: 0.8rem;">
                                    <div class="col-4">
                                        <small class="text-muted">
                                            <strong>Opening:</strong> {{ formatNumberWithSpaces(getPeriodSummaryValue(periodIndex, 'opening_reading')) }} L
                                        </small>
                                    </div>
                                    <div class="col-4 text-center">
                                        <small class="text-muted">
                                            <strong>Closing:</strong> {{ formatNumberWithSpaces(getPeriodSummaryValue(periodIndex, 'projected_reading')) }} L
                                        </small>
                                    </div>
                                    <div class="col-4 text-right">
                                        <small class="text-muted">
                                            <strong>Daily:</strong> 
                                            <span v-if="getPeriodSummaryValue(periodIndex, 'daily_usage') !== null">
                                                {{ formatNumberWithSpaces(getPeriodSummaryValue(periodIndex, 'daily_usage')) }} L/day
                                            </span>
                                            <span v-else class="text-warning">N/A</span>
                                        </small>
                                    </div>
                                </div>
                                <!-- Detailed info row -->
                                <div class="row mt-2" style="font-size: 0.8rem;">
                                    <div class="col-6">
                                        <small class="text-muted">
                                            <strong>Days:</strong> {{ getPeriodSummaryValue(periodIndex, 'days_in_period') }} |
                                            <strong>Readings:</strong> {{ getPeriodSummaryValue(periodIndex, 'readings_in_period') || getPeriodSummaryValue(periodIndex, 'readings_count') || 0 }} in period
                                        </small>
                                    </div>
                                    <div class="col-6 text-right">
                                        <small class="text-muted">
                                            <strong>Days:</strong> {{ getPeriodSummaryValue(periodIndex, 'days_in_period') }} |
                                            <strong>Readings:</strong> {{ getPeriodSummaryValue(periodIndex, 'readings_in_period') || 0 }} in period
                                        </small>
                                    </div>
                                </div>
                                <div class="row" style="font-size: 0.8rem;" v-if="getPeriodSummaryValue(periodIndex, 'can_calculate')">
                                    <div class="col-6">
                                        <small class="text-muted">
                                            <strong>Proven Usage:</strong> {{ formatNumberWithSpaces(getPeriodSummaryValue(periodIndex, 'proven_usage')) }} L
                                        </small>
                                    </div>
                                    <div class="col-6 text-right">
                                        <small class="text-muted">
                                            <strong>Period Usage:</strong> {{ formatNumberWithSpaces(getPeriodSummaryValue(periodIndex, 'period_usage')) }} L
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-if="periodsList.length === 0" class="text-center text-muted py-3">
                    No periods yet. Click "Add New Period" to get started.
                </div>
            </div>
        </div>


        <!-- Bill Status and View Full Bill -->
        <div class="row mb-3">
            <div class="col-6">
                <strong style="font-size: 0.9rem;">Bill Status: </strong>
                <span class="badge badge-warning">{{ billStatus }}</span>
            </div>
            <div class="col-6 text-right">
                <a href="#" class="text-primary" @click.prevent="viewFullBill" v-if="generatedBill" style="font-size: 0.9rem;">
                    View Full Bill
                </a>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue';

export default {
    name: 'BillingCalculatorForm',
    props: {
        csrfToken: {
            type: String,
            required: true
        },
        apiUrls: {
            type: Object,
            required: true
        }
    },
    setup(props) {
        // State
        const startMonth = ref('');
        const meterStartReading = ref(0);
        const billDay = ref(20);
        const meterStartDay = ref(1);
        const tiers = ref([]);
        const periods = ref([]);
        const periodsList = ref([]); // Array of period objects with their own readings
        const activePeriodIndex = ref(0); // Currently active/selected period index
        const meterReadings = ref([
            { day: null, reading: null }
        ]);
        
        const loading = ref(false);
        const loadingTemplates = ref(false);
        const generatingBill = ref(false);
        const generatedBill = ref(null);
        const generateBillError = ref(null);
        const selectedPeriodId = ref(null);
        const selectedTariffTemplateId = ref(null);
        const tariffTemplateOptions = ref([]);
        const billStatus = ref('Provisional');
        const linkedTariffTemplate = ref(null);
        const showLinkTariffModal = ref(false);
        const selectedTariffForLinking = ref(null);
        const loadingTariffDetails = ref(false);
        const originalTiers = ref([]); // Store original tiers before linking

        // Computed
        const dateRange = computed(() => {
            if (startMonth.value && billDay.value) {
                const startDate = new Date(startMonth.value + '-01');
                const endDate = new Date(startDate);
                endDate.setMonth(endDate.getMonth() + 1);
                
                // Use bill day for both dates
                startDate.setDate(Math.min(billDay.value, new Date(startDate.getFullYear(), startDate.getMonth() + 1, 0).getDate()));
                endDate.setDate(Math.min(billDay.value, new Date(endDate.getFullYear(), endDate.getMonth() + 1, 0).getDate()));
                
                const startDay = startDate.getDate();
                const startMonthName = startDate.toLocaleDateString('en-GB', { month: 'short' });
                const startYear = startDate.getFullYear();
                
                const endDay = endDate.getDate();
                const endMonthName = endDate.toLocaleDateString('en-GB', { month: 'short' });
                const endYear = endDate.getFullYear();
                
                // Add ordinal suffix to day
                const getOrdinalSuffix = (day) => {
                    if (day > 3 && day < 21) return 'th';
                    switch (day % 10) {
                        case 1: return 'st';
                        case 2: return 'nd';
                        case 3: return 'rd';
                        default: return 'th';
                    }
                };
                
                const startDayWithSuffix = `${startDay}${getOrdinalSuffix(startDay)}`;
                const endDayWithSuffix = `${endDay}${getOrdinalSuffix(endDay)}`;
                
                return `${startDayWithSuffix} ${startMonthName} ${startYear} to ${endDayWithSuffix} ${endMonthName} ${endYear}`;
            }
            return '';
        });

        // Summary values from BillingEngine (loaded via API)
        const summaryData = ref({
            projected_reading: 0,
            daily_usage: 0,
            daily_cost: 0,
            projected_total: 0,
            can_calculate: false,
        });
        const loadingSummary = ref(false);
        
        // Store summary data per period (keyed by period index)
        const periodSummaries = ref({});

        // Computed properties for summary data (backward compatibility - can be removed if not needed)
        const projectedReading = computed(() => {
            return summaryData.value.projected_reading || 0;
        });

        const dailyUsage = computed(() => {
            return summaryData.value.daily_usage || 0;
        });

        const dailyCost = computed(() => {
            return summaryData.value.daily_cost || 0;
        });

        const projectedTotal = computed(() => {
            return summaryData.value.projected_total || 0;
        });

        const periodSummaryData = computed(() => {
            if (activePeriodIndex.value >= 0 && periodSummaries.value[activePeriodIndex.value]) {
                return periodSummaries.value[activePeriodIndex.value];
            }
            return summaryData.value;
        });

        const getPeriodSummaryValue = (periodIndex, field) => {
            // Get summary data for this specific period
            if (periodSummaries.value[periodIndex] && periodSummaries.value[periodIndex][field] !== undefined) {
                return periodSummaries.value[periodIndex][field];
            }
            // If no summary exists for this period, return appropriate default
            if (field === 'period_status') return 'PROJECTED';
            if (field === 'can_calculate') return false;
            if (field === 'message') return '';
            return 0;
        };

        // Get the appropriate badge class for period status
        const getPeriodStatusBadgeClass = (periodIndex) => {
            const status = getPeriodSummaryValue(periodIndex, 'period_status');
            switch (status) {
                case 'ACTUAL': return 'badge-success';
                case 'PROVISIONAL': return 'badge-warning';
                case 'CALCULATED': return 'badge-info';
                case 'PROJECTED': 
                default: return 'badge-secondary';
            }
        };

        // Get the appropriate label for the closing reading based on status
        const getPeriodClosingLabel = (periodIndex) => {
            const status = getPeriodSummaryValue(periodIndex, 'period_status');
            switch (status) {
                case 'ACTUAL': return 'Actual Closing';
                case 'PROVISIONAL': return 'Provisional Closing';
                case 'CALCULATED': return 'Calculated Closing';
                case 'PROJECTED': 
                default: return 'Projected Closing';
            }
        };

        const hasValidPeriod = computed(() => {
            return startMonth.value && billDay.value;
        });

        const periodDays = computed(() => {
            if (!hasValidPeriod.value) return [];
            
            const startDate = new Date(startMonth.value + '-01');
            const endDate = new Date(startDate);
            endDate.setMonth(endDate.getMonth() + 1);
            
            // Use bill day for both dates
            const startDayNum = Math.min(billDay.value, new Date(startDate.getFullYear(), startDate.getMonth() + 1, 0).getDate());
            const endDayNum = Math.min(billDay.value, new Date(endDate.getFullYear(), endDate.getMonth() + 1, 0).getDate());
            
            startDate.setDate(startDayNum);
            endDate.setDate(endDayNum);
            
            const days = [];
            const currentDate = new Date(startDate);
            
            // Add ordinal suffix helper
            const getOrdinalSuffix = (day) => {
                if (day > 3 && day < 21) return 'th';
                switch (day % 10) {
                    case 1: return 'st';
                    case 2: return 'nd';
                    case 3: return 'rd';
                    default: return 'th';
                }
            };
            
            while (currentDate <= endDate) {
                const day = currentDate.getDate();
                const monthName = currentDate.toLocaleDateString('en-GB', { month: 'short' });
                const year = currentDate.getFullYear();
                const dayWithSuffix = `${day}${getOrdinalSuffix(day)}`;
                
                // Store as timestamp for unique value
                const timestamp = currentDate.getTime();
                
                days.push({
                    value: timestamp,
                    label: `${dayWithSuffix} ${monthName} ${year}`,
                    date: new Date(currentDate),
                    dayNumber: day
                });
                
                currentDate.setDate(currentDate.getDate() + 1);
            }
            
            return days;
        });

        const canCompute = computed(() => {
            // Check if all readings have both day and reading values
            return meterReadings.value.every(reading => reading.day && reading.reading !== null && reading.reading !== undefined);
        });

        /**
         * Calculate summary for a specific period using the SEQUENTIAL LEDGER model
         * 
         * CORE BILLING ENGINE BRIEF COMPLIANCE:
         * - Readings are GLOBAL and form a SINGLE, CONTINUOUS, CHRONOLOGICAL TIMELINE
         * - Usage is calculated ONLY between TWO actual readings
         * - Cost MUST be computed as soon as usage exists (even for PROVISIONAL periods)
         * - CO continuity: A period's closing reading becomes the opening of the next
         * - Periods are calculated SEQUENTIALLY (0, 1, 2...) - never in parallel
         */
        const calculatePeriodSummary = async (periodIndex) => {
            if (periodIndex < 0 || periodIndex >= periodsList.value.length) {
                return;
            }

            const period = periodsList.value[periodIndex];
            
            // CORE RULE: Readings are GLOBAL and continuous, not constrained to period boundaries
            // Collect ALL readings from ALL periods for global calculation
            const allGlobalReadings = [];
            for (let i = 0; i < periodsList.value.length; i++) {
                const p = periodsList.value[i];
                if (p.readings && p.readings.length > 0) {
                    p.readings.forEach(r => {
                        if (r.day && r.reading !== null && r.reading !== undefined && r.reading !== '') {
                            allGlobalReadings.push({
                                day: r.day,
                                reading: parseFloat(r.reading)
                            });
                        }
                    });
                }
            }
            
            // Get previous period's closing reading for CO continuity (if not period 0)
            // SEQUENTIAL LEDGER: Each period MUST wait for previous period's calculation
            let previousPeriodClosingReading = null;
            let previousPeriodStatus = null;
            if (periodIndex > 0) {
                const prevSummary = periodSummaries.value[periodIndex - 1];
                if (prevSummary) {
                    previousPeriodClosingReading = prevSummary.projected_reading || null;
                    previousPeriodStatus = prevSummary.period_status || 'PROJECTED';
                }
            }

            // Check minimum requirement: at least 2 readings globally
            if (allGlobalReadings.length < 2) {
                // Get opening reading for this period
                let openingReading = meterStartReading.value;
                if (periodIndex > 0 && previousPeriodClosingReading !== null) {
                    openingReading = previousPeriodClosingReading;
                } else if (period.readings.length > 0 && period.readings[0].reading) {
                    openingReading = parseFloat(period.readings[0].reading);
                }
                
                periodSummaries.value[periodIndex] = {
                    projected_reading: openingReading,
                    daily_usage: null,
                    daily_cost: 0,
                    projected_total: 0,
                    can_calculate: false,
                    usage_state: 'INSUFFICIENT',
                    period_status: 'PROJECTED',
                    message: 'The app needs two readings to calculate.',
                    opening_reading: openingReading,
                    days_in_period: 0,
                    readings_count: allGlobalReadings.length,
                };
                return;
            }

            // Check if tiers exist (needed for cost calculation)
            if (tiers.value.length === 0) {
                periodSummaries.value[periodIndex] = {
                    projected_reading: meterStartReading.value,
                    daily_usage: null,
                    daily_cost: 0,
                    projected_total: 0,
                    can_calculate: false,
                    usage_state: 'INSUFFICIENT',
                    period_status: 'PROJECTED',
                    message: 'No tariff tiers configured.',
                    opening_reading: meterStartReading.value,
                    days_in_period: 0,
                    readings_count: allGlobalReadings.length,
                };
                return;
            }

            try {
                const response = await fetch(props.apiUrls.calculateSummary, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': props.csrfToken
                    },
                    body: JSON.stringify({
                        readings: allGlobalReadings, // Send ALL global readings
                        tiers: tiers.value,
                        bill_day: billDay.value,
                        start_month: period.startMonth,
                        meter_start_reading: meterStartReading.value,
                        fixed_charges: 0, // Could add fixed charges input later
                        period_index: periodIndex,
                        previous_period_closing_reading: previousPeriodClosingReading,
                        previous_period_status: previousPeriodStatus,
                    })
                });

                const result = await response.json();
                if (result.success && result.data) {
                    // Store summary for this specific period
                    periodSummaries.value[periodIndex] = result.data;
                    
                    // RECONCILIATION CHECK: If we have a previous PROVISIONAL period,
                    // check if this period's readings contradict it
                    if (periodIndex > 0 && previousPeriodStatus === 'PROVISIONAL' && props.apiUrls.reconcile) {
                        await checkAndReconcile(periodIndex, allGlobalReadings);
                    }
                    
                    // Update active period's summary data if this is the active period
                    if (periodIndex === activePeriodIndex.value) {
                        summaryData.value = result.data;
                    }
                    
                    // Save to localStorage
                    saveToLocalStorage();
                } else {
                    console.error('Failed to calculate summary:', result.error);
                    const openingReading = previousPeriodClosingReading || period.readings[0]?.reading || meterStartReading.value;
                    const emptyData = {
                        projected_reading: openingReading,
                        daily_usage: null,
                        daily_cost: 0,
                        projected_total: 0,
                        can_calculate: false,
                        usage_state: 'INSUFFICIENT',
                        period_status: 'PROJECTED',
                        message: result.error || 'Calculation failed.',
                        opening_reading: openingReading,
                    };
                    periodSummaries.value[periodIndex] = emptyData;
                    if (periodIndex === activePeriodIndex.value) {
                        summaryData.value = emptyData;
                    }
                }
            } catch (error) {
                console.error('Error calculating summary:', error);
                const openingReading = previousPeriodClosingReading || period.readings[0]?.reading || meterStartReading.value;
                const emptyData = {
                    projected_reading: openingReading,
                    daily_usage: null,
                    daily_cost: 0,
                    projected_total: 0,
                    can_calculate: false,
                    usage_state: 'INSUFFICIENT',
                    period_status: 'PROJECTED',
                    message: 'Error: ' + error.message,
                    opening_reading: openingReading,
                };
                periodSummaries.value[periodIndex] = emptyData;
                if (periodIndex === activePeriodIndex.value) {
                    summaryData.value = emptyData;
                }
            }
        };

        /**
         * Check if reconciliation is needed and perform it
         * 
         * LEDGER RULE (CRITICAL):
         * - PROVISIONED amounts are IMMUTABLE - never overwrite
         * - CALCULATED amounts show what SHOULD have been billed
         * - ADJUSTMENT is carried forward as a debit/credit
         * - Both values must coexist and be visible
         */
        const checkAndReconcile = async (periodIndex, allGlobalReadings) => {
            if (periodIndex <= 0 || !props.apiUrls.reconcile) return;
            
            // ============================================================
            // MULTI-PERIOD RECONCILIATION SUPPORT
            // 
            // Collect ALL PROVISIONAL periods that need reconciliation.
            // The reconciliation spans from the last ACTUAL/CALCULATED anchor
            // to the new terminating reading.
            // 
            // ONE adjustment is computed across ALL affected periods.
            // ============================================================
            
            // Collect all PROVISIONAL periods
            const provisionedPeriods = [];
            for (let i = 0; i < periodIndex; i++) {
                const summary = periodSummaries.value[i];
                if (summary && summary.period_status === 'PROVISIONAL') {
                    const period = periodsList.value[i];
                    provisionedPeriods.push({
                        period_index: i,
                        closing_reading: summary.projected_reading || summary.proven_usage || 0,
                        usage: summary.period_usage || summary.billable_usage || summary.proven_usage || 0,
                        cost: summary.projected_total || summary.billable_cost || summary.proven_cost || 0,
                        status: summary.period_status,
                        start_month: period.startMonth,
                    });
                }
            }
            
            // If no PROVISIONAL periods, nothing to reconcile
            if (provisionedPeriods.length === 0) return;
            
            const period = periodsList.value[periodIndex];
            
            try {
                const response = await fetch(props.apiUrls.reconcile, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': props.csrfToken
                    },
                    body: JSON.stringify({
                        readings: allGlobalReadings,
                        period_index: periodIndex,
                        // Multi-period support: send all provisioned periods
                        provisioned_periods: provisionedPeriods,
                        bill_day: billDay.value,
                        tiers: tiers.value,
                    })
                });

                const result = await response.json();
                if (result.success && result.data && result.data.reconciliation_required) {
                    // ============================================================
                    // LEDGER RULE: IMMUTABLE PERIODS
                    // 
                    // ALL reconciled periods are LEDGER ENTRIES. They are FROZEN.
                    // We do NOT change their billed values.
                    // 
                    // We ONLY add metadata for UI display showing what
                    // SHOULD have been billed (for transparency).
                    // 
                    // ONE ADJUSTMENT is applied to the CURRENT period as a
                    // SEPARATE LINE ITEM - not merged into usage.
                    // ============================================================
                    
                    // Update each reconciled period with metadata (values stay frozen)
                    if (result.data.period_details) {
                        result.data.period_details.forEach(detail => {
                            const idx = detail.period_index;
                            const existingSummary = periodSummaries.value[idx];
                            if (existingSummary) {
                                periodSummaries.value[idx] = {
                                    ...existingSummary,
                                    // Status changes to CALCULATED (but values stay frozen)
                                    period_status: 'CALCULATED',
                                    
                                    // Store what WAS billed (FROZEN values - for reference)
                                    provisioned_closing: detail.provisioned_closing,
                                    provisioned_usage: detail.provisioned_usage,
                                    provisioned_cost: detail.provisioned_cost,
                                    
                                    // Store what SHOULD have been billed (informational only)
                                    calculated_closing: detail.calculated_closing,
                                    calculated_usage: detail.calculated_usage,
                                    calculated_cost: detail.calculated_cost,
                                    
                                    // Per-period difference (informational)
                                    usage_adjustment: detail.calculated_usage - detail.provisioned_usage,
                                    cost_adjustment: detail.calculated_cost - detail.provisioned_cost,
                                    
                                    // Message for UI
                                    reconciliation_message: result.data.message,
                                };
                            }
                        });
                    }
                    
                    // ============================================================
                    // APPLY SINGLE ADJUSTMENT TO CURRENT PERIOD
                    // This is a SEPARATE LINE ITEM - not merged into usage!
                    // Spans ALL reconciled periods.
                    // ============================================================
                    if (periodSummaries.value[periodIndex]) {
                        periodSummaries.value[periodIndex] = {
                            ...periodSummaries.value[periodIndex],
                            // Single adjustment from ALL reconciled periods
                            adjustment_brought_forward: result.data.cost_adjustment,
                            adjustment_quantity: result.data.usage_adjustment, // Quantity in litres
                            adjustment_quantity_kl: result.data.adjustment_quantity_kl, // Quantity in KL
                            adjustment_from_periods: provisionedPeriods.map(p => p.period_index),
                            adjustment_source_label: result.data.periods_reconciled_count > 1 
                                ? `Periods 1-${result.data.periods_reconciled_count}`
                                : `Period ${provisionedPeriods[0].period_index + 1}`,
                            adjustment_type: result.data.adjustment_type,
                            
                            // Multi-period reconciliation metadata
                            reconciled_daily_usage: result.data.reconciled_daily_usage,
                            anchor_reading: result.data.anchor_reading,
                            terminating_reading: result.data.terminating_reading,
                        };
                    }
                    
                    console.log('LEDGER RECONCILIATION APPLIED:');
                    console.log('  Previous period (FROZEN): R' + (prevSummary.projected_total || prevSummary.proven_cost));
                    console.log('  Should have been: R' + result.data.calculated_cost);
                    console.log('  Adjustment to next period: R' + result.data.cost_adjustment);
                }
            } catch (error) {
                console.error('Reconciliation check failed:', error);
            }
        };

        /**
         * Handle low reading confirmation
         * LOW-READING CONFIRMATION RULE (Section G):
         * When reading is 50%+ below expected, ask user to confirm
         */
        const confirmLowReading = (periodIndex, confirmed) => {
            if (confirmed) {
                // User confirmed the low reading is correct
                // Lock proven consumption, set daily usage to UNKNOWN/zero
                if (periodSummaries.value[periodIndex]) {
                    periodSummaries.value[periodIndex].low_reading_confirmed = true;
                    periodSummaries.value[periodIndex].low_confidence = true;
                    // Clear the warning since user confirmed
                    periodSummaries.value[periodIndex].low_reading_warning = null;
                }
                showNotification('Low reading accepted. Projections marked as LOW CONFIDENCE until next reading.', 'warning');
                saveToLocalStorage();
            } else {
                // User wants to correct the reading
                // Focus on the last reading input for this period
                const period = periodsList.value[periodIndex];
                if (period && period.readings && period.readings.length > 0) {
                    // Clear the last reading
                    period.readings[period.readings.length - 1].reading = null;
                    // Recalculate
                    calculatePeriodSummary(periodIndex);
                }
                showNotification('Please enter the correct reading.', 'info');
            }
        };

        // Calculate summary using BillingEngine API (calculates summaries for all periods)
        // IMPORTANT: Calculate periods sequentially (0, 1, 2...) so each period can use previous period's closing reading (CO continuity)
        const calculateSummary = async () => {
            // Calculate summary for all periods sequentially
            for (let i = 0; i < periodsList.value.length; i++) {
                await calculatePeriodSummary(i);
            }
        };

        const deleteLastPeriod = () => {
            if (periodsList.value.length <= 1) {
                showNotification('Cannot delete the only period', 'warning');
                return;
            }

            // Remove the last period
            periodsList.value.pop();
            
            // Update active period index if needed
            if (activePeriodIndex.value >= periodsList.value.length) {
                activePeriodIndex.value = periodsList.value.length - 1;
                updateActivePeriodData();
            }
            
            // Remove summary data for deleted period
            delete periodSummaries.value[periodsList.value.length];
            
            // Save to localStorage
            saveToLocalStorage();
            
            showNotification('Last period deleted', 'success');
        };

        const saveToLocalStorage = () => {
            try {
                const dataToSave = {
                    periodsList: periodsList.value.map(p => ({
                        ...p,
                        readings: p.readings
                    })),
                    activePeriodIndex: activePeriodIndex.value,
                    tiers: tiers.value,
                    billDay: billDay.value,
                    meterStartReading: meterStartReading.value,
                    periodSummaries: periodSummaries.value
                };
                localStorage.setItem('billingCalculatorData', JSON.stringify(dataToSave));
            } catch (error) {
                console.error('Error saving to localStorage:', error);
            }
        };

        const loadFromLocalStorage = () => {
            try {
                const saved = localStorage.getItem('billingCalculatorData');
                if (saved) {
                    const data = JSON.parse(saved);
                    
                    if (data.periodsList && data.periodsList.length > 0) {
                        // Restore periods list
                        periodsList.value = data.periodsList.map(p => ({
                            ...p,
                            readings: p.readings || [{ day: null, reading: null }],
                            isExpanded: p.isExpanded !== undefined ? p.isExpanded : true,
                            billDay: p.billDay || data.billDay || 20
                        }));
                        
                        // Restore active period index
                        activePeriodIndex.value = data.activePeriodIndex !== undefined && data.activePeriodIndex < periodsList.value.length
                            ? data.activePeriodIndex 
                            : periodsList.value.length - 1;
                        
                        // Restore other data
                        if (data.tiers && Array.isArray(data.tiers)) tiers.value = data.tiers;
                        if (data.billDay) billDay.value = data.billDay;
                        if (data.meterStartReading !== undefined) meterStartReading.value = data.meterStartReading;
                        if (data.periodSummaries && typeof data.periodSummaries === 'object') {
                            periodSummaries.value = data.periodSummaries;
                        }
                        
                        // Update active period data
                        updateActivePeriodData();
                        
                        return true;
                    }
                }
            } catch (error) {
                console.error('Error loading from localStorage:', error);
            }
            return false;
        };

        // Methods
        const formatNumber = (value, decimals = 0) => {
            if (value === null || value === undefined || isNaN(value)) return '0';
            return Number(value).toFixed(decimals);
        };

        const formatNumberWithSpaces = (value) => {
            if (value === null || value === undefined || isNaN(value)) return '0';
            const num = Math.round(Number(value));
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        };

        const addTier = () => {
            const tierNumber = tiers.value.length + 1;
            const lastMax = tiers.value.length > 0 
                ? (tiers.value[tiers.value.length - 1].max_units || 0) 
                : 0;
            
            tiers.value.push({
                tier_number: tierNumber,
                max_units: lastMax + 10,
                rate_per_unit: 30.5 + (tierNumber * 5),
                label: `Tier ${tierNumber}`
            });
        };

        const removeTier = (index) => {
            tiers.value.splice(index, 1);
        };

        const addReadingRow = () => {
            meterReadings.value.push({ day: null, reading: null });
        };

        const removeReading = (index) => {
            if (meterReadings.value.length > 1) {
                meterReadings.value.splice(index, 1);
            }
        };

        const addNewPeriod = () => {
            if (!startMonth.value || !billDay.value) {
                showNotification('Please set Start Month and Bill Day first', 'warning');
                return;
            }

            // Get the last period's projected/closing reading (if exists)
            // This becomes the opening reading for the new period
            let openingReading = meterStartReading.value;
            if (periodsList.value.length > 0) {
                const lastPeriod = periodsList.value[periodsList.value.length - 1];
                const lastPeriodIndex = periodsList.value.length - 1;
                // Use projected reading (which is the closing reading for closed periods)
                openingReading = getPeriodProjectedReading(lastPeriod) || openingReading;
            }

            // Calculate next period start month
            let periodStartMonth = startMonth.value;
            if (periodsList.value.length > 0) {
                const lastPeriod = periodsList.value[periodsList.value.length - 1];
                const lastDate = new Date(lastPeriod.startMonth + '-01');
                lastDate.setMonth(lastDate.getMonth() + 1);
                periodStartMonth = `${lastDate.getFullYear()}-${String(lastDate.getMonth() + 1).padStart(2, '0')}`;
            }

            // Create new period
            const newPeriod = {
                id: periodsList.value.length + 1,
                label: `Period ${periodsList.value.length + 1}`,
                startMonth: periodStartMonth,
                readings: [
                    { day: null, reading: openingReading }
                ],
                isExpanded: true,
                billDay: billDay.value,
                dateRange: '' // Will be computed
            };
            
            // Calculate date range for new period
            newPeriod.dateRange = calculatePeriodDateRange(newPeriod);

            periodsList.value.push(newPeriod);
            activePeriodIndex.value = periodsList.value.length - 1;
            
            // Update current period data
            updateActivePeriodData();
            
            // Calculate summary for the new period
            setTimeout(() => {
                calculatePeriodSummary(activePeriodIndex.value);
            }, 100);
            
            // Save to localStorage
            saveToLocalStorage();
            
            showNotification('New period added', 'success');
        };

        const togglePeriod = (index) => {
            periodsList.value[index].isExpanded = !periodsList.value[index].isExpanded;
        };

        const setActivePeriod = (index) => {
            activePeriodIndex.value = index;
            updateActivePeriodData();
            // Recalculate summary for the newly active period if it doesn't exist
            if (!periodSummaries.value[index] || !periodSummaries.value[index].can_calculate) {
                calculatePeriodSummary(index);
            } else {
                // Update summaryData to match the selected period
                summaryData.value = periodSummaries.value[index];
            }
        };

        const updateActivePeriodData = () => {
            if (periodsList.value.length > 0 && activePeriodIndex.value >= 0) {
                const activePeriod = periodsList.value[activePeriodIndex.value];
                startMonth.value = activePeriod.startMonth;
                meterReadings.value = JSON.parse(JSON.stringify(activePeriod.readings)); // Deep copy
                // Set meter start reading from opening reading
                if (activePeriod.readings.length > 0 && activePeriod.readings[0].reading) {
                    meterStartReading.value = activePeriod.readings[0].reading;
                }
            }
        };

        const addPeriodReadingRow = (periodIndex) => {
            periodsList.value[periodIndex].readings.push({ day: null, reading: null });
            if (periodIndex === activePeriodIndex.value) {
                updateActivePeriodData();
            }
            // Recalculate summary for this period
            setTimeout(() => {
                calculatePeriodSummary(periodIndex);
            }, 100);
            saveToLocalStorage();
        };

        const removePeriodReading = (periodIndex, readingIndex) => {
            if (periodsList.value[periodIndex].readings.length > 1) {
                periodsList.value[periodIndex].readings.splice(readingIndex, 1);
                if (periodIndex === activePeriodIndex.value) {
                    updateActivePeriodData();
                }
                // Recalculate summary for this period
                setTimeout(() => {
                    calculatePeriodSummary(periodIndex);
                }, 100);
                saveToLocalStorage();
            }
        };

        const getPeriodClosingReading = (period) => {
            if (!period.readings || period.readings.length === 0) return 0;
            const sortedReadings = [...period.readings]
                .filter(r => r.day && r.reading !== null && r.reading !== undefined)
                .sort((a, b) => (a.day || 0) - (b.day || 0));
            if (sortedReadings.length === 0) return 0;
            return sortedReadings[sortedReadings.length - 1].reading || 0;
        };

        const getPeriodReadingStatus = (period, periodIndex) => {
            // Get period end date (bill_day of next period, or current date + bill_day)
            const periodStartDate = new Date(period.startMonth + '-01');
            const periodEndDate = new Date(periodStartDate);
            periodEndDate.setMonth(periodEndDate.getMonth() + 1);
            periodEndDate.setDate(Math.min(period.billDay || 20, periodEndDate.getDate()));
            
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const periodEnd = new Date(periodEndDate);
            periodEnd.setHours(0, 0, 0, 0);
            
            // If this is not the last period, it's closed (use closing reading)
            const isLastPeriod = periodIndex === periodsList.value.length - 1;
            if (!isLastPeriod) {
                // Check if there's an actual reading on bill_day grace window (19th, 20th, 21st)
                const billDay = period.billDay || 20;
                const billDayDate = new Date(periodEnd);
                const oneDayBefore = new Date(billDayDate);
                oneDayBefore.setDate(oneDayBefore.getDate() - 1);
                const oneDayAfter = new Date(billDayDate);
                oneDayAfter.setDate(oneDayAfter.getDate() + 1);
                
                const sortedReadings = [...period.readings]
                    .filter(r => r.day && r.reading !== null && r.reading !== undefined)
                    .sort((a, b) => (a.day || 0) - (b.day || 0));
                
                for (const reading of sortedReadings) {
                    const readingDate = new Date(reading.day);
                    readingDate.setHours(0, 0, 0, 0);
                    
                    if (readingDate.getTime() === oneDayBefore.getTime() || 
                        readingDate.getTime() === billDayDate.getTime() ||
                        readingDate.getTime() === oneDayAfter.getTime()) {
                        return 'ACTUAL';
                    }
                }
                
                // Check if there's a reading after the grace window (becomes CALCULATED)
                const graceWindowEnd = new Date(oneDayAfter);
                graceWindowEnd.setDate(graceWindowEnd.getDate() + 1);
                
                for (const reading of sortedReadings) {
                    const readingDate = new Date(reading.day);
                    readingDate.setHours(0, 0, 0, 0);
                    if (readingDate > graceWindowEnd) {
                        return 'CALCULATED';
                    }
                }
                
                // Otherwise, it's PROVISIONAL
                return 'PROVISIONAL';
            }
            
            // Current (last) period - check if we're past bill_day
            if (today > periodEnd) {
                // Past bill_day, check for actual reading in grace window
                const billDay = period.billDay || 20;
                const billDayDate = new Date(periodEnd);
                const oneDayBefore = new Date(billDayDate);
                oneDayBefore.setDate(oneDayBefore.getDate() - 1);
                const oneDayAfter = new Date(billDayDate);
                oneDayAfter.setDate(oneDayAfter.getDate() + 1);
                
                const sortedReadings = [...period.readings]
                    .filter(r => r.day && r.reading !== null && r.reading !== undefined)
                    .sort((a, b) => (a.day || 0) - (b.day || 0));
                
                for (const reading of sortedReadings) {
                    const readingDate = new Date(reading.day);
                    readingDate.setHours(0, 0, 0, 0);
                    
                    if (readingDate.getTime() === oneDayBefore.getTime() || 
                        readingDate.getTime() === billDayDate.getTime() ||
                        readingDate.getTime() === oneDayAfter.getTime()) {
                        return 'ACTUAL';
                    }
                }
                
                // Past bill_day - check if we have at least 2 readings (opening + one actual)
                // If less than 2 readings, cannot be PROVISIONAL, must be PROJECTED
                const sortedReadingsCheck = [...period.readings]
                    .filter(r => r.day && r.reading !== null && r.reading !== undefined)
                    .sort((a, b) => (a.day || 0) - (b.day || 0));
                
                if (sortedReadingsCheck.length < 2) {
                    // Less than 2 readings means we can't calculate daily usage, so it's PROJECTED
                    return 'PROJECTED';
                }
                
                // Past bill_day with at least 2 readings = PROVISIONAL
                return 'PROVISIONAL';
            }
            
            // Before bill_day = PROJECTED
            return 'PROJECTED';
        };

        const getPeriodReadingStatusLabel = (period) => {
            if (!periodsList.value) return 'Closing Reading';
            const periodIndex = periodsList.value.findIndex(p => p.id === period.id);
            if (periodIndex < 0) return 'Closing Reading';
            
            const status = getPeriodReadingStatus(period, periodIndex);
            switch (status) {
                case 'PROJECTED': return 'Projected Reading';
                case 'PROVISIONAL': return 'Provisional Reading';
                case 'CALCULATED': return 'Calculated Reading';
                case 'ACTUAL': return 'Actual Reading';
                default: return 'Closing Reading';
            }
        };

        const getPeriodProjectedReading = (period) => {
            if (!periodsList.value) return 0;
            const periodIndex = periodsList.value.findIndex(p => p.id === period.id);
            if (periodIndex < 0) return 0;
            
            // First, try to use the backend calculated value if available
            const summaryValue = getPeriodSummaryValue(periodIndex, 'projected_reading');
            if (summaryValue !== null && summaryValue !== undefined) {
                return summaryValue;
            }
            
            // Fallback to frontend calculation if backend value not available
            if (!period.readings || period.readings.length === 0) return 0;
            
            const sortedReadings = [...period.readings]
                .filter(r => r.day && r.reading !== null && r.reading !== undefined)
                .sort((a, b) => (a.day || 0) - (b.day || 0));
            
            if (sortedReadings.length === 0) return 0;
            
            const openingReading = sortedReadings[0].reading || 0;
            const lastReading = sortedReadings[sortedReadings.length - 1];
            
            const status = getPeriodReadingStatus(period, periodIndex);
            
            // If we have actual readings and it's not PROJECTED, use the last reading
            if (status !== 'PROJECTED' && sortedReadings.length > 1) {
                return lastReading.reading || 0;
            }
            
            // For PROJECTED status, calculate based on 1000 litres per day
            if (status === 'PROJECTED') {
                const periodStartDate = new Date(period.startMonth + '-01');
                periodStartDate.setDate(Math.min(period.billDay || 20, periodStartDate.getDate()));
                
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                // If we have multiple readings, calculate daily average from them
                if (sortedReadings.length >= 2) {
                    const firstDate = new Date(sortedReadings[0].day);
                    const lastDate = new Date(lastReading.day);
                    const daysDiff = Math.max(1, Math.round((lastDate - firstDate) / (1000 * 60 * 60 * 24)));
                    const consumption = lastReading.reading - openingReading;
                    const dailyAverage = consumption / daysDiff;
                    
                    // Project from last reading to period end or today
                    const periodEndDate = new Date(periodStartDate);
                    periodEndDate.setMonth(periodEndDate.getMonth() + 1);
                    periodEndDate.setDate(Math.min(period.billDay || 20, periodEndDate.getDate()));
                    
                    const daysFromLastReading = Math.max(0, Math.round((Math.min(today, periodEndDate) - lastDate) / (1000 * 60 * 60 * 24)));
                    return lastReading.reading + (dailyAverage * daysFromLastReading);
                } else {
                    // Use 1000 litres per day default
                    const daysFromStart = Math.max(0, Math.round((today - periodStartDate) / (1000 * 60 * 60 * 24)));
                    return openingReading + (1000 * daysFromStart);
                }
            }
            
            // For other statuses, return the last reading value
            return lastReading.reading || 0;
        };

        const getPeriodDays = (periodStartMonth) => {
            if (!periodStartMonth || !billDay.value) return [];
            
            const startDate = new Date(periodStartMonth + '-01');
            const endDate = new Date(startDate);
            endDate.setMonth(endDate.getMonth() + 1);
            
            const startDayNum = Math.min(billDay.value, new Date(startDate.getFullYear(), startDate.getMonth() + 1, 0).getDate());
            const endDayNum = Math.min(billDay.value, new Date(endDate.getFullYear(), endDate.getMonth() + 1, 0).getDate());
            
            startDate.setDate(startDayNum);
            endDate.setDate(endDayNum);
            
            const days = [];
            const currentDate = new Date(startDate);
            
            const getOrdinalSuffix = (day) => {
                if (day > 3 && day < 21) return 'th';
                switch (day % 10) {
                    case 1: return 'st';
                    case 2: return 'nd';
                    case 3: return 'rd';
                    default: return 'th';
                }
            };
            
            while (currentDate <= endDate) {
                const day = currentDate.getDate();
                const monthName = currentDate.toLocaleDateString('en-GB', { month: 'short' });
                const year = currentDate.getFullYear();
                const dayWithSuffix = `${day}${getOrdinalSuffix(day)}`;
                
                days.push({
                    value: currentDate.getTime(),
                    label: `${dayWithSuffix} ${monthName} ${year}`,
                    date: new Date(currentDate),
                    dayNumber: day
                });
                
                currentDate.setDate(currentDate.getDate() + 1);
            }
            
            return days;
        };

        const calculatePeriodDateRange = (period) => {
            if (!period.startMonth || !period.billDay) return '';
            
            const startDate = new Date(period.startMonth + '-01');
            const endDate = new Date(startDate);
            endDate.setMonth(endDate.getMonth() + 1);
            
            const startDayNum = Math.min(period.billDay, new Date(startDate.getFullYear(), startDate.getMonth() + 1, 0).getDate());
            const endDayNum = Math.min(period.billDay, new Date(endDate.getFullYear(), endDate.getMonth() + 1, 0).getDate());
            
            startDate.setDate(startDayNum);
            endDate.setDate(endDayNum);
            
            const getOrdinalSuffix = (day) => {
                if (day > 3 && day < 21) return 'th';
                switch (day % 10) {
                    case 1: return 'st';
                    case 2: return 'nd';
                    case 3: return 'rd';
                    default: return 'th';
                }
            };
            
            const startDay = startDate.getDate();
            const startMonthName = startDate.toLocaleDateString('en-GB', { month: 'short' });
            const startYear = startDate.getFullYear();
            const startDayWithSuffix = `${startDay}${getOrdinalSuffix(startDay)}`;
            
            const endDay = endDate.getDate();
            const endMonthName = endDate.toLocaleDateString('en-GB', { month: 'short' });
            const endYear = endDate.getFullYear();
            const endDayWithSuffix = `${endDay}${getOrdinalSuffix(endDay)}`;
            
            return `${startDayWithSuffix} ${startMonthName} ${startYear} to ${endDayWithSuffix} ${endMonthName} ${endYear}`;
        };

        const currentPeriodDateRange = computed(() => {
            if (periodsList.value.length === 0 || activePeriodIndex.value < 0) return '';
            const activePeriod = periodsList.value[activePeriodIndex.value];
            if (!activePeriod.startMonth || !billDay.value) return '';
            
            const startDate = new Date(activePeriod.startMonth + '-01');
            const endDate = new Date(startDate);
            endDate.setMonth(endDate.getMonth() + 1);
            
            const startDayNum = Math.min(billDay.value, new Date(startDate.getFullYear(), startDate.getMonth() + 1, 0).getDate());
            const endDayNum = Math.min(billDay.value, new Date(endDate.getFullYear(), endDate.getMonth() + 1, 0).getDate());
            
            startDate.setDate(startDayNum);
            endDate.setDate(endDayNum);
            
            const getOrdinalSuffix = (day) => {
                if (day > 3 && day < 21) return 'th';
                switch (day % 10) {
                    case 1: return 'st';
                    case 2: return 'nd';
                    case 3: return 'rd';
                    default: return 'th';
                }
            };
            
            const startDay = startDate.getDate();
            const startMonthName = startDate.toLocaleDateString('en-GB', { month: 'short' });
            const startYear = startDate.getFullYear();
            const startDayWithSuffix = `${startDay}${getOrdinalSuffix(startDay)}`;
            
            const endDay = endDate.getDate();
            const endMonthName = endDate.toLocaleDateString('en-GB', { month: 'short' });
            const endYear = endDate.getFullYear();
            const endDayWithSuffix = `${endDay}${getOrdinalSuffix(endDay)}`;
            
            return `${startDayWithSuffix} ${startMonthName} ${startYear} to ${endDayWithSuffix} ${endMonthName} ${endYear}`;
        });

        // Watch active period readings and sync back to periodsList, then recalculate and save
        watch(meterReadings, () => {
            if (periodsList.value.length > 0 && activePeriodIndex.value >= 0) {
                periodsList.value[activePeriodIndex.value].readings = JSON.parse(JSON.stringify(meterReadings.value));
                // Recalculate summary for the active period
                setTimeout(() => {
                    calculatePeriodSummary(activePeriodIndex.value);
                }, 100);
                saveToLocalStorage();
            }
        }, { deep: true });

        // Watch all periods' readings to recalculate summaries when they change
        // IMPORTANT: Since readings are GLOBAL and periods depend on each other (CO continuity),
        // we must recalculate ALL periods sequentially (0, 1, 2...) when any reading changes
        watch(() => periodsList.value.map(p => p.readings), async () => {
            // Recalculate summaries for all periods sequentially when any period's readings change
            // Sequential calculation ensures each period uses the previous period's closing reading
            for (let i = 0; i < periodsList.value.length; i++) {
                await calculatePeriodSummary(i);
            }
        }, { deep: true });

        // Watch active period index and recalculate when it changes
        watch(activePeriodIndex, () => {
            triggerSummaryCalculation();
        });

        // Watch periodsList changes (new periods added) and recalculate
        watch(periodsList, () => {
            triggerSummaryCalculation();
        }, { deep: true });

        const linkTariffTemplate = async () => {
            if (!selectedTariffForLinking.value) {
                showNotification('Please select a tariff template', 'warning');
                return;
            }

            loadingTariffDetails.value = true;
            try {
                const response = await fetch(props.apiUrls.tariffTemplateDetails, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': props.csrfToken
                    },
                    body: JSON.stringify({
                        tariff_template_id: selectedTariffForLinking.value
                    })
                });

                const result = await response.json();
                if (result.success && result.data) {
                    // Store original tiers before linking
                    originalTiers.value = JSON.parse(JSON.stringify(tiers.value));
                    
                    // Replace tiers with tariff template tiers
                    tiers.value = result.data.tiers.map(tier => ({
                        tier_number: tier.tier_number,
                        max_units: tier.max_units,
                        rate_per_unit: tier.rate_per_unit,
                        label: tier.label || `Tier ${tier.tier_number}`
                    }));

                    // Store linked template info
                    const template = tariffTemplateOptions.value.find(t => t.value === selectedTariffForLinking.value);
                    linkedTariffTemplate.value = {
                        id: result.data.id,
                        template_name: result.data.template_name || template.label,
                        vat_percentage: result.data.vat_percentage
                    };

                    showLinkTariffModal.value = false;
                    selectedTariffForLinking.value = null;
                    showNotification('Tariff template linked successfully', 'success');
                    // Recalculate summary after linking tariff template
                    triggerSummaryCalculation();
                } else {
                    showNotification(result.error || 'Failed to load tariff template', 'danger');
                }
            } catch (error) {
                showNotification('Error loading tariff template: ' + error.message, 'danger');
            } finally {
                loadingTariffDetails.value = false;
            }
        };

        const unlinkTariffTemplate = () => {
            // Restore original tiers if they exist
            if (originalTiers.value.length > 0) {
                tiers.value = JSON.parse(JSON.stringify(originalTiers.value));
            } else {
                // Otherwise clear tiers
                tiers.value = [];
            }
            linkedTariffTemplate.value = null;
            originalTiers.value = [];
            showNotification('Tariff template unlinked', 'success');
        };

        const handleSeedData = async () => {
            loading.value = true;
            try {
                const response = await fetch(props.apiUrls.seed, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': props.csrfToken
                    },
                    body: JSON.stringify({ bill_day: billDay.value })
                });

                const result = await response.json();
                if (result.success && result.data) {
                    periods.value = result.data.periods || [];
                    tiers.value = result.data.tiers || [];
                    billDay.value = result.data.bill_day || 20;
                    
                    // Initialize meter readings from seed data if available
                    if (periods.value.length > 0) {
                        const firstPeriod = periods.value[0];
                        meterReadings.value = [
                            { day: null, reading: firstPeriod.opening_reading || 0 },
                            { day: null, reading: firstPeriod.closing_reading || 0 }
                        ];
                        meterStartReading.value = firstPeriod.opening_reading || 0;
                    }
                    
                    showNotification('Seed data loaded successfully', 'success');
                    // Recalculate summary after loading seed data
                    triggerSummaryCalculation();
                } else {
                    showNotification(result.error || 'Failed to load seed data', 'danger');
                }
            } catch (error) {
                showNotification('Error loading seed data: ' + error.message, 'danger');
            } finally {
                loading.value = false;
            }
        };

        const loadTariffTemplates = async () => {
            loadingTemplates.value = true;
            try {
                const response = await fetch(props.apiUrls.tariffTemplates, {
                    headers: {
                        'X-CSRF-TOKEN': props.csrfToken
                    }
                });
                const result = await response.json();
                if (result.success && result.data) {
                    // Handle both result.data (array) and result.data.data (nested)
                    const templates = Array.isArray(result.data) ? result.data : (result.data.data || []);
                    tariffTemplateOptions.value = templates.map(t => ({
                        value: t.id,
                        label: t.template_name,
                        region: t.region
                    }));
                }
            } catch (error) {
                showNotification('Error loading tariff templates: ' + error.message, 'danger');
            } finally {
                loadingTemplates.value = false;
            }
        };

        const handleGenerateBill = async () => {
            if (!selectedPeriodId.value || !selectedTariffTemplateId.value) {
                showNotification('Please select both a period and a tariff template', 'warning');
                return;
            }

            if (!canCompute.value) {
                showNotification('Please ensure all readings have both Day and Reading values before computing', 'warning');
                return;
            }

            generatingBill.value = true;
            generatedBill.value = null;
            generateBillError.value = null;

            try {
                // Get the projected reading for the active period
                const activePeriod = periodsList.value[activePeriodIndex.value];
                const projectedReadingValue = getPeriodSummaryValue(activePeriodIndex.value, 'projected_reading') || 
                                             (activePeriod?.readings[0]?.reading || meterStartReading.value);
                
                // Create a period object from current meter readings
                const periodData = {
                    period_id: selectedPeriodId.value,
                    opening_reading: activePeriod?.readings[0]?.reading || meterStartReading.value,
                    closing_reading: projectedReadingValue,
                    period_start: activePeriod?.startMonth ? `${activePeriod.startMonth}-01` : (startMonth.value ? `${startMonth.value}-01` : new Date().toISOString().split('T')[0]),
                    period_end: new Date().toISOString().split('T')[0]
                };

                const response = await fetch(props.apiUrls.generateBill, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': props.csrfToken
                    },
                    body: JSON.stringify({
                        period_id: selectedPeriodId.value,
                        tariff_template_id: selectedTariffTemplateId.value,
                        periods: [periodData]
                    })
                });

                const result = await response.json();
                if (result.success && result.data && result.data.data) {
                    generatedBill.value = result.data.data;
                    billStatus.value = generatedBill.value.period?.status || 'Provisional';
                    showNotification('Bill generated successfully', 'success');
                } else {
                    generateBillError.value = result.error || 'Failed to generate bill';
                    showNotification(generateBillError.value, 'danger');
                }
            } catch (error) {
                generateBillError.value = error.message || 'Failed to generate bill';
                showNotification(generateBillError.value, 'danger');
            } finally {
                generatingBill.value = false;
            }
        };

        const viewFullBill = () => {
            if (generatedBill.value) {
                // TODO: Implement full bill view modal or page
                alert('Full bill details:\n\n' + JSON.stringify(generatedBill.value, null, 2));
            }
        };

        const showNotification = (message, type = 'info') => {
            // Simple alert for now - could be enhanced with Bootstrap toasts
            if (type === 'danger' || type === 'error') {
                alert('Error: ' + message);
            } else {
                console.log(type + ': ' + message);
            }
        };

        // Watch for changes and recalculate summaries for all periods
        let summaryDebounceTimer = null;
        const triggerSummaryCalculation = () => {
            if (summaryDebounceTimer) {
                clearTimeout(summaryDebounceTimer);
            }
            summaryDebounceTimer = setTimeout(() => {
                calculateSummary();
            }, 300); // Debounce 300ms
        };

        // Watch tiers and bill day to recalculate all periods (period-specific inputs are handled in watch statements above)
        watch([tiers, billDay], async () => {
            // Recalculate all periods sequentially when tiers or bill day changes
            // Sequential calculation ensures each period uses the previous period's closing reading
            for (let i = 0; i < periodsList.value.length; i++) {
                await calculatePeriodSummary(i);
            }
        }, { deep: true });

        // Initialize first period
        const initializeFirstPeriod = () => {
            const now = new Date();
            const defaultStartMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
            startMonth.value = defaultStartMonth;
            
            const firstPeriod = {
                id: 1,
                label: 'Period 1',
                startMonth: defaultStartMonth,
                readings: [
                    { day: null, reading: meterStartReading.value || 0 }
                ],
                isExpanded: true,
                billDay: billDay.value,
                dateRange: ''
            };
            
            firstPeriod.dateRange = calculatePeriodDateRange(firstPeriod);
            
            periodsList.value = [firstPeriod];
            activePeriodIndex.value = 0;
            updateActivePeriodData();
        };

        // Load tariff templates on mount
        onMounted(() => {
            loadTariffTemplates();
            
            // Try to load from localStorage first
            const loaded = loadFromLocalStorage();
            
            if (!loaded) {
                // Initialize with default first period if nothing was loaded
                initializeFirstPeriod();
            }
            
            // Calculate summaries for all periods after mount
            setTimeout(() => {
                for (let i = 0; i < periodsList.value.length; i++) {
                    calculatePeriodSummary(i);
                }
            }, 500); // Small delay to ensure component is fully mounted
        });

        return {
            startMonth,
            meterStartReading,
            billDay,
            meterStartDay,
            tiers,
            periods,
            meterReadings,
            loading,
            loadingTemplates,
            generatingBill,
            generatedBill,
            generateBillError,
            selectedPeriodId,
            selectedTariffTemplateId,
            tariffTemplateOptions,
            billStatus,
            dateRange,
            projectedReading,
            dailyUsage,
            dailyCost,
            projectedTotal,
            hasValidPeriod,
            periodDays,
            canCompute,
            linkedTariffTemplate,
            showLinkTariffModal,
            selectedTariffForLinking,
            loadingTariffDetails,
            currentPeriodDateRange,
            periodsList,
            activePeriodIndex,
            formatNumber,
            formatNumberWithSpaces,
            addNewPeriod,
            deleteLastPeriod,
            togglePeriod,
            setActivePeriod,
            addPeriodReadingRow,
            removePeriodReading,
            getPeriodClosingReading,
            getPeriodProjectedReading,
            getPeriodReadingStatus,
            getPeriodReadingStatusLabel,
            getPeriodDays,
            calculatePeriodDateRange,
            periodSummaryData,
            getPeriodSummaryValue,
            getPeriodStatusBadgeClass,
            getPeriodClosingLabel,
            checkAndReconcile,
            confirmLowReading,
            saveToLocalStorage,
            linkTariffTemplate,
            unlinkTariffTemplate,
            addTier,
            removeTier,
            addReadingRow,
            removeReading,
            handleSeedData,
            calculateSummary,
            calculatePeriodSummary,
            handleGenerateBill,
            viewFullBill
        };
    }
};
</script>

<style scoped>
.billing-calculator-wrapper {
    padding: 0;
}

.table-sm td, .table-sm th {
    padding: 0.4rem;
    font-size: 0.85rem;
}

.alert-danger {
    background-color: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
}

.alert-danger {
    background-color: #dc3545 !important;
    border-color: #dc3545 !important;
}

.alert-danger .h3 {
    color: white;
    font-weight: bold;
}

.form-control-sm {
    font-size: 0.875rem;
}

.card-header {
    font-size: 0.95rem;
}

/* Ensure no horizontal scroll */
.table-responsive {
    overflow-x: visible;
}

@media (max-width: 1200px) {
    .table-responsive {
        overflow-x: auto;
    }
}
</style>
