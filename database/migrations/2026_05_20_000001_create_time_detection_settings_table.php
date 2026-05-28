<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_detection_settings', function (Blueprint $table) {
            $table->id();
            $table->string('punch_type', 20);          // timein, breakout, breakin, timeout, otin, otout
            $table->string('label', 50);               // Display label
            $table->integer('before_minutes');          // Minutes before scheduled time
            $table->integer('after_minutes');           // Minutes after scheduled time
            $table->enum('pick_rule', ['earliest', 'latest']); // Which log to pick if multiple
            $table->timestamps();
        });

        // Seed default detection rules
        DB::table('time_detection_settings')->insert([
            [
                'punch_type'     => 'timein',
                'label'          => 'Time In',
                'before_minutes' => 180,   // 3 hours before
                'after_minutes'  => 120,   // 2 hours after
                'pick_rule'      => 'earliest',
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'punch_type'     => 'breakout',
                'label'          => 'Break Out',
                'before_minutes' => 120,   // 2 hours before
                'after_minutes'  => 30,    // 30 minutes after
                'pick_rule'      => 'latest',
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'punch_type'     => 'breakin',
                'label'          => 'Break In',
                'before_minutes' => 30,    // 30 minutes before
                'after_minutes'  => 120,   // 2 hours after
                'pick_rule'      => 'earliest',
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'punch_type'     => 'timeout',
                'label'          => 'Time Out',
                'before_minutes' => 120,   // 2 hours before
                'after_minutes'  => 180,   // 3 hours after
                'pick_rule'      => 'latest',
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'punch_type'     => 'otin',
                'label'          => 'Overtime Start Time',
                'before_minutes' => 0,     // Not used as window — this is the OT threshold time
                'after_minutes'  => 0,     // Time beyond this is counted as OT
                'pick_rule'      => 'earliest',
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('time_detection_settings');
    }
};
