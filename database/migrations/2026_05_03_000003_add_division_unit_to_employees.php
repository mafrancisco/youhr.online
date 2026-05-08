<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'division_id')) {
                $table->unsignedBigInteger('division_id')->nullable()->after('schedule');
            }
            if (!Schema::hasColumn('employees', 'unit_id')) {
                $table->unsignedBigInteger('unit_id')->nullable()->after('division_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'unit_id'))     $table->dropColumn('unit_id');
            if (Schema::hasColumn('employees', 'division_id')) $table->dropColumn('division_id');
        });
    }
};
