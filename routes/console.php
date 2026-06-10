<?php

use App\Models\SaaS\Company;
use App\Models\SaaS\CompanyLicense;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
