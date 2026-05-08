<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    protected $table    = 'leave_type';
    public    $timestamps = false;
    protected $fillable = ['leave_type', 'description', 'acronym'];

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class, 'leave_type');
    }

    // "Vacation Leave" — matches legacy $rowltype[1].' '.$rowltype[2] display
    public function getFullNameAttribute(): string
    {
        return $this->leave_type;
    }
}
