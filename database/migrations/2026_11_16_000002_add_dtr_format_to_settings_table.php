<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-company DTR PDF layout: 'standard' (original) or 'csc48' (Civil Service Form No. 48).
 * Tenant migration — run with `php artisan tenants:migrate`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('settings') || Schema::hasColumn('settings', 'dtr_format')) {
            return;
        }

        Schema::table('settings', function (Blueprint $table) {
            $table->string('dtr_format', 20)->default('standard');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('settings') && Schema::hasColumn('settings', 'dtr_format')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->dropColumn('dtr_format');
            });
        }
    }
};
