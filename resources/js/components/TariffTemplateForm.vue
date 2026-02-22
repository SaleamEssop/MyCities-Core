<template>
    <div class="cust-form-wrapper">
        <div class="row">
            <div class="col-md-12">
                <form method="POST" :action="submitUrl" @submit="handleSubmit">
                    <input type="hidden" name="_token" :value="csrfToken" />
                    <input v-if="formData.id" type="hidden" name="id" :value="formData.id" />
                    
                    <!-- Basic Info -->
                    <div class="form-group" data-component="form-group" data-component-id="tariff-template-name-group-1">
                        <label data-component="form-label" data-component-id="tariff-template-name-label-1"><strong>Template Name :</strong></label>
                        <input class="form-control" type="text" placeholder="Template name" name="template_name" v-model="formData.template_name" data-component="text-input" data-component-id="tariff-template-name-input-1" data-component-description="Template Name" />
                    </div>
                    <div class="form-group" data-component="form-group" data-component-id="tariff-template-region-group-1">
                        <label data-component="form-label" data-component-id="tariff-template-region-label-1"><strong>Select Region :</strong></label>
                        <select class="form-control" name="region_id" v-model="formData.region_id" @change="onRegionChange" data-component="select-input" data-component-id="tariff-template-region-select-1" data-component-description="Select Region">
                            <option value="">Please select Region</option>
                            <option v-for="region in regions" :key="region.id" :value="region.id" :data-component="'select-option'" :data-component-id="'tariff-template-region-option-' + region.id">{{ region.municipality ? region.municipality + ' (' + region.province + ')' : region.name }}</option>
                        </select>
                    </div>
                    <div class="form-group" data-component="form-group" data-component-id="tariff-template-start-date-group-1">
                        <label data-component="form-label" data-component-id="tariff-template-start-date-label-1"><strong>Applicable Start Date :</strong></label>
                        <input class="form-control" type="date" placeholder="Start Date" name="start_date" v-model="formData.start_date" data-component="date-input" data-component-id="tariff-template-start-date-input-1" data-component-description="Applicable Start Date" />
                    </div>
                    <div class="form-group" data-component="form-group" data-component-id="tariff-template-end-date-group-1">
                        <label data-component="form-label" data-component-id="tariff-template-end-date-label-1"><strong>Applicable End Date :</strong></label>
                        <input class="form-control" type="date" placeholder="End Date" name="end_date" v-model="formData.end_date" data-component="date-input" data-component-id="tariff-template-end-date-input-1" data-component-description="Applicable End Date" />
                    </div>
                    <div class="form-group" style="display:none;" data-component="form-group" data-component-id="tariff-template-water-email-group-1">
                        <label data-component="form-label" data-component-id="tariff-template-water-email-label-1"><strong>Water Email :</strong></label>
                        <input class="form-control" type="email" placeholder="Water Email" name="water_email" v-model="formData.water_email" data-component="email-input" data-component-id="tariff-template-water-email-input-1" data-component-description="Water Email" />
                    </div>
                    <div class="form-group" style="display:none;" data-component="form-group" data-component-id="tariff-template-electricity-email-group-1">
                        <label data-component="form-label" data-component-id="tariff-template-electricity-email-label-1"><strong>Electricity Email :</strong></label>
                        <input class="form-control" type="email" placeholder="Electricity Email" name="electricity_email" v-model="formData.electricity_email" data-component="email-input" data-component-id="tariff-template-electricity-email-input-1" data-component-description="Electricity Email" />
                    </div>
                    <div class="form-group" data-component="form-group" data-component-id="tariff-template-vat-group-1">
                        <label data-component="form-label" data-component-id="tariff-template-vat-label-1"><strong>Vat In Percentage :</strong></label>
                        <input class="form-control" type="text" placeholder="VAT Percentage" name="vat_percentage" v-model="formData.vat_percentage" @input="filterDecimal($event)" data-component="text-input" data-component-id="tariff-template-vat-input-1" data-component-description="Vat In Percentage" />
                    </div>

                    <!-- Billing Period Configuration -->
                    <hr>
                    <div class="form-group" style="background: #f8f9fa; padding: 20px; border: 2px solid #007bff; border-radius: 8px; margin-bottom: 20px;" data-component="form-group" data-component-id="tariff-template-billing-type-group-1">
                        <label style="font-size: 18px; font-weight: 700; color: #007bff; margin-bottom: 15px; display: block;" data-component="form-label" data-component-id="tariff-template-billing-type-label-1">
                            <strong>Billing Period Application Type :</strong>
                        </label>
                        <p style="color: #6c757d; margin-bottom: 15px;">Select how the tariff billing period is applied. <strong>Only one option may be selected.</strong></p>
                        
                        <div style="display: flex; gap: 30px; flex-wrap: wrap;" data-component="radio-group" data-component-id="tariff-template-billing-type-radio-group-1">
                            <!-- Period-to-Period Option -->
                            <div style="flex: 1; min-width: 280px; background: white; padding: 15px; border: 1px solid #dee2e6; border-radius: 6px;" :style="(formData.billing_type === 'PERIOD_TO_PERIOD' || formData.billing_type === 'MONTHLY') ? 'border-color: #28a745; border-width: 2px;' : ''" data-component="radio-option" data-component-id="tariff-template-billing-period-to-period-option-1">
                                <div style="margin-bottom: 10px;">
                                    <input type="radio" 
                                           id="billingPeriodToPeriod" 
                                           name="billing_type" 
                                           value="PERIOD_TO_PERIOD" 
                                           v-model="formData.billing_type"
                                           style="margin-right: 8px; transform: scale(1.2);"
                                           data-component="radio-input" 
                                           data-component-id="tariff-template-billing-period-to-period-input-1"
                                           data-component-description="Period-to-Period Application">
                                    <label for="billingPeriodToPeriod" style="font-weight: 600; cursor: pointer;" data-component="radio-label" data-component-id="tariff-template-billing-period-to-period-label-1">
                                        Period-to-Period Application
                                    </label>
                                </div>
                                <ul style="font-size: 13px; color: #6c757d; padding-left: 20px; margin: 0;">
                                    <li>Tariff applied on period-to-period basis</li>
                                    <li>Billing period: Start date to end date of period</li>
                                    <li>Periods close automatically at period-end</li>
                                    <li style="color: #dc3545;"><strong>No "Close Period" button shown</strong></li>
                                </ul>
                            </div>
                            
                            <!-- Date-to-Date Option -->
                            <div style="flex: 1; min-width: 280px; background: white; padding: 15px; border: 1px solid #dee2e6; border-radius: 6px;" :style="formData.billing_type === 'DATE_TO_DATE' ? 'border-color: #28a745; border-width: 2px;' : ''" data-component="radio-option" data-component-id="tariff-template-billing-date-to-date-option-1">
                                <div style="margin-bottom: 10px;">
                                    <input type="radio" 
                                           id="billingDateToDate" 
                                           name="billing_type" 
                                           value="DATE_TO_DATE" 
                                           v-model="formData.billing_type"
                                           style="margin-right: 8px; transform: scale(1.2);"
                                           data-component="radio-input" 
                                           data-component-id="tariff-template-billing-date-to-date-input-1"
                                           data-component-description="Date-to-Date Application">
                                    <label for="billingDateToDate" style="font-weight: 600; cursor: pointer;" data-component="radio-label" data-component-id="tariff-template-billing-date-to-date-label-1">
                                        Date-to-Date Application
                                    </label>
                                </div>
                                <ul style="font-size: 13px; color: #6c757d; padding-left: 20px; margin: 0;">
                                    <li>Tariff applied between specific dates</li>
                                    <li>Billing aligns to actual meter readings</li>
                                    <li>User manually closes current period</li>
                                    <li style="color: #28a745;"><strong>Permanent "Close Period" button shown</strong></li>
                                </ul>
                            </div>
                        </div>
                        
                        <div v-if="!formData.billing_type" style="background: #fff3cd; color: #856404; padding: 10px 15px; border-radius: 4px; margin-top: 15px;">
                            <strong>Required:</strong> Please select a billing period type.
                        </div>
                    </div>

                    <hr>
                    <label style="font-size: 24px;font-weight: 800;" data-component="section-title" data-component-id="tariff-template-user-input-title-1"><strong>User Input : </strong></label>
                    <div class="form-group" data-component="form-group" data-component-id="tariff-template-ratable-value-group-1">
                        <label data-component="form-label" data-component-id="tariff-template-ratable-value-label-1"><strong>Ratable Value :</strong></label>
                        <input class="form-control" type="text" placeholder="Ratable Value" name="ratable_value" v-model="formData.ratable_value" @input="filterDecimal($event)" data-component="text-input" data-component-id="tariff-template-ratable-value-input-1" data-component-description="Ratable Value" />
                    </div>
                    <hr>
                    <div class="form-group" data-component="form-group" data-component-id="tariff-template-meter-type-group-1">
                        <label data-component="form-label" data-component-id="tariff-template-meter-type-label-1"><strong>Select Meter Type :</strong></label>
                        <input type="checkbox" name="is_water" id="waterchk" v-model="formData.is_water" data-component="checkbox-input" data-component-id="tariff-template-water-checkbox-1" data-component-description="Water Meter Type" /> <label for="waterchk" data-component="checkbox-label" data-component-id="tariff-template-water-checkbox-label-1">Water</label>
                        <input type="checkbox" name="is_electricity" id="electricitychk" v-model="formData.is_electricity" data-component="checkbox-input" data-component-id="tariff-template-electricity-checkbox-1" data-component-description="Electricity Meter Type" /> <label for="electricitychk" data-component="checkbox-label" data-component-id="tariff-template-electricity-checkbox-label-1">Electricity</label>
                    </div>
                    <div class="form-group" v-show="formData.is_water" data-component="form-group" data-component-id="tariff-template-water-used-group-1">
                        <label data-component="form-label" data-component-id="tariff-template-water-used-label-1"><strong>Water Used in KL :</strong></label>
                        <input class="form-control" type="text" placeholder="Water Usage" name="water_used" v-model="formData.water_used" @input="filterDecimal($event)" data-component="text-input" data-component-id="tariff-template-water-used-input-1" data-component-description="Water Used in KL" />
                    </div>
                    <div class="form-group" v-show="formData.is_electricity" data-component="form-group" data-component-id="tariff-template-electricity-used-group-1">
                        <label data-component="form-label" data-component-id="tariff-template-electricity-used-label-1"><strong>Electricity Used in KWH :</strong></label>
                        <input class="form-control" type="text" placeholder="Electricity Usage" name="electricity_used" v-model="formData.electricity_used" @input="filterDecimal($event)" data-component="text-input" data-component-id="tariff-template-electricity-used-input-1" data-component-description="Electricity Used in KWH" />
                    </div>

                    <!-- Water In Section -->
                    <div v-show="formData.is_water" class="water_in_section">
                        <hr>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="mr-2"><strong>Add Water In Cost (Tiers in Litres, Cost per KL) : </strong></label>
                                <button type="button" class="btn btn-sm btn-outline-secondary btn-circle" @click="addWaterInRow">
                                    <i class="fa fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div v-for="(row, index) in waterIn" :key="'waterin-' + index" class="row mb-2">
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Min (L)</label>
                                    <input class="form-control" type="text" placeholder="Min litres" :name="'waterin[' + index + '][min]'" v-model="row.min" @input="filterDecimal($event)" />
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Max (L)</label>
                                    <input class="form-control" type="text" placeholder="Max litres" :name="'waterin[' + index + '][max]'" v-model="row.max" @input="filterDecimal($event)" />
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Cost/KL</label>
                                    <input class="form-control" type="text" placeholder="R per KL" :name="'waterin[' + index + '][cost]'" v-model="row.cost" @input="filterDecimal($event)" />
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>KL Used</label>
                                    <input class="form-control" type="text" placeholder="KL" :value="calculateWaterInKL(index)" disabled />
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Total</label>
                                    <input class="form-control" type="text" placeholder="Total" :value="calculateWaterInRowTotal(index)" disabled />
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <button type="button" style="margin-top: 32px;" class="btn btn-outline-secondary btn-sm btn-circle" @click="removeWaterInRow(index)">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-2">
                            <div class="col-md-2 offset-md-10">
                                <label><strong>Water In Total :</strong></label>
                                <input class="form-control" type="text" placeholder="Total" :value="waterInTotal.toFixed(2)" disabled />
                            </div>
                        </div>

                        <div class="row mb-3 mt-4">
                            <div class="col-md-12">
                                <label class="mr-2"><strong>Water in related Cost : </strong></label>
                                <button type="button" class="btn btn-sm btn-outline-secondary btn-circle" @click="addWaterInAdditionalRow">
                                    <i class="fa fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div v-for="(row, index) in waterInAdditional" :key="'waterin-add-' + index" class="row mb-2">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Title</label>
                                    <input class="form-control" type="text" placeholder="Title" :name="'waterin_additional[' + index + '][title]'" v-model="row.title" />
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Percentage</label>
                                    <input class="form-control" type="text" placeholder="%" :name="'waterin_additional[' + index + '][percentage]'" v-model="row.percentage" @input="filterDecimal($event)" />
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Cost</label>
                                    <input class="form-control" type="text" placeholder="Cost" :name="'waterin_additional[' + index + '][cost]'" v-model="row.cost" @input="filterDecimal($event)" />
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Total</label>
                                    <input class="form-control" type="text" placeholder="Total" :value="calculateAdditionalRowTotal(row, formData.water_used)" disabled />
                                </div>
                            </div>
                            <div class="col-md-1">
                                <div class="form-group">
                                    <button type="button" style="margin-top: 32px;" class="btn btn-outline-secondary btn-sm btn-circle" @click="removeWaterInAdditionalRow(index)">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-2">
                            <div class="col-md-2 offset-md-10">
                                <label><strong>WaterIn related Total :</strong></label>
                                <input class="form-control" type="text" placeholder="Total" :value="waterInRelatedTotal.toFixed(2)" disabled />
                            </div>
                        </div>
                    </div>

                    <!-- Water Out Section -->
                    <div v-show="formData.is_water" class="water_out_section">
                        <hr>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="mr-2"><strong>Add Water Out Cost : </strong></label>
                                <button type="button" class="btn btn-sm btn-outline-secondary btn-circle" @click="addWaterOutRow">
                                    <i class="fa fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div v-for="(row, index) in waterOut" :key="'waterout-' + index" class="row mb-2">
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Min</label>
                                    <input class="form-control" type="text" placeholder="Min" :name="'waterout[' + index + '][min]'" v-model="row.min" @input="filterDecimal($event)" />
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Max</label>
                                    <input class="form-control" type="text" placeholder="Max" :name="'waterout[' + index + '][max]'" v-model="row.max" @input="filterDecimal($event)" />
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>%</label>
                                    <input class="form-control" type="text" placeholder="%" :name="'waterout[' + index + '][percentage]'" v-model="row.percentage" @input="filterDecimal($event)" />
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Cost</label>
                                    <input class="form-control" type="text" placeholder="Cost" :name="'waterout[' + index + '][cost]'" v-model="row.cost" @input="filterDecimal($event)" />
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Total</label>
                                    <input class="form-control" type="text" placeholder="Total" :value="calculateWaterOutRowTotal(index)" disabled />
                                </div>
                            </div>
                            <div class="col-md-1">
                                <div class="form-group">
                                    <button type="button" style="margin-top: 32px;" class="btn btn-outline-secondary btn-sm btn-circle" @click="removeWaterOutRow(index)">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-2">
                            <div class="col-md-2 offset-md-10">
                                <label><strong>Water Out Total :</strong></label>
                                <input class="form-control" type="text" placeholder="Total" :value="waterOutTotal.toFixed(2)" disabled />
                            </div>
                        </div>

                        <div class="row mb-3 mt-4">
                            <div class="col-md-12">
                                <label class="mr-2"><strong>Water out related Cost : </strong></label>
                                <button type="button" class="btn btn-sm btn-outline-secondary btn-circle" @click="addWaterOutAdditionalRow">
                                    <i class="fa fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div v-for="(row, index) in waterOutAdditional" :key="'waterout-add-' + index" class="row mb-2">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Title</label>
                                    <input class="form-control" type="text" placeholder="Title" :name="'waterout_additional[' + index + '][title]'" v-model="row.title" />
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>%</label>
                                    <input class="form-control" type="text" placeholder="%" :name="'waterout_additional[' + index + '][percentage]'" v-model="row.percentage" @input="filterDecimal($event)" />
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Cost</label>
                                    <input class="form-control" type="text" placeholder="Cost" :name="'waterout_additional[' + index + '][cost]'" v-model="row.cost" @input="filterDecimal($event)" />
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Total</label>
                                    <input class="form-control" type="text" placeholder="Total" :value="calculateAdditionalRowTotal(row, formData.water_used)" disabled />
                                </div>
                            </div>
                            <div class="col-md-1">
                                <div class="form-group">
                                    <button type="button" style="margin-top: 32px;" class="btn btn-outline-secondary btn-sm btn-circle" @click="removeWaterOutAdditionalRow(index)">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-2">
                            <div class="col-md-2 offset-md-10">
                                <label><strong>Waterout related Total:</strong></label>
                                <input class="form-control" type="text" placeholder="Total" :value="waterOutRelatedTotal.toFixed(2)" disabled />
                            </div>
                        </div>
                    </div>

                    <!-- Electricity Section -->
                    <div v-show="formData.is_electricity" class="ele_section">
                        <hr>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="mr-2"><strong>Electricity : </strong></label>
                                <button type="button" class="btn btn-sm btn-outline-secondary btn-circle" @click="addElectricityRow">
                                    <i class="fa fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div v-for="(row, index) in electricity" :key="'electricity-' + index" class="row mb-2">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Min (KWH)</label>
                                    <input class="form-control" type="text" placeholder="Min" :name="'electricity[' + index + '][min]'" v-model="row.min" @input="filterDecimal($event)" />
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Max (KWH)</label>
                                    <input class="form-control" type="text" placeholder="Max" :name="'electricity[' + index + '][max]'" v-model="row.max" @input="filterDecimal($event)" />
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Cost/KWH</label>
                                    <input class="form-control" type="text" placeholder="Cost" :name="'electricity[' + index + '][cost]'" v-model="row.cost" @input="filterDecimal($event)" />
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Total</label>
                                    <input class="form-control" type="text" placeholder="Total" :value="calculateElectricityRowTotal(index)" disabled />
                                </div>
                            </div>
                            <div class="col-md-1">
                                <div class="form-group">
                                    <button type="button" style="margin-top: 32px;" class="btn btn-outline-secondary btn-sm btn-circle" @click="removeElectricityRow(index)">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-2">
                            <div class="col-md-2 offset-md-10">
                                <label><strong>Electricity Total :</strong></label>
                                <input class="form-control" type="text" placeholder="Total" :value="electricityTotal.toFixed(2)" disabled />
                            </div>
                        </div>

                        <div class="row mb-3 mt-4">
                            <div class="col-md-12">
                                <label class="mr-2"><strong>Electricity related Cost : </strong></label>
                                <button type="button" class="btn btn-sm btn-outline-secondary btn-circle" @click="addElectricityAdditionalRow">
                                    <i class="fa fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div v-for="(row, index) in electricityAdditional" :key="'electricity-add-' + index" class="row mb-2">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Title</label>
                                    <input class="form-control" type="text" placeholder="Title" :name="'electricity_additional[' + index + '][title]'" v-model="row.title" />
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>%</label>
                                    <input class="form-control" type="text" placeholder="%" :name="'electricity_additional[' + index + '][percentage]'" v-model="row.percentage" @input="filterDecimal($event)" />
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Cost</label>
                                    <input class="form-control" type="text" placeholder="Cost" :name="'electricity_additional[' + index + '][cost]'" v-model="row.cost" @input="filterDecimal($event)" />
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Total</label>
                                    <input class="form-control" type="text" placeholder="Total" :value="calculateAdditionalRowTotal(row, formData.electricity_used)" disabled />
                                </div>
                            </div>
                            <div class="col-md-1">
                                <div class="form-group">
                                    <button type="button" style="margin-top: 32px;" class="btn btn-outline-secondary btn-sm btn-circle" @click="removeElectricityAdditionalRow(index)">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-2">
                            <div class="col-md-2 offset-md-10">
                                <label><strong>Electricity related Total :</strong></label>
                                <input class="form-control" type="text" placeholder="Total" :value="electricityRelatedTotal.toFixed(2)" disabled />
                            </div>
                        </div>
                    </div>

                    <!-- Fixed Costs Section (Customer Cannot Edit) -->
                    <hr>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="mr-2"><strong>Fixed Costs (Customer Cannot Edit) : </strong></label>
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-circle" @click="addFixedCostRow">
                                <i class="fa fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div v-for="(row, index) in fixedCosts" :key="'fixed-' + index" class="row mb-2">
                        <div class="col-md-5">
                            <div class="form-group">
                                <label>Cost Name</label>
                                <input class="form-control" type="text" placeholder="Cost Name" :name="'fixed_costs[' + index + '][name]'" v-model="row.name" />
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="form-group">
                                <label>Value (negative for rebates)</label>
                                <input class="form-control" type="text" placeholder="Value (negative for rebates)" :name="'fixed_costs[' + index + '][value]'" v-model="row.value" @input="filterDecimal($event)" />
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <button type="button" style="margin-top: 32px;" class="btn btn-outline-danger btn-sm btn-circle" @click="removeFixedCostRow(index)">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Editable Costs Section -->
                    <hr>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="mr-2"><strong>Customer Editable Costs (Customer Can Modify in App) : </strong></label>
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-circle" @click="addCustomerCostRow">
                                <i class="fa fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div v-for="(row, index) in customerCosts" :key="'customer-' + index" class="row mb-2">
                        <div class="col-md-5">
                            <div class="form-group">
                                <label>Cost Name</label>
                                <input class="form-control" type="text" placeholder="Cost Name" :name="'customer_costs[' + index + '][name]'" v-model="row.name" />
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="form-group">
                                <label>Default Value (can be empty)</label>
                                <input class="form-control" type="text" placeholder="Default Value (can be empty)" :name="'customer_costs[' + index + '][value]'" v-model="row.value" @input="filterDecimal($event)" />
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <button type="button" style="margin-top: 32px;" class="btn btn-outline-danger btn-sm btn-circle" @click="removeCustomerCostRow(index)">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Rates Section -->
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <label><strong>Rates :</strong></label>
                            <div class="form-group">
                                <input class="form-control" type="text" placeholder="Rates" name="vat_rate" v-model="formData.vat_rate" @input="filterDecimal($event)" />
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label><strong>Rates Rebate :</strong></label>
                            <div class="form-group">
                                <input class="form-control" type="text" placeholder="Rates Rebate" name="rates_rebate" v-model="formData.rates_rebate" @input="filterDecimal($event)" />
                            </div>
                        </div>
                    </div>

                    <!-- Professional Bill Preview Section -->
                    <hr class="my-5">
                    <div class="card">
                        <div class="card-header bg-dark text-white text-center">
                            <h4 class="mb-0">BILL PREVIEW</h4>
                        </div>
                        <div class="card-body">
                            <!-- Water Charges -->
                            <div v-if="formData.is_water">
                                <h5 class="border-bottom pb-2">WATER CHARGES</h5>
                                
                                <h6>Water In</h6>
                                <table class="table table-sm">
                                    <tbody>
                                        <tr v-for="(row, index) in waterIn" :key="'preview-wi-' + index">
                                            <td>Tier {{ index + 1 }} ({{ row.min || 0 }} - {{ row.max || 0 }} L)</td>
                                            <td class="text-right">{{ calculateWaterInKL(index) }} KL × R{{ row.cost || 0 }}</td>
                                            <td class="text-right">R{{ calculateWaterInRowTotal(index) }}</td>
                                        </tr>
                                        <tr class="font-weight-bold">
                                            <td colspan="2">Water In Subtotal</td>
                                            <td class="text-right">R{{ waterInTotal.toFixed(2) }}</td>
                                        </tr>
                                    </tbody>
                                </table>

                                <h6 v-if="waterInAdditional.length > 0 && waterInAdditional[0] && waterInAdditional[0].title">Water In Related Costs</h6>
                                <table class="table table-sm" v-if="waterInAdditional.length > 0 && waterInAdditional[0] && waterInAdditional[0].title">
                                    <tbody>
                                        <template v-for="(row, index) in waterInAdditional" :key="'preview-wia-' + index">
                                            <tr v-if="row && row.title">
                                                <td>{{ row.title }}</td>
                                                <td class="text-right">R{{ calculateAdditionalRowTotal(row, formData.water_used) }}</td>
                                            </tr>
                                        </template>
                                        <tr class="font-weight-bold">
                                            <td>Water In Related Subtotal</td>
                                            <td class="text-right">R{{ waterInRelatedTotal.toFixed(2) }}</td>
                                        </tr>
                                    </tbody>
                                </table>

                                <h6>Water Out</h6>
                                <table class="table table-sm">
                                    <tbody>
                                        <tr v-for="(row, index) in waterOut" :key="'preview-wo-' + index">
                                            <td>Tier {{ index + 1 }} ({{ row.min || 0 }} - {{ row.max || 0 }}) @ {{ row.percentage || 0 }}%</td>
                                            <td class="text-right">R{{ calculateWaterOutRowTotal(index) }}</td>
                                        </tr>
                                        <tr class="font-weight-bold">
                                            <td>Water Out Subtotal</td>
                                            <td class="text-right">R{{ waterOutTotal.toFixed(2) }}</td>
                                        </tr>
                                    </tbody>
                                </table>

                                <h6 v-if="waterOutAdditional.length > 0 && waterOutAdditional[0] && waterOutAdditional[0].title">Water Out Related Costs</h6>
                                <table class="table table-sm" v-if="waterOutAdditional.length > 0 && waterOutAdditional[0] && waterOutAdditional[0].title">
                                    <tbody>
                                        <template v-for="(row, index) in waterOutAdditional" :key="'preview-woa-' + index">
                                            <tr v-if="row && row.title">
                                                <td>{{ row.title }}</td>
                                                <td class="text-right">R{{ calculateAdditionalRowTotal(row, formData.water_used) }}</td>
                                            </tr>
                                        </template>
                                        <tr class="font-weight-bold">
                                            <td>Water Out Related Subtotal</td>
                                            <td class="text-right">R{{ waterOutRelatedTotal.toFixed(2) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Electricity Charges -->
                            <div v-if="formData.is_electricity">
                                <h5 class="border-bottom pb-2 mt-4">ELECTRICITY CHARGES</h5>
                                <table class="table table-sm">
                                    <tbody>
                                        <tr v-for="(row, index) in electricity" :key="'preview-el-' + index">
                                            <td>Tier {{ index + 1 }} ({{ row.min || 0 }} - {{ row.max || 0 }} KWH)</td>
                                            <td class="text-right">R{{ calculateElectricityRowTotal(index) }}</td>
                                        </tr>
                                        <tr class="font-weight-bold">
                                            <td>Electricity Subtotal</td>
                                            <td class="text-right">R{{ electricityTotal.toFixed(2) }}</td>
                                        </tr>
                                    </tbody>
                                </table>

                                <h6 v-if="electricityAdditional.length > 0 && electricityAdditional[0] && electricityAdditional[0].title">Electricity Related Costs</h6>
                                <table class="table table-sm" v-if="electricityAdditional.length > 0 && electricityAdditional[0] && electricityAdditional[0].title">
                                    <tbody>
                                        <template v-for="(row, index) in electricityAdditional" :key="'preview-ela-' + index">
                                            <tr v-if="row && row.title">
                                                <td>{{ row.title }}</td>
                                                <td class="text-right">R{{ calculateAdditionalRowTotal(row, formData.electricity_used) }}</td>
                                            </tr>
                                        </template>
                                        <tr class="font-weight-bold">
                                            <td>Electricity Related Subtotal</td>
                                            <td class="text-right">R{{ electricityRelatedTotal.toFixed(2) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Fixed Costs -->
                            <h5 class="border-bottom pb-2 mt-4">FIXED COSTS</h5>
                            <table class="table table-sm">
                                <tbody>
                                    <template v-for="(row, index) in fixedCosts" :key="'preview-fc-' + index">
                                        <tr v-if="row && row.name">
                                            <td>{{ row.name }}</td>
                                            <td class="text-right" :class="{ 'text-danger': parseFloat(row.value) < 0 }">
                                                R{{ parseFloat(row.value || 0).toFixed(2) }}
                                            </td>
                                        </tr>
                                    </template>
                                    <tr class="font-weight-bold">
                                        <td>Fixed Costs Subtotal</td>
                                        <td class="text-right">R{{ fixedCostsTotal.toFixed(2) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            
                            <!-- Customer Editable Costs -->
                            <h5 class="border-bottom pb-2 mt-4">CUSTOMER INPUT COSTS</h5>
                            <table class="table table-sm">
                                <tbody>
                                    <template v-for="(row, index) in customerCosts" :key="'preview-cc-' + index">
                                        <tr v-if="row && row.name">
                                            <td>{{ row.name }}</td>
                                            <td class="text-right text-muted">
                                                <span v-if="row.value">R{{ parseFloat(row.value).toFixed(2) }} *</span>
                                                <span v-else>[User Input]</span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                            <small class="text-muted">* Default value - customer can modify in app</small>
                            
                            <!-- Summary -->
                            <div class="bg-light p-3 mt-4">
                                <table class="table table-sm mb-0">
                                    <tbody>
                                        <tr>
                                            <td>Subtotal</td>
                                            <td class="text-right">R{{ subTotal.toFixed(2) }}</td>
                                        </tr>
                                        <tr>
                                            <td>VAT ({{ formData.vat_percentage }}%)</td>
                                            <td class="text-right">R{{ vatAmount.toFixed(2) }}</td>
                                        </tr>
                                        <tr>
                                            <td>Rates</td>
                                            <td class="text-right">R{{ parseFloat(formData.vat_rate || 0).toFixed(2) }}</td>
                                        </tr>
                                        <tr>
                                            <td>Rates Rebate</td>
                                            <td class="text-right text-danger">-R{{ parseFloat(formData.rates_rebate || 0).toFixed(2) }}</td>
                                        </tr>
                                        <tr class="font-weight-bold h5">
                                            <td>TOTAL</td>
                                            <td class="text-right">R{{ finalTotal.toFixed(2) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Submit buttons below the bill preview -->
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-warning btn-lg">{{ formData.id ? 'Save / Update' : 'Save Template' }}</button>
                        <a :href="cancelUrl" class="btn btn-secondary btn-lg ml-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';

const props = defineProps({
    regions: {
        type: Array,
        required: true
    },
    csrfToken: {
        type: String,
        required: true
    },
    submitUrl: {
        type: String,
        required: true
    },
    cancelUrl: {
        type: String,
        required: true
    },
    getEmailUrl: {
        type: String,
        default: ''
    },
    existingData: {
        type: Object,
        default: null
    }
});

// Form data
const formData = reactive({
    id: '',
    template_name: '',
    region_id: '',
    start_date: '',
    end_date: '',
    water_email: '',
    electricity_email: '',
    vat_percentage: '0',
    billing_day: '',
    read_day: '',
    ratable_value: '0',
    is_water: false,
    is_electricity: false,
    water_used: '1',
    electricity_used: '1',
    vat_rate: '0',
    rates_rebate: '0',
    billing_type: 'PERIOD_TO_PERIOD'  // Default to MONTHLY, options: 'MONTHLY' or 'DATE_TO_DATE'
});

// Dynamic arrays for tiered pricing - start empty, user adds rows as needed
const waterIn = ref([]);
const waterOut = ref([]);
const electricity = ref([]);
const waterInAdditional = ref([]);
const waterOutAdditional = ref([]);
const electricityAdditional = ref([]);

// New cost arrays - start empty, user adds rows as needed
const fixedCosts = ref([]);
const customerCosts = ref([]);

// Initialize from existing data if editing
onMounted(() => {
    if (props.existingData) {
        const data = props.existingData;
        formData.id = data.id || '';
        formData.template_name = data.template_name || '';
        formData.region_id = data.region_id || '';
        formData.start_date = data.start_date || '';
        formData.end_date = data.end_date || '';
        formData.water_email = data.water_email || '';
        formData.electricity_email = data.electricity_email || '';
        formData.vat_percentage = data.vat_percentage ?? '0';
        formData.ratable_value = data.ratable_value ?? '0';
        formData.is_water = data.is_water == 1;
        formData.is_electricity = data.is_electricity == 1;
        formData.water_used = data.water_used ?? '1';
        formData.electricity_used = data.electricity_used ?? '1';
        formData.vat_rate = data.vat_rate ?? '0';
        formData.rates_rebate = data.rates_rebate ?? '0';
        const billingType = data.billing_type || 'PERIOD_TO_PERIOD';
        formData.billing_type = (billingType === 'MONTHLY') ? 'PERIOD_TO_PERIOD' : billingType;

        // Load arrays
        if (Array.isArray(data.water_in) && data.water_in.length > 0) {
            waterIn.value = data.water_in.map(item => ({
                min: item.min ?? '',
                max: item.max ?? '',
                cost: item.cost ?? ''
            }));
        }
        if (Array.isArray(data.water_out) && data.water_out.length > 0) {
            waterOut.value = data.water_out.map(item => ({
                min: item.min ?? '',
                max: item.max ?? '',
                percentage: item.percentage ?? '',
                cost: item.cost ?? ''
            }));
        }
        if (Array.isArray(data.electricity) && data.electricity.length > 0) {
            electricity.value = data.electricity.map(item => ({
                min: item.min ?? '',
                max: item.max ?? '',
                cost: item.cost ?? ''
            }));
        }
        if (Array.isArray(data.waterin_additional) && data.waterin_additional.length > 0) {
            waterInAdditional.value = data.waterin_additional.map(item => ({
                title: item.title ?? '',
                percentage: item.percentage ?? '',
                cost: item.cost ?? ''
            }));
        }
        if (Array.isArray(data.waterout_additional) && data.waterout_additional.length > 0) {
            waterOutAdditional.value = data.waterout_additional.map(item => ({
                title: item.title ?? '',
                percentage: item.percentage ?? '',
                cost: item.cost ?? ''
            }));
        }
        if (Array.isArray(data.electricity_additional) && data.electricity_additional.length > 0) {
            electricityAdditional.value = data.electricity_additional.map(item => ({
                title: item.title ?? '',
                percentage: item.percentage ?? '',
                cost: item.cost ?? ''
            }));
        }
        // Load new cost arrays
        if (Array.isArray(data.fixed_costs) && data.fixed_costs.length > 0) {
            fixedCosts.value = data.fixed_costs.map(item => ({
                name: item.name ?? '',
                value: item.value ?? ''
            }));
        }
        if (Array.isArray(data.customer_costs) && data.customer_costs.length > 0) {
            customerCosts.value = data.customer_costs.map(item => ({
                name: item.name ?? '',
                value: item.value ?? ''
            }));
        }
    }
});

// Filter input to allow decimals AND negative sign (for rebates)
function filterDecimal(event) {
    const value = event.target.value.replace(/[^0-9.-]/g, '');
    event.target.value = value;
}

// Region change handler
function onRegionChange() {
    if (formData.region_id && props.getEmailUrl) {
        const url = props.getEmailUrl.replace('__ID__', formData.region_id);
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data) {
                    formData.water_email = data.water_email || '';
                    formData.electricity_email = data.electricity_email || '';
                }
            })
            .catch(() => {});
    }
}

// Add/Remove row functions - ALL rows can be removed (no minimum)
function addWaterInRow() {
    waterIn.value.push({ min: '', max: '', cost: '' });
}
function removeWaterInRow(index) {
    waterIn.value.splice(index, 1);
}

function addWaterOutRow() {
    waterOut.value.push({ min: '', max: '', percentage: '', cost: '' });
}
function removeWaterOutRow(index) {
    waterOut.value.splice(index, 1);
}

function addElectricityRow() {
    electricity.value.push({ min: '', max: '', cost: '' });
}
function removeElectricityRow(index) {
    electricity.value.splice(index, 1);
}

function addWaterInAdditionalRow() {
    waterInAdditional.value.push({ title: '', percentage: '', cost: '' });
}
function removeWaterInAdditionalRow(index) {
    waterInAdditional.value.splice(index, 1);
}

function addWaterOutAdditionalRow() {
    waterOutAdditional.value.push({ title: '', percentage: '', cost: '' });
}
function removeWaterOutAdditionalRow(index) {
    waterOutAdditional.value.splice(index, 1);
}

function addElectricityAdditionalRow() {
    electricityAdditional.value.push({ title: '', percentage: '', cost: '' });
}
function removeElectricityAdditionalRow(index) {
    electricityAdditional.value.splice(index, 1);
}

// New cost row functions - ALL rows can be removed
function addFixedCostRow() {
    fixedCosts.value.push({ name: '', value: '' });
}
function removeFixedCostRow(index) {
    fixedCosts.value.splice(index, 1);
}

function addCustomerCostRow() {
    customerCosts.value.push({ name: '', value: '' });
}
function removeCustomerCostRow(index) {
    customerCosts.value.splice(index, 1);
}

// Calculate KL used in a water tier (for display)
function calculateWaterInKL(index) {
    // Convert usage from KL to litres
    const usageLitres = parseFloat(formData.water_used) * 1000;
    
    // Calculate how many litres have been consumed by previous tiers
    let consumedLitres = 0;
    for (let i = 0; i < index; i++) {
        const tierMin = parseFloat(waterIn.value[i].min) || 0;
        const tierMax = parseFloat(waterIn.value[i].max) || 0;
        consumedLitres += (tierMax - tierMin);
    }
    
    const row = waterIn.value[index];
    const tierMin = parseFloat(row.min) || 0;
    const tierMax = parseFloat(row.max) || 0;
    
    const tierCapacity = tierMax - tierMin;
    const remainingUsage = usageLitres - consumedLitres;
    const litresInThisTier = Math.max(0, Math.min(tierCapacity, remainingUsage));
    
    // Convert litres to KL for display
    const klInThisTier = litresInThisTier / 1000;
    return klInThisTier.toFixed(2);
}

// FIXED: Calculate row totals for tiered pricing (Water In - KL to Litres conversion)
function calculateWaterInRowTotal(index) {
    // Convert usage from KL to litres
    const usageLitres = parseFloat(formData.water_used) * 1000;
    
    // Calculate how many litres have been consumed by previous tiers
    let consumedLitres = 0;
    for (let i = 0; i < index; i++) {
        const tierMin = parseFloat(waterIn.value[i].min) || 0;
        const tierMax = parseFloat(waterIn.value[i].max) || 0;
        consumedLitres += (tierMax - tierMin);
    }
    
    const row = waterIn.value[index];
    const tierMin = parseFloat(row.min) || 0;
    const tierMax = parseFloat(row.max) || 0;
    const costPerKL = parseFloat(row.cost) || 0;
    
    const tierCapacity = tierMax - tierMin;  // e.g., 6000 - 0 = 6000 litres
    const remainingUsage = usageLitres - consumedLitres;
    const litresInThisTier = Math.max(0, Math.min(tierCapacity, remainingUsage));
    
    // Convert litres to KL for cost calculation
    const klInThisTier = litresInThisTier / 1000;
    const total = klInThisTier * costPerKL;
    
    return total.toFixed(2);
}

// Calculate row totals for Water Out (with percentage) - same logic as water in but with percentage
function calculateWaterOutRowTotal(index) {
    // Convert usage from KL to litres
    const usageLitres = parseFloat(formData.water_used) * 1000;
    
    // Calculate how many litres have been consumed by previous tiers
    let consumedLitres = 0;
    for (let i = 0; i < index; i++) {
        const tierMin = parseFloat(waterOut.value[i].min) || 0;
        const tierMax = parseFloat(waterOut.value[i].max) || 0;
        consumedLitres += (tierMax - tierMin);
    }
    
    const row = waterOut.value[index];
    const tierMin = parseFloat(row.min) || 0;
    const tierMax = parseFloat(row.max) || 0;
    const percentage = parseFloat(row.percentage) || 100;
    const costPerKL = parseFloat(row.cost) || 0;
    
    const tierCapacity = tierMax - tierMin;
    const remainingUsage = usageLitres - consumedLitres;
    let litresInThisTier = Math.max(0, Math.min(tierCapacity, remainingUsage));
    
    // Apply percentage
    litresInThisTier = (percentage / 100) * litresInThisTier;
    
    // Convert litres to KL for cost calculation
    const klInThisTier = litresInThisTier / 1000;
    const total = klInThisTier * costPerKL;
    
    return total.toFixed(2);
}

// Calculate row totals for Electricity (no conversion needed - KWH to KWH)
function calculateElectricityRowTotal(index) {
    const electricityUsage = parseFloat(formData.electricity_used) || 0;
    let remaining = electricityUsage;
    
    for (let i = 0; i < index; i++) {
        const min = parseFloat(electricity.value[i].min) || 0;
        const max = parseFloat(electricity.value[i].max) || 0;
        const unitsInBracket = max - min;
        remaining -= unitsInBracket;
        if (remaining < 0) remaining = 0;
    }
    
    const row = electricity.value[index];
    const min = parseFloat(row.min) || 0;
    const max = parseFloat(row.max) || 0;
    const cost = parseFloat(row.cost) || 0;
    
    const unitsInBracket = max - min;
    let actualUnits = unitsInBracket;
    if (remaining - unitsInBracket < 0) {
        actualUnits = Math.max(0, remaining);
    }
    
    const total = Math.round(actualUnits * cost * 100) / 100;
    return total.toFixed(2);
}

// Calculate additional/related row totals
function calculateAdditionalRowTotal(row, usage) {
    const usageValue = parseFloat(usage) || 0;
    const percentage = parseFloat(row.percentage);
    const cost = parseFloat(row.cost) || 0;
    
    let effectiveUsage = usageValue;
    if (isNaN(percentage) || row.percentage === '') {
        effectiveUsage = 1;
    } else if (percentage !== 100) {
        effectiveUsage = usageValue - (usageValue * (percentage / 100));
    }
    
    const total = Math.round(effectiveUsage * cost * 100) / 100;
    return total.toFixed(2);
}

// Computed totals
const waterInTotal = computed(() => {
    let total = 0;
    for (let i = 0; i < waterIn.value.length; i++) {
        total += parseFloat(calculateWaterInRowTotal(i)) || 0;
    }
    return total;
});

const waterOutTotal = computed(() => {
    let total = 0;
    for (let i = 0; i < waterOut.value.length; i++) {
        total += parseFloat(calculateWaterOutRowTotal(i)) || 0;
    }
    return total;
});

const electricityTotal = computed(() => {
    let total = 0;
    for (let i = 0; i < electricity.value.length; i++) {
        total += parseFloat(calculateElectricityRowTotal(i)) || 0;
    }
    return total;
});

const waterInRelatedTotal = computed(() => {
    let total = 0;
    for (const row of waterInAdditional.value) {
        total += parseFloat(calculateAdditionalRowTotal(row, formData.water_used)) || 0;
    }
    return total;
});

const waterOutRelatedTotal = computed(() => {
    let total = 0;
    for (const row of waterOutAdditional.value) {
        total += parseFloat(calculateAdditionalRowTotal(row, formData.water_used)) || 0;
    }
    return total;
});

const electricityRelatedTotal = computed(() => {
    let total = 0;
    for (const row of electricityAdditional.value) {
        total += parseFloat(calculateAdditionalRowTotal(row, formData.electricity_used)) || 0;
    }
    return total;
});

// Fixed costs total (includes negative values for rebates)
const fixedCostsTotal = computed(() => {
    let total = 0;
    for (const row of fixedCosts.value) {
        if (row) {
            total += parseFloat(row.value) || 0;
        }
    }
    return total;
});

// Customer costs total (only for items with default values)
const customerCostsTotal = computed(() => {
    let total = 0;
    for (const row of customerCosts.value) {
        if (row) {
            total += parseFloat(row.value) || 0;
        }
    }
    return total;
});

const subTotal = computed(() => {
    return waterInTotal.value + waterInRelatedTotal.value + 
           waterOutTotal.value + waterOutRelatedTotal.value + 
           electricityTotal.value + electricityRelatedTotal.value + 
           fixedCostsTotal.value + customerCostsTotal.value;
});

const vatAmount = computed(() => {
    const vatPercentage = parseFloat(formData.vat_percentage) || 0;
    return Math.round((vatPercentage / 100) * subTotal.value * 100) / 100;
});

const finalTotal = computed(() => {
    const rates = parseFloat(formData.vat_rate) || 0;
    const ratesRebate = parseFloat(formData.rates_rebate) || 0;
    return Math.round((subTotal.value + vatAmount.value + rates - ratesRebate) * 100) / 100;
});

// Validate tier rows - all fields must be filled if a tier exists
function validateTiers() {
    const errors = [];
    
    // Validate Water In tiers
    waterIn.value.forEach((row, index) => {
        const hasAnyValue = row.min !== '' || row.max !== '' || row.cost !== '';
        if (hasAnyValue) {
            const missingFields = [];
            if (row.min === '' || row.min === null || row.min === undefined) missingFields.push('Min');
            if (row.max === '' || row.max === null || row.max === undefined) missingFields.push('Max');
            if (row.cost === '' || row.cost === null || row.cost === undefined) missingFields.push('Cost');
            
            if (missingFields.length > 0) {
                errors.push(`Water In Tier ${index + 1}: Missing ${missingFields.join(', ')}`);
            }
        }
    });
    
    // Validate Water Out tiers
    waterOut.value.forEach((row, index) => {
        const hasAnyValue = row.min !== '' || row.max !== '' || row.cost !== '';
        if (hasAnyValue) {
            const missingFields = [];
            if (row.min === '' || row.min === null || row.min === undefined) missingFields.push('Min');
            if (row.max === '' || row.max === null || row.max === undefined) missingFields.push('Max');
            if (row.cost === '' || row.cost === null || row.cost === undefined) missingFields.push('Cost');
            
            if (missingFields.length > 0) {
                errors.push(`Water Out Tier ${index + 1}: Missing ${missingFields.join(', ')}`);
            }
        }
    });
    
    // Validate Electricity tiers
    electricity.value.forEach((row, index) => {
        const hasAnyValue = row.min !== '' || row.max !== '' || row.cost !== '';
        if (hasAnyValue) {
            const missingFields = [];
            if (row.min === '' || row.min === null || row.min === undefined) missingFields.push('Min');
            if (row.max === '' || row.max === null || row.max === undefined) missingFields.push('Max');
            if (row.cost === '' || row.cost === null || row.cost === undefined) missingFields.push('Cost');
            
            if (missingFields.length > 0) {
                errors.push(`Electricity Tier ${index + 1}: Missing ${missingFields.join(', ')}`);
            }
        }
    });
    
    return errors;
}

// Form submit handler
function handleSubmit(event) {
    // Validate tiers before submit
    const tierErrors = validateTiers();
    
    if (tierErrors.length > 0) {
        event.preventDefault();
        alert('Tier Validation Errors:\n\n' + tierErrors.join('\n'));
        return false;
    }
    
    // Form submits normally via POST
    return true;
}
</script>
