<?php

namespace App\Models;

class Submission extends TenantModel
{
    protected $table = 'submissions';
    public    $timestamps = false;

    protected $fillable = ['badgeID', 'attRange', 'date_submitted', 'time_submitted'];

    public static function isLocked(string $badgeID, string $attRange): bool
    {
        return static::where('badgeID', $badgeID)->where('attRange', $attRange)->exists();
    }
}
