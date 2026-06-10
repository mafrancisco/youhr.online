<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricSyncHistory extends TenantModel
{
    protected $fillable = [
        'device_id', 'type', 'status', 'records_fetched',
        'records_new', 'records_skipped', 'error_message',
        'started_at', 'completed_at',
    ];

    protected $casts = [
        'records_fetched' => 'integer',
        'records_new'     => 'integer',
        'records_skipped' => 'integer',
        'started_at'      => 'datetime',
        'completed_at'    => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'device_id');
    }
}
