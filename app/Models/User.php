<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['username', 'password', 'fullname', 'email', 'type'];
    protected $hidden   = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'type'     => 'integer',
        ];
    }

    public function getAuthIdentifierName(): string
    {
        return 'username';
    }

    public function isHR(): bool
    {
        return $this->type === 1;
    }

    public function isEmployee(): bool
    {
        return $this->type === 2;
    }
}
