<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HolType extends Model
{
    protected $table    = 'holtype';
    public    $timestamps = false;
    protected $fillable = ['type'];
}
