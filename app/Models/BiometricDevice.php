<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class BiometricDevice extends TenantModel
{
    protected $fillable = [
        'name', 'model', 'serial_number', 'ip_address', 'port', 'connection_type',
        'location', 'status', 'remarks', 'last_sync_at', 'is_online',
    ];

    protected $casts = [
        'port'         => 'integer',
        'is_online'    => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(BiometricDeviceUser::class, 'device_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(BiometricLog::class, 'device_id');
    }

    public function syncHistories(): HasMany
    {
        return $this->hasMany(BiometricSyncHistory::class, 'device_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
