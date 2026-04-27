<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceClean extends Model
{
    protected $table = 'attendance_clean';

    protected $fillable = [
        'BadgeNumber', 'AttDate',
        'StartTime1', 'StartTime2', 'StartTime3', 'StartTime4',
        'OTIn', 'OTOut', 'OT',
        'Tardiness', 'undertime',
        'amlate', 'pmlate', 'amundertime', 'pmuntertime',
        'remarks', 'obtime',
    ];

    const LABEL_ABSENT  = 'A';
    const LABEL_LEAVE   = 'L';
    const LABEL_OB      = 'OB';
    const LABEL_TRAVEL  = 'T';
    const LABEL_AWA     = 'AWA';
    const LABEL_SAT     = 'Saturday';
    const LABEL_SUN     = 'Sunday';
}
