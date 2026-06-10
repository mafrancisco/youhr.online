<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends TenantModel
{
    protected $table    = 'units';
    protected $fillable = ['unit_name', 'division_id', 'unit_head'];

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'unit_id');
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'unit_head', 'badgeID')->withDefault();
    }
}
