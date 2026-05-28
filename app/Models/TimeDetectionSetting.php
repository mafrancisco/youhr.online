<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimeDetectionSetting extends Model
{
    protected $fillable = [
        'punch_type', 'label', 'before_minutes', 'after_minutes', 'pick_rule',
    ];

    protected $casts = [
        'before_minutes' => 'integer',
        'after_minutes'  => 'integer',
    ];

    /**
     * Get all settings keyed by punch_type.
     */
    public static function allKeyed(): array
    {
        return static::all()->keyBy('punch_type')->toArray();
    }
}
