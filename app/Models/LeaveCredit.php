<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveCredit extends TenantModel
{
    protected $table      = 'lcredits';
    public    $timestamps = false;
    protected $fillable   = ['badgeID', 'vl', 'sl', 'maternity', 'paternity', 'spl', 'forced', 'wellness', 'ot', 'service', 'dateupdated'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'badgeID', 'badgeID');
    }
}
