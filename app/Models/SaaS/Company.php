<?php

namespace App\Models\SaaS;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'database',
        'owner_google_email',
        'status',
    ];

    public function getConnectionName(): ?string
    {
        return config('saas.landlord_connection');
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(CompanyLicense::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(CompanyModule::class);
    }

    public function enabledModules(): array
    {
        return CompanyModule::enabledForCompany($this->id);
    }

    public function hasModule(string $module): bool
    {
        return CompanyModule::isModuleEnabled($this->id, $module);
    }

    public function hasActiveLicense(): bool
    {
        return $this->licenses()
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->exists();
    }
}
