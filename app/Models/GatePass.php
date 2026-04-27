<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GatePass extends Model
{
    protected $table = 'gatepass';

    protected $fillable = [
        'controlno', 'badgeID', 'gatepass_type', 'gatepass_date',
        'gatepass_timeout', 'gatepass_timein', 'purpose', 'destination',
        'gatepass_datefiled', 'actual_timeout', 'actual_timein',
        'date_time_approved', 'status',
    ];

    protected $casts = [
        'gatepass_date'       => 'date',
        'gatepass_datefiled'  => 'datetime',
        'date_time_approved'  => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'badgeID', 'badgeID');
    }

    public function isApproved(): bool { return !is_null($this->date_time_approved); }
    public function isPending(): bool  { return $this->status === 'Pending'; }
}
