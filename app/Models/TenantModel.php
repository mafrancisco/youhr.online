<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class TenantModel extends Model
{
    public function getConnectionName(): ?string
    {
        return config('saas.tenant_connection', 'tenant');
    }
}
