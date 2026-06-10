<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricEmployeeMapping extends TenantModel
{
    protected $fillable = ['device_user_id', 'badge_id'];

    public function deviceUser(): BelongsTo
    {
        return $this->belongsTo(BiometricDeviceUser::class, 'device_user_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'badge_id', 'badgeID');
    }
}
