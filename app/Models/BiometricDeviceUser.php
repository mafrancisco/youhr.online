<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BiometricDeviceUser extends Model
{
    protected $fillable = [
        'device_id', 'uid', 'user_id', 'name', 'role', 'privilege',
    ];

    protected $casts = [
        'role'      => 'integer',
        'privilege' => 'integer',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'device_id');
    }

    public function mapping(): HasOne
    {
        return $this->hasOne(BiometricEmployeeMapping::class, 'device_user_id');
    }

    public function isMapped(): bool
    {
        return $this->mapping !== null;
    }
}
