<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeStatus extends Model
{
    protected $table    = 'empstatus';
    public    $timestamps = false;
    protected $fillable = ['description'];
}
