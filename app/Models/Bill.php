<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\RegionsAccountTypeCost;

class Bill extends Model
{
    use HasFactory;

    protected $table = 'bills';

    protected $fillable = [
        'billing_cycle_id',
        'account_id',
        'meter_id',
        'tariff_template_id',
        'billing_mode',
        'period_start_date',
        'period_end_date',
        'sector_readings',
        'opening_reading_id',
        'closing_reading_id',
        'consumption',
        'original_provisional_value',
        'calculated_value',
        'adjustment_delta',
        'tiered_charge',
        'fixed_costs_total',
        'vat_amount',
        'total_amount',
        'is_provisional',
        'tier_breakdown',
        'fixed_costs_breakdown',
        'account_costs_breakdown',
        'warnings',
        'usage_status',
        'bill_total_status',
        'adjustment_brought_forward',
        'usage_charge',
        'editable_charge_total',
        'daily_usage',
        'calculated_closing',
        'status',
    ];

    protected $casts = [
        'period_start_date' => 'date',
        'period_end_date' => 'date',
        'consumption' => 'decimal:4',
        'original_provisional_value' => 'decimal:4',
        'calculated_value' => 'decimal:4',
        'adjustment_delta' => 'decimal:4',
        'tiered_charge' => 'decimal:2',
        'fixed_costs_total' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'is_provisional' => 'boolean',
        'tier_breakdown' => 'array',
        'fixed_costs_breakdown' => 'array',
        'account_costs_breakdown' => 'array',
        'warnings' => 'array',
        'sector_readings' => 'array',
        'adjustment_brought_forward' => 'decimal:2',
        'usage_charge' => 'decimal:2',
        'editable_charge_total' => 'decimal:2',
        'daily_usage' => 'decimal:4',
        'calculated_closing' => 'decimal:4',
    ];

    /**
     * Get the billing cycle for this bill.
     */
    public function billingCycle()
    {
        return $this->belongsTo(BillingCycle::class);
    }

    /**
     * Get the account for this bill.
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the meter for this bill.
     */
    public function meter()
    {
        return $this->belongsTo(Meter::class);
    }

    /**
     * Get the tariff template used for this bill.
     * Uses RegionsAccountTypeCost (source of truth).
     */
    public function tariffTemplate()
    {
        return $this->belongsTo(RegionsAccountTypeCost::class, 'tariff_template_id');
    }

    /**
     * Get the opening reading.
     */
    public function openingReading()
    {
        return $this->belongsTo(MeterReadings::class, 'opening_reading_id');
    }

    /**
     * Get the closing reading.
     */
    public function closingReading()
    {
        return $this->belongsTo(MeterReadings::class, 'closing_reading_id');
    }

    /**
     * Get all adjustments for this bill.
     */
    public function adjustments()
    {
        return $this->hasMany(Adjustment::class);
    }

    /**
     * Get adjustments that were applied to this bill.
     */
    public function appliedAdjustments()
    {
        return $this->hasMany(Adjustment::class, 'applied_to_bill_id');
    }

    /**
     * Get formatted tier breakdown.
     *
     * @return array
     */
    public function getFormattedTierBreakdown(): array
    {
        return $this->tier_breakdown ?? [];
    }

    /**
     * Get formatted fixed costs breakdown.
     *
     * @return array
     */
    public function getFormattedFixedCostsBreakdown(): array
    {
        return $this->fixed_costs_breakdown ?? [];
    }

    /**
     * Get formatted account costs breakdown.
     *
     * @return array
     */
    public function getFormattedAccountCostsBreakdown(): array
    {
        return $this->account_costs_breakdown ?? [];
    }

    /**
     * Get all warnings for this bill.
     *
     * @return array
     */
    public function getWarnings(): array
    {
        return $this->warnings ?? [];
    }

    /**
     * Check if this bill is closed (not provisional).
     * A bill is closed if it's not provisional (actual reading was used).
     *
     * @return bool
     */
    public function isClosed(): bool
    {
        return !$this->is_provisional;
    }

    /**
     * Boot method to prevent modification of closed bills.
     */
    protected static function boot()
    {
        parent::boot();

        // Prevent updates to closed bills
        static::updating(function ($bill) {
            if ($bill->isClosed() && $bill->isDirty()) {
                throw new \Exception('Cannot modify a closed bill. Closed bills are immutable for audit trail integrity.');
            }
        });

        // Prevent deletion of closed bills
        static::deleting(function ($bill) {
            if ($bill->isClosed()) {
                throw new \Exception('Cannot delete a closed bill. Closed bills are immutable for audit trail integrity.');
            }
        });
    }
}
