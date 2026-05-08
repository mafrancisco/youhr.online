<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Division extends Model
{
    protected $table    = 'divisions';
    protected $fillable = ['division_name', 'division_chief'];

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'division_id');
    }

    public function chief(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'division_chief', 'badgeID')->withDefault();
    }
}
