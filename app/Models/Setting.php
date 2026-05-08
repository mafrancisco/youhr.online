<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table    = 'settings';
    public    $timestamps = false;
    protected $fillable = [
        'system_name',
        'company_address',
        'authorized_signatory',
        'authorized_signatory_position',
        'logo_path',
    ];

    public static function current(): self
    {
        return self::firstOrCreate(['id' => 1], [
            'system_name'                    => 'IronOne DTR',
            'company_address'                => '',
            'authorized_signatory'           => '',
            'authorized_signatory_position'  => '',
            'logo_path'                      => '',
        ]);
    }

    public function logoPublicPath(): string
    {
        return $this->logo_path ? storage_path('app/public/' . $this->logo_path) : '';
    }

    public function logoUrl(): string
    {
        return $this->logo_path ? asset('storage/' . $this->logo_path) : '';
    }
}
