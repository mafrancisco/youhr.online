<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('system_name', 100)->default('IronOne DTR');
            $table->string('company_address', 255)->default('');
            $table->string('authorized_signatory', 100)->default('');
            $table->string('authorized_signatory_position', 100)->default('');
            $table->string('logo_path', 255)->default('');
        });

        // Seed the single row
        DB::table('settings')->insert([
            'id'                             => 1,
            'system_name'                    => 'IronOne DTR',
            'company_address'                => '',
            'authorized_signatory'           => '',
            'authorized_signatory_position'  => '',
            'logo_path'                      => '',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
