<?php

namespace App\Models\SaaS;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyLicense extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'license_key_hash',
        'bound_email',
        'status',
        'activated_by_email',
        'activated_at',
        'expires_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
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

    public function matchesKey(string $plainKey): bool
    {
        return hash_equals($this->license_key_hash, hash('sha256', trim($plainKey)));
    }
}
