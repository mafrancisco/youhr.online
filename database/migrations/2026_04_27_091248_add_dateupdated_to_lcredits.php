<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('lcredits')) {
            Schema::create('lcredits', function (Blueprint $table) {
                $table->id();
                $table->string('badgeID', 50);
                $table->decimal('vl', 5, 2)->default(0);
                $table->decimal('sl', 5, 2)->default(0);
                $table->decimal('ot', 5, 2)->default(0);
                $table->decimal('service', 5, 2)->default(0);
                $table->string('dateupdated', 20)->default('');
            });
            return;
        }

        if (!Schema::hasColumn('lcredits', 'dateupdated')) {
            Schema::table('lcredits', function (Blueprint $table) {
                $table->string('dateupdated', 20)->default('');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lcredits');
    }
};
