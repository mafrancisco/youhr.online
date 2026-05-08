<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Leave extends Model
{
    protected $table = 'leaves';
    public    $timestamps = false;

    protected $fillable = [
        'controlno', 'badgeID', 'leave_type', 'date_start', 'date_end',
        'leave_details', 'date_filed', 'noofdays', 'status',
        'credits_vl', 'credits_sl', 'ot_credits', 'service_credits', 'dateUpdated',
    ];

    protected $casts = [];

    public function type(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'badgeID', 'badgeID');
    }
}
