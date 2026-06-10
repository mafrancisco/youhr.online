<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $connection = config('saas.tenant_connection', 'tenant');

        Schema::connection($connection)->table('users', function (Blueprint $table) use ($connection) {
            if (!Schema::connection($connection)->hasColumn('users', 'google_id')) {
                $table->string('google_id', 100)->nullable()->after('email');
            }
            if (!Schema::connection($connection)->hasColumn('users', 'google_email')) {
                $table->string('google_email', 191)->nullable()->after('google_id');
            }
            if (!Schema::connection($connection)->hasColumn('users', 'auth_provider')) {
                $table->string('auth_provider', 30)->default('google')->after('google_email');
            }
        });
    }

    public function down(): void
    {
        $connection = config('saas.tenant_connection', 'tenant');

        Schema::connection($connection)->table('users', function (Blueprint $table) use ($connection) {
            if (Schema::connection($connection)->hasColumn('users', 'auth_provider')) {
                $table->dropColumn('auth_provider');
            }
            if (Schema::connection($connection)->hasColumn('users', 'google_email')) {
                $table->dropColumn('google_email');
            }
            if (Schema::connection($connection)->hasColumn('users', 'google_id')) {
                $table->dropColumn('google_id');
            }
        });
    }
};
