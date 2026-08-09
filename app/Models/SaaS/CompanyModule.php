<?php

namespace App\Models\SaaS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyModule extends Model
{
    protected $fillable = [
        'company_id',
        'module',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('saas.landlord_connection');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Master list of all available system modules.
     */
    public static function availableModules(): array
    {
        return [
            'employees'        => 'Employees',
            'users'            => 'User Accounts',
            'schedules'        => 'Schedules',
            'attendance'       => 'Attendance / DTR',
            'biometric'        => 'Biometric Devices',
            'leaves'           => 'Leaves',
            'gate-passes'      => 'Gate Passes',
            'credits'          => 'Leave Credits',
            'holidays'         => 'Holidays',
            'divisions'        => 'Divisions / Units',
            'employee-statuses'=> 'Employee Statuses',
            'leave-types'      => 'Leave Types',
            'time-detection'   => 'Time Detection',
            'settings'         => 'Settings',
        ];
    }

    /**
     * Get enabled modules for a company. Returns all if none are configured yet.
     */
    public static function enabledForCompany(int $companyId): array
    {
        $records = static::where('company_id', $companyId)->get();

        // If no records exist yet, all modules are considered enabled (backward compat)
        if ($records->isEmpty()) {
            return array_keys(static::availableModules());
        }

        return $records->where('enabled', true)->pluck('module')->all();
    }

    /**
     * Check if a specific module is enabled for a company.
     */
    public static function isModuleEnabled(int $companyId, string $module): bool
    {
        $record = static::where('company_id', $companyId)
            ->where('module', $module)
            ->first();

        // If no record exists, default to enabled (backward compat)
        if (!$record) {
            return true;
        }

        return $record->enabled;
    }
}
