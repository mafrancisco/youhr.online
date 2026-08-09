<?php

namespace App\Services\SaaS;

use App\Models\SaaS\Company;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantManager
{
    private ?string $originalDefaultConnection = null;

    public function companySessionKey(): string
    {
        return config('saas.company_session_key', 'tenant_company_id');
    }

    public function connectionName(): string
    {
        return config('saas.tenant_connection', 'tenant');
    }

    public function switchToCompany(Company $company): void
    {
        // Validate database name has expected prefix to prevent accessing arbitrary databases
        $prefix = config('saas.tenant_database_prefix', 'tenant_');
        $database = $company->database;

        if (!preg_match('/^[a-z0-9_\-]+$/i', $database)) {
            throw new \RuntimeException("Invalid tenant database name: contains illegal characters.");
        }

        if (!str_starts_with($database, $prefix)) {
            throw new \RuntimeException("Invalid tenant database name: must start with '{$prefix}'.");
        }

        $this->originalDefaultConnection ??= config('database.default');

        $tenantConnection = $this->connectionName();
        $base = Config::get('database.connections.' . $tenantConnection, []);

        $base['database'] = $database;

        Config::set('database.connections.' . $tenantConnection, $base);
        DB::purge($tenantConnection);
        DB::reconnect($tenantConnection);

        Config::set('database.default', $tenantConnection);

        app()->instance('currentCompany', $company);
    }

    public function restoreDefaultConnection(): void
    {
        if ($this->originalDefaultConnection) {
            Config::set('database.default', $this->originalDefaultConnection);
        }

        $tenantConnection = $this->connectionName();
        DB::disconnect($tenantConnection);
        app()->forgetInstance('currentCompany');
    }

    public function provisionTenant(Company $company, string $ownerName, string $ownerEmail, string $googleId): void
    {
        $landlord = config('saas.landlord_connection');

        DB::connection($landlord)->statement(
            sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $company->database)
        );

        $this->switchToCompany($company);

        try {
            Artisan::call('migrate', [
                '--database' => $this->connectionName(),
                '--force' => true,
            ]);

            // Re-establish the connection after migrations (artisan may have purged it)
            $this->switchToCompany($company);

            $usernameBase = Str::slug(strtok($ownerEmail, '@'), '_') ?: 'admin';
            $username = $usernameBase;
            $counter = 1;
            while (User::on($this->connectionName())->where('username', $username)->exists()) {
                $username = $usernameBase . '_' . $counter;
                $counter++;
            }

            User::on($this->connectionName())->create([
                'username' => $username,
                'fullname' => $ownerName,
                'email' => $ownerEmail,
                'password' => Str::random(40),
                'type' => 1,
                'google_id' => $googleId,
                'google_email' => $ownerEmail,
                'auth_provider' => 'google',
            ]);
        } finally {
            $this->restoreDefaultConnection();
        }
    }

    public function currentCompany(): ?Company
    {
        return app()->bound('currentCompany') ? app('currentCompany') : null;
    }
}
