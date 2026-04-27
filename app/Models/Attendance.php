<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $table = 'attendance';

    protected $fillable = ['BadgeNumber', 'AttDate', 'AttTime', 'AttType'];

    const TYPE_TIMEIN  = 0;
    const TYPE_BREAKOUT = 1;
    const TYPE_BREAKIN  = 2;
    const TYPE_TIMEOUT  = 3;
    const TYPE_OTIN    = 4;
    const TYPE_OTOUT   = 5;
}
