<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('model', 50)->default('ZK IN05-A');
            $table->string('ip_address', 45);
            $table->unsignedSmallInteger('port')->default(4370);
            $table->enum('connection_type', ['LAN', 'WLAN'])->default('LAN');
            $table->string('location', 150)->default('');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('remarks')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->boolean('is_online')->default(false);
            $table->timestamps();

            $table->unique('ip_address');
        });

        Schema::create('biometric_device_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('biometric_devices')->onDelete('cascade');
            $table->string('uid', 20);          // device internal UID
            $table->string('user_id', 20);      // user ID on device
            $table->string('name', 100)->default('');
            $table->unsignedTinyInteger('role')->default(0);    // 0=user, 14=admin
            $table->unsignedTinyInteger('privilege')->default(0);
            $table->timestamps();

            $table->unique(['device_id', 'uid']);
        });

        Schema::create('biometric_employee_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_user_id')->constrained('biometric_device_users')->onDelete('cascade');
            $table->string('badge_id', 50);     // maps to employees.badgeID
            $table->timestamps();

            $table->unique('device_user_id');
            $table->index('badge_id');
        });

        Schema::create('biometric_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('biometric_devices')->onDelete('cascade');
            $table->string('device_user_id', 20);   // user ID from device
            $table->dateTime('timestamp');
            $table->unsignedTinyInteger('punch_type')->default(0); // 0=in, 1=out, etc.
            $table->unsignedTinyInteger('verify_type')->default(0); // 0=password, 1=fingerprint, 2=card
            $table->boolean('is_processed')->default(false);
            $table->timestamps();

            $table->unique(['device_id', 'device_user_id', 'timestamp', 'punch_type'], 'biometric_logs_unique');
            $table->index('is_processed');
        });

        Schema::create('biometric_sync_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('biometric_devices')->onDelete('cascade');
            $table->enum('type', ['logs', 'users'])->default('logs');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->unsignedInteger('records_fetched')->default(0);
            $table->unsignedInteger('records_new')->default(0);
            $table->unsignedInteger('records_skipped')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_sync_histories');
        Schema::dropIfExists('biometric_logs');
        Schema::dropIfExists('biometric_employee_mappings');
        Schema::dropIfExists('biometric_device_users');
        Schema::dropIfExists('biometric_devices');
    }
};
