<?php

use App\Models\SaaS\Company;
use App\Models\SaaS\CompanyLicense;
use App\Models\User;
use App\Services\SaaS\TenantManager;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('tenants:migrate {--company=} {--status}', function (TenantManager $tenants) {
    $query = Company::query()->orderBy('id');

    if ($slug = $this->option('company')) {
        $query->where('slug', Str::lower((string) $slug));
    }

    $companies = $query->get();

    if ($companies->isEmpty()) {
        $this->error('No matching companies found.');
        return self::FAILURE;
    }

    $failed = [];

    try {
        foreach ($companies as $company) {
            $this->line("── {$company->name} ({$company->database})");

            try {
                $tenants->switchToCompany($company);

                Artisan::call($this->option('status') ? 'migrate:status' : 'migrate', array_filter([
                    '--database' => $tenants->connectionName(),
                    '--force'    => $this->option('status') ? null : true,
                ]));

                $this->line(trim(Artisan::output()));
            } catch (\Throwable $e) {
                $failed[] = $company->slug;
                $this->error("   failed: {$e->getMessage()}");
            }
        }
    } finally {
        $tenants->restoreDefaultConnection();
    }

    if ($failed) {
        $this->error('Failed for: ' . implode(', ', $failed));
        return self::FAILURE;
    }

    $this->info('Done for ' . $companies->count() . ' tenant(s).');
    return self::SUCCESS;
})->purpose('Run migrations across every tenant database');

Artisan::command('biometric:agent-token {company_slug} {--name=} {--revoke}', function (TenantManager $tenants) {
    $slug = Str::lower((string) $this->argument('company_slug'));
    $company = Company::where('slug', $slug)->first();

    if (!$company) {
        $this->error("Company '{$slug}' not found.");
        return self::FAILURE;
    }

    $tenants->switchToCompany($company);

    try {
        // A dedicated account per site, so an agent never carries a person's
        // credentials and can be revoked without affecting anyone's login.
        $username = $this->option('name') ?: 'agent-' . $slug;

        /** @var User $agent */
        $agent = User::on($tenants->connectionName())->where('username', $username)->first();

        if ($this->option('revoke')) {
            if (!$agent) {
                $this->error("No agent account '{$username}' in this tenant.");
                return self::FAILURE;
            }

            $count = $agent->tokens()->count();
            $agent->tokens()->delete();
            $this->info("Revoked {$count} token(s) for '{$username}'. Any agent using them stops working immediately.");
            return self::SUCCESS;
        }

        if (!$agent) {
            $agent = User::on($tenants->connectionName())->create([
                'username'      => $username,
                'fullname'      => 'Biometric Sync Agent',
                'email'         => $username . '@agent.local',
                // Login is by token only; this password is never used and is random
                // so the account cannot be signed into interactively.
                'password'      => Str::random(48),
                'type'          => 1,
                'auth_provider' => 'agent',
            ]);
            $this->line("Created agent account '{$username}'.");
        }

        // Replace rather than accumulate, so an old copy of the token cannot linger.
        $agent->tokens()->delete();

        // Scoped to ingest only — this token cannot read employees or touch the DTR.
        $token = $agent->createToken('biometric-agent', ['biometric:ingest']);

        $this->newLine();
        $this->info('Agent token issued. It is shown once — store it in the agent config now.');
        $this->newLine();
        $this->line('  Company     : ' . $company->name);
        $this->line('  AGENT_SLUG  : ' . $company->slug);
        $this->line('  AGENT_TOKEN : ' . $token->plainTextToken);
        $this->line('  SERVER_URL  : ' . rtrim(config('app.url'), '/'));
        $this->newLine();
        $this->line('The agent sends these as:');
        $this->line('  Authorization: Bearer <AGENT_TOKEN>');
        $this->line('  X-Company-Slug: ' . $company->slug);
        $this->newLine();

        return self::SUCCESS;
    } finally {
        $tenants->restoreDefaultConnection();
    }
})->purpose('Issue (or revoke) a scoped biometric sync-agent token for one tenant');

Artisan::command('saas:license-generate {company_slug} {--email=} {--expires=}', function () {

    $slug = Str::lower((string) $this->argument('company_slug'));
    $company = Company::where('slug', $slug)->first();

    if (!$company) {
        $this->error('Company not found.');
        return self::FAILURE;
    }

    $plain = strtoupper(Str::random(4) . '-' . Str::random(4) . '-' . Str::random(4) . '-' . Str::random(4));
    $hash = hash('sha256', $plain);

    $expiresAt = null;
    $expires = $this->option('expires');
    if ($expires) {
        try {
            $expiresAt = \Carbon\Carbon::parse((string) $expires);
        } catch (\Throwable) {
            $this->error('Invalid --expires date. Use a parseable date/time string.');
            return self::FAILURE;
        }
    }

    CompanyLicense::create([
        'company_id' => $company->id,
        'license_key_hash' => $hash,
        'bound_email' => $this->option('email') ?: null,
        'status' => 'pending',
        'expires_at' => $expiresAt,
        'metadata' => ['generated_by' => 'artisan', 'generated_at' => now()->toIso8601String()],
    ]);

    $this->info('License generated successfully:');
    $this->line('Company: ' . $company->name . ' (' . $company->slug . ')');
    $this->line('License Key: ' . $plain);
    if ($this->option('email')) {
        $this->line('Bound Email: ' . $this->option('email'));
    }
    if ($expiresAt) {
        $this->line('Expires At: ' . $expiresAt->toDateTimeString());
    }

    return self::SUCCESS;
})->purpose('Generate a manual license key for a company tenant');
