<?php

namespace Tests\Traits;

use App\Models\AttendanceClean;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\Schedule;
use App\Models\Submission;
use App\Models\User;

trait CreatesTestData
{
    protected function createHRUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'username' => 'hradmin',
            'password' => 'password',
            'fullname' => 'HR Admin',
            'email'    => 'hr@test.com',
            'type'     => 1,
        ], $overrides));
    }

    protected function createEmployeeUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'username' => 'employee1',
            'password' => 'password',
            'fullname' => 'John Doe',
            'email'    => 'john@test.com',
            'type'     => 2,
        ], $overrides));
    }

    protected function createEmployee(array $overrides = []): Employee
    {
        return Employee::create(array_merge([
            'badgeID'    => '10001',
            'empName'    => 'John Doe',
            'email'      => 'john@test.com',
            'empStatus'  => 1,
            'empDesig'   => 'Developer',
            'empHead'    => 'Jane Smith',
            'schedule'   => null,
            'status1'    => 'Active',
            'date_encoded' => now()->toDateString(),
        ], $overrides));
    }

    protected function createSchedule(array $overrides = []): Schedule
    {
        $defaults = [
            'schedulename' => 'Regular 8-5',
        ];

        $days = ['m', 't', 'w', 'th', 'f'];
        foreach ($days as $day) {
            $defaults["{$day}_timein"]   = '08:00';
            $defaults["{$day}_breakout"] = '12:00';
            $defaults["{$day}_breakin"]  = '13:00';
            $defaults["{$day}_timeout"]  = '17:00';
            $defaults["{$day}_crossday"] = 0;
        }

        foreach (['sat', 'sun'] as $day) {
            $defaults["{$day}_timein"]   = '';
            $defaults["{$day}_breakout"] = '';
            $defaults["{$day}_breakin"]  = '';
            $defaults["{$day}_timeout"]  = '';
            $defaults["{$day}_crossday"] = 0;
        }

        return Schedule::create(array_merge($defaults, $overrides));
    }

    protected function createAttendanceRecord(string $badgeID, string $attDate, array $overrides = []): AttendanceClean
    {
        return AttendanceClean::create(array_merge([
            'BadgeNumber' => $badgeID,
            'AttDate'     => $attDate,
            'StartTime1'  => '',
            'StartTime2'  => '',
            'StartTime3'  => '',
            'StartTime4'  => '',
            'OTIn'        => '',
            'OTOut'       => '',
            'OT'          => 0,
            'Tardiness'   => 0,
            'undertime'   => 0,
            'remarks'     => '',
            'obtime'      => '',
        ], $overrides));
    }

    protected function createLeave(string $badgeID, array $overrides = []): Leave
    {
        return Leave::create(array_merge([
            'controlno'    => 'LV-' . uniqid(),
            'badgeID'      => $badgeID,
            'leave_type'   => 1,
            'date_start'   => '05/05/2025',
            'date_end'     => '',
            'leave_details' => 'Personal',
            'date_filed'   => now()->toDateString(),
            'noofdays'     => 1,
            'status'       => 'Pending',
        ], $overrides));
    }

    protected function createSubmission(string $badgeID, string $attRange): Submission
    {
        return Submission::create([
            'badgeID'        => $badgeID,
            'attRange'       => $attRange,
            'date_submitted' => now()->toDateString(),
            'time_submitted' => now()->format('H:i:s'),
        ]);
    }
}
