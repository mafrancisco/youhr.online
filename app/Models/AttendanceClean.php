<?php

namespace App\Models;

class AttendanceClean extends TenantModel
{
    protected $table = 'attendance_clean';
    public    $timestamps = false;

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
