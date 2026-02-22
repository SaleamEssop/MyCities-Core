<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegionZone extends Model
{
    use HasFactory;

    protected $table = 'region_zones';

    protected $fillable = [
        'region_id',
        'zone_name',
        'water_email',
        'electricity_email',
    ];

    public function region()
    {
        return $this->belongsTo(Regions::class, 'region_id');
    }
}
