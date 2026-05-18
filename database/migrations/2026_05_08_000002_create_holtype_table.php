<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('holtype')) {
            Schema::create('holtype', function (Blueprint $table) {
                $table->id();
                $table->string('type', 100);
            });
        }

        // Seed default holiday types
        $types = [
            'Regular Holiday',
            'Special Non-Working Day',
            'Special Working Holiday',
            'Adjusted Flag Ceremony Schedule',
            'Override Official Time',
        ];

        foreach ($types as $type) {
            DB::table('holtype')->insertOrIgnore(['type' => $type]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('holtype');
    }
};
