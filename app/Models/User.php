<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = ['username', 'password', 'fullname', 'email', 'type', 'google_id', 'google_email', 'auth_provider'];
    protected $hidden   = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'type'     => 'integer',
        ];
    }

    public function getConnectionName(): ?string
    {
        // Dynamic connection resolution:
        // If tenant context is active (database is set on tenant connection), use it.
        // Otherwise fall back to default connection to prevent "No database selected" errors
        // during auth guard deserialization before middleware runs.
        $tenantConn = config('saas.tenant_connection', 'tenant');
        $database = config("database.connections.{$tenantConn}.database");

        return !empty($database) ? $tenantConn : config('database.default');
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
