<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveCredit extends Model
{
    protected $table      = 'lcredits';
    public    $timestamps = false;
    protected $fillable   = ['badgeID', 'vl', 'sl', 'ot', 'service', 'dateupdated'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'badgeID', 'badgeID');
    }
}
