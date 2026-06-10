<?php

namespace App\Models\SaaS;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandlordAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'license_id',
        'actor_email',
        'action',
        'subject_type',
        'subject_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
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

    public function license(): BelongsTo
    {
        return $this->belongsTo(CompanyLicense::class, 'license_id');
    }
}
