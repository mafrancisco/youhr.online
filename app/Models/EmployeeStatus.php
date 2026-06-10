<?php

namespace App\Models;

class EmployeeStatus extends TenantModel
{
    protected $table    = 'empstatus';
    public    $timestamps = false;
    protected $fillable = ['description'];
}
