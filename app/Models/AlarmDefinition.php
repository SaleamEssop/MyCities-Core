<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlarmDefinition extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'condition_type',
        'condition_params',
        'delivery_method',
        'severity',
        'is_active',
    ];

    protected $casts = [
        'condition_params' => 'array',
        'is_active'        => 'boolean',
    ];
}
