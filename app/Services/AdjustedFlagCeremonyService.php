<?php

namespace App\Services;

use App\Models\AttendanceClean;
use App\Models\DateParameter;
use App\Models\Employee;
use App\Models\Schedule;

class AdjustedFlagCeremonyService
{
    public function recompute(DateParameter $param): void
    {
        $actualDate = $param->actualDate;
        $dayName    = strtolower(date('l', strtotime($actualDate)));
        $attDate    = date('m/d/Y', strtotime($actualDate));

        if ($param->isAdjustedFlagCeremony()) {
            $this->recomputeAdjustedFlag($param, $dayName, $attDate);
        } elseif ($param->isOverrideOfficialTime()) {
            $this->recomputeOverride($param, $attDate);
        }
    }

    private function recomputeAdjustedFlag(DateParameter $param, string $dayName, string $attDate): void
    {
        $map = [
            'monday'  => ['col_in' => 'm_timein',  'col_out' => 'm_timeout',  'std_in' => '07:30', 'std_out' => '16:30'],
            'tuesday' => ['col_in' => 't_timein',  'col_out' => 't_timeout',  'std_in' => '08:00', 'std_out' => '17:00'],
        ];

        if (!isset($map[$dayName])) return;
        $cfg = $map[$dayName];

        $scheduleIds = Schedule::where($cfg['col_in'], $cfg['std_in'])
            ->where($cfg['col_out'], $cfg['std_out'])
            ->pluck('id');

        $badgeIDs = Employee::whereIn('schedule', $scheduleIds)->pluck('badgeID');

        AttendanceClean::where('attDate', $attDate)->whereIn('BadgeNumber', $badgeIDs)->get()
            ->each(function ($row) use ($param) {
                $updates = [];
                if ($row->StartTime1 && strtotime($row->StartTime1 . ':00') !== false) {
                    $updates['amlate'] = max(0, (strtotime($row->StartTime1) - strtotime($param->timein)) / 60);
                }
                if ($row->StartTime4 && strtotime($row->StartTime4 . ':00') !== false) {
                    $updates['pmuntertime'] = max(0, (strtotime($param->timeout) - strtotime($row->StartTime4)) / 60);
                }
                if (!empty($updates)) {
                    $updates['Tardiness'] = ($updates['amlate'] ?? $row->amlate ?? 0) + ($row->pmlate ?? 0);
                    $updates['undertime'] = ($updates['pmuntertime'] ?? $row->pmuntertime ?? 0) + ($row->amundertime ?? 0);
                    $row->update($updates);
                }
            });
    }

    private function recomputeOverride(DateParameter $param, string $attDate): void
    {
        AttendanceClean::where('attDate', $attDate)->get()->each(function ($row) use ($param) {
            $updates = [];
            if ($row->StartTime1 && strtotime($row->StartTime1 . ':00') !== false) {
                $updates['amlate'] = max(0, (strtotime($row->StartTime1) - strtotime($param->timein)) / 60);
            }
            if ($row->StartTime4 && strtotime($row->StartTime4 . ':00') !== false) {
                $updates['pmuntertime'] = max(0, (strtotime($param->timeout) - strtotime($row->StartTime4)) / 60);
            }
            if ($row->StartTime2 && strtotime($row->StartTime2 . ':00') !== false) {
                $updates['amundertime'] = max(0, (strtotime($param->breakout) - strtotime($row->StartTime2)) / 60);
            }
            if ($row->StartTime3 && strtotime($row->StartTime3 . ':00') !== false) {
                $updates['pmlate'] = max(0, (strtotime($row->StartTime3) - strtotime($param->breakin)) / 60);
            }
            if (!empty($updates)) {
                $updates['Tardiness'] = ($updates['amlate'] ?? $row->amlate ?? 0) + ($updates['pmlate'] ?? $row->pmlate ?? 0);
                $updates['undertime'] = ($updates['pmuntertime'] ?? $row->pmuntertime ?? 0) + ($updates['amundertime'] ?? $row->amundertime ?? 0);
                $row->update($updates);
            }
        });
    }
}
