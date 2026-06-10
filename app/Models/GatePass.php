<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GatePass extends TenantModel
{
    protected $table = 'gatepass';
    public    $timestamps = false;

    protected $fillable = [
        'controlno', 'badgeID', 'gatepass_type', 'gatepass_date',
        'gatepass_timeout', 'gatepass_timein', 'purpose', 'destination',
        'gatepass_datefiled', 'actual_timeout', 'actual_timein',
        'date_time_approved', 'time_consumed', 'status',
    ];

    protected $casts = [];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'badgeID', 'badgeID');
    }

    public function isApproved(): bool { return !empty($this->date_time_approved); }
    public function isPending(): bool  { return $this->status === 'Pending'; }
}
