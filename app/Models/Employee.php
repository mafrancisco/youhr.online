<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    protected $table = 'employees';

    protected $fillable = [
        'badgeID', 'empName', 'email', 'empStatus', 'empDesig',
        'empHead', 'schedule', 'status1', 'date_deact', 'date_encoded',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status1', 'Active');
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status1', 'Inactive');
    }

    public function statusRecord(): BelongsTo
    {
        return $this->belongsTo(EmployeeStatus::class, 'empStatus');
    }

    public function scheduleRecord(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule');
    }

    public function leaveCredit(): HasOne
    {
        return $this->hasOne(LeaveCredit::class, 'badgeID', 'badgeID');
    }
}
