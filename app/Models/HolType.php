<?php

namespace App\Models;

class HolType extends TenantModel
{
    protected $table    = 'holtype';
    public    $timestamps = false;
    protected $fillable = ['type'];
}
