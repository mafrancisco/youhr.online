<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Head extends Model
{
    protected $table    = 'heads';
    protected $fillable = ['headname', 'headposition'];
}
