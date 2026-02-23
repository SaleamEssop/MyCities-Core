<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'site_id',
        'tariff_template_id',
        'region_id',
        'zone_id',
        'account_name',
        'account_number',
        'name_on_bill',
        'billing_date',
        'optional_information',
        'address',
        'latitude',
        'longitude',
        'water_email',
        'electricity_email',
        'bill_day',
        'read_day',
        'bill_read_day_active',
        'customer_costs',
        'fixed_costs',
    ];
    
    protected $casts = [
        'customer_costs' => 'array',
        'fixed_costs' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function region()
    {
        return $this->belongsTo(Regions::class, 'region_id');
    }

    public function zone()
    {
        return $this->belongsTo(RegionZone::class, 'zone_id');
    }

    /**
     * Get the tariff template associated with this account.
     * This replaces the old region_id + account_type_id lookup.
     */
    public function tariffTemplate()
    {
        return $this->belongsTo(RegionsAccountTypeCost::class, 'tariff_template_id');
    }

    /**
     * Helper method to get the region via the tariff template.
     * Account gets region via TariffTemplate in the new architecture.
     */
    public function getRegion()
    {
        return $this->tariffTemplate ? $this->tariffTemplate->region : null;
    }

    /**
     * Helper method to get the region_id via the tariff template.
     * Named explicitly to avoid confusion with the old region_id column.
     */
    public function getRegionIdFromTemplateAttribute()
    {
        return $this->tariffTemplate ? $this->tariffTemplate->region_id : null;
    }

    /**
     * Check if tariff template is properly assigned and exists
     */
    public function hasValidTariffTemplate(): bool
    {
        if (!$this->tariff_template_id) {
            return false;
        }
        
        // Try relationship first
        if ($this->relationLoaded('tariffTemplate')) {
            return $this->tariffTemplate !== null;
        }
        
        // Fallback to direct query
        return \App\Models\RegionsAccountTypeCost::where('id', $this->tariff_template_id)->exists();
    }

    public function fixedCosts()
    {
        return $this->hasMany(FixedCost::class);
    }

    public function defaultFixedCosts()
    {
        return $this->hasMany(AccountFixedCost::class);
    }

    public function meters()
    {
        return $this->hasMany(Meter::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function bills()
    {
        return $this->hasMany(Bill::class);
    }

    protected static function booted()
    {
        static::deleting(function ($account) {
            // Delete all payments for this account
            Payment::where('account_id', $account->id)->delete();
            
            // Delete all fixed costs
            FixedCost::where('account_id', $account->id)->delete();
            AccountFixedCost::where('account_id', $account->id)->delete();

            // Delete all meters (which will cascade to readings)
            foreach($account->meters as $meter) {
                $meter->delete();
            }
        });
    }

}
