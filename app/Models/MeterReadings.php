<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class MeterReadings extends Model
{
    use HasFactory;

    protected $fillable = [
        'meter_id',
        'reading_date',
        'reading_value',
        'reading_image',
        'reading_type',
        'is_locked',
        'notes',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'reading_date' => 'date',
    ];

    /**
     * Available reading types (SIMPLIFIED - THREE ONLY).
     * 
     * - ACTUAL: User input reading matching bill_day rules
     * - CALCULATED: System-calculated from subsequent readings (closes period)
     * - PROVISIONAL: System-projected reading/usage
     */
    const TYPE_ACTUAL = 'ACTUAL';
    const TYPE_CALCULATED = 'CALCULATED';
    const TYPE_PROVISIONAL = 'PROVISIONAL';

    public function meter() {
        return $this->belongsTo(Meter::class);
    }

    public function getReadingImageAttribute($value)
    {
        if (empty($value))
            return '';
        return URL::to(Storage::url($value));
    }

    /**
     * Check if this reading is an actual reading (user submitted).
     * Note: Actual determination is based on bill_day rules, not just type.
     */
    public function isActual(): bool
    {
        return $this->reading_type === self::TYPE_ACTUAL;
    }

    /**
     * Check if this reading is calculated (from subsequent readings, closes period).
     */
    public function isCalculated(): bool
    {
        return $this->reading_type === self::TYPE_CALCULATED;
    }

    /**
     * Check if this reading is provisional (system-projected).
     */
    public function isProvisional(): bool
    {
        return $this->reading_type === self::TYPE_PROVISIONAL;
    }

    /**
     * Lock this reading to prevent modifications.
     */
    public function lock(): self
    {
        $this->is_locked = true;
        $this->save();
        return $this;
    }

    /**
     * Get reading date as a formatted string (Y-m-d format).
     * Since reading_date is cast as 'date', it's always a Carbon instance when retrieved.
     * 
     * @return string
     */
    public function getReadingDateFormatted(): string
    {
        return $this->reading_date->toDateString();
    }

    /**
     * Get reading date as Carbon instance.
     * This is the native format due to the 'date' cast in the model.
     * 
     * @return \Carbon\Carbon
     */
    public function getReadingDate(): \Carbon\Carbon
    {
        return $this->reading_date;
    }
}
