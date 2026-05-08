<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceRequest extends Model
{
    protected $table = 'request';
    public    $timestamps = false;

    protected $fillable = [
        'attID', 'BadgeNumber', 'AttDate',
        'StartTime1', 'StartTime2', 'StartTime3', 'StartTime4',
        'dateReq', 'log1', 'log2', 'log3', 'log4', 'remarks',
    ];

    public static function logColumn(string $column): string
    {
        return match ($column) {
            'StartTime1' => 'log1',
            'StartTime2' => 'log2',
            'StartTime3' => 'log3',
            'StartTime4' => 'log4',
        };
    }
}
