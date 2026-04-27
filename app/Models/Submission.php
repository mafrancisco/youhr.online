<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    protected $table = 'submissions';

    protected $fillable = ['badgeID', 'attRange', 'date_submitted', 'time_submitted'];

    public static function isLocked(string $badgeID, string $attRange): bool
    {
        return static::where('badgeID', $badgeID)->where('attRange', $attRange)->exists();
    }
}
