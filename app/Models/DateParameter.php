<?php

namespace App\Models;

class DateParameter extends TenantModel
{
    protected $table      = 'dateparameters';
    public    $timestamps = false;
    protected $fillable   = ['type', 'description', 'actualDate', 'timein', 'breakout', 'breakin', 'timeout'];

    public function isHoliday(): bool
    {
        return str_contains($this->type, 'Holiday');
    }

    public function isAdjustedFlagCeremony(): bool
    {
        return $this->type === 'Adjusted Flag Ceremony Schedule';
    }

    public function isOverrideOfficialTime(): bool
    {
        return $this->type === 'Override Official Time';
    }
}
