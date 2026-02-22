<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TariffTemplate extends Model
{
    use HasFactory;

    protected $table = 'regions_account_type_cost';

    protected $fillable = [
        'meter_type_id',
        'region_id',
        'min',
        'max',
        'amount',
        'water_in',
        'water_out',
        'waterout_additional',
        'electricity',
        'fixed_costs',
        'customer_costs',
        'billing_day',
        'read_day',
        'vat_rate',
        'is_water',
        'is_electricity',
        'billing_type',
        'template_name',
        'is_active',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'water_in' => 'array',
        'water_out' => 'array',
        'waterout_additional' => 'array',
        'electricity' => 'array',
        'fixed_costs' => 'array',
        'customer_costs' => 'array',
        'is_water' => 'boolean',
        'is_electricity' => 'boolean',
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function region()
    {
        return $this->belongsTo(Regions::class, 'region_id');
    }

    public function meterType()
    {
        return $this->belongsTo(MeterType::class, 'meter_type_id');
    }

    /**
     * Check if this is date-to-date billing
     */
    public function isDateToDateBilling(): bool
    {
        return $this->billing_type === 'DATE_TO_DATE';
    }

    /**
     * Get VAT rate (default 15%)
     */
    public function getVatRate(): float
    {
        return $this->vat_rate ?? 15.0;
    }

    /**
     * Check if tariff is effective for a given date
     */
    public function isEffectiveFor(Carbon $date): bool
    {
        if ($this->effective_from && $date->lt($this->effective_from)) {
            return false;
        }
        if ($this->effective_to && $date->gt($this->effective_to)) {
            return false;
        }
        return $this->is_active ?? true;
    }

    /**
     * Get tiers relationship (returns empty collection for legacy tariffs)
     * Modern tariffs use water_in JSON column instead
     */
    public function tiers()
    {
        return $this->hasMany(\App\Models\TariffTier::class, 'tariff_template_id');
    }
}