<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lcredits', function (Blueprint $table) {
            if (!Schema::hasColumn('lcredits', 'maternity')) {
                $table->decimal('maternity', 6, 2)->default(0)->after('sl');
            }
            if (!Schema::hasColumn('lcredits', 'paternity')) {
                $table->decimal('paternity', 6, 2)->default(0)->after('maternity');
            }
            if (!Schema::hasColumn('lcredits', 'spl')) {
                $table->decimal('spl', 6, 2)->default(0)->after('paternity');
            }
            if (!Schema::hasColumn('lcredits', 'forced')) {
                $table->decimal('forced', 6, 2)->default(0)->after('spl');
            }
            if (!Schema::hasColumn('lcredits', 'wellness')) {
                $table->decimal('wellness', 6, 2)->default(0)->after('forced');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lcredits', function (Blueprint $table) {
            $table->dropColumn(['maternity', 'paternity', 'spl', 'forced', 'wellness']);
        });
    }
};
