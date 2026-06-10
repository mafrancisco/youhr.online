<?php

namespace App\Models;

class TimeDetectionSetting extends TenantModel
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
