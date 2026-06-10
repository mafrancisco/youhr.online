<?php

namespace App\Models;

class Head extends TenantModel
{
    protected $table    = 'heads';
    public    $timestamps = false;
    protected $fillable = ['headname', 'headposition'];
}
