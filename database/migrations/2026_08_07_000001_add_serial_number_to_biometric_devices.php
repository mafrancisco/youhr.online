<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('biometric_devices')) {
            return;
        }

        if (!Schema::hasColumn('biometric_devices', 'serial_number')) {
            Schema::table('biometric_devices', function (Blueprint $table) {
                // Nullable so existing rows remain valid; MySQL permits multiple NULLs
                // under a unique index, while enforcing uniqueness for real serials.
                $table->string('serial_number', 50)->nullable()->after('model');
            });
        }

        // The device serial identifies which tenant owns it, so it must be unique
        // within the tenant database.
        if (!$this->hasIndex('biometric_devices', 'biometric_devices_serial_number_unique')) {
            Schema::table('biometric_devices', function (Blueprint $table) {
                $table->unique('serial_number');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('biometric_devices')) {
            return;
        }

        if ($this->hasIndex('biometric_devices', 'biometric_devices_serial_number_unique')) {
            Schema::table('biometric_devices', function (Blueprint $table) {
                $table->dropUnique('biometric_devices_serial_number_unique');
            });
        }

        if (Schema::hasColumn('biometric_devices', 'serial_number')) {
            Schema::table('biometric_devices', function (Blueprint $table) {
                $table->dropColumn('serial_number');
            });
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        return !empty($connection->select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$database, $table, $index]
        ));
    }
};
