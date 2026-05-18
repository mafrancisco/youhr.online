<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dateparameters', function (Blueprint $table) {
            if (!Schema::hasColumn('dateparameters', 'timein')) {
                $table->string('timein', 10)->default('')->after('description');
            }
            if (!Schema::hasColumn('dateparameters', 'breakout')) {
                $table->string('breakout', 10)->default('')->after('timein');
            }
            if (!Schema::hasColumn('dateparameters', 'breakin')) {
                $table->string('breakin', 10)->default('')->after('breakout');
            }
            if (!Schema::hasColumn('dateparameters', 'timeout')) {
                $table->string('timeout', 10)->default('')->after('breakin');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dateparameters', function (Blueprint $table) {
            $table->dropColumn(['timein', 'breakout', 'breakin', 'timeout']);
        });
    }
};
