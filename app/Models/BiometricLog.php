<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricLog extends TenantModel
{
    protected $fillable = [
        'device_id', 'device_user_id', 'timestamp',
        'punch_type', 'verify_type', 'is_processed',
    ];

    protected $casts = [
        'timestamp'    => 'datetime',
        'punch_type'   => 'integer',
        'verify_type'  => 'integer',
        'is_processed' => 'boolean',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'device_id');
    }

    public function scopeUnprocessed($query)
    {
        return $query->where('is_processed', false);
    }

    /**
     * Punch type labels matching ZKTeco conventions.
     */
    public static function punchLabel(int $type): string
    {
        return match ($type) {
            0 => 'Check In',
            1 => 'Check Out',
            2 => 'Break Out',
            3 => 'Break In',
            4 => 'OT In',
            5 => 'OT Out',
            default => "Type $type",
        };
    }
}
