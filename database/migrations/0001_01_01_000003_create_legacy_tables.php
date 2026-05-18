<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates tables that existed in the legacy system before Laravel migrations.
 * These tables are required for the application to function but were never
 * formally created via a migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employees')) {
            Schema::create('employees', function (Blueprint $table) {
                $table->id();
                $table->string('badgeID', 50)->index();
                $table->string('empName', 100)->default('');
                $table->string('email', 100)->default('');
                $table->integer('empStatus')->default(1);
                $table->string('empDesig', 100)->default('');
                $table->string('empHead', 100)->default('');
                $table->unsignedBigInteger('schedule')->nullable();
                $table->string('status1', 20)->default('Active');
                $table->string('date_deact', 20)->default('');
                $table->string('date_encoded', 20)->default('');
                $table->unsignedBigInteger('division_id')->nullable();
                $table->unsignedBigInteger('unit_id')->nullable();
            });
        }

        if (!Schema::hasTable('schedule')) {
            Schema::create('schedule', function (Blueprint $table) {
                $table->id();
                $table->string('schedulename', 100);
                foreach (['m', 't', 'w', 'th', 'f', 'sat', 'sun'] as $day) {
                    $table->string("{$day}_timein", 10)->default('');
                    $table->string("{$day}_breakout", 10)->default('');
                    $table->string("{$day}_breakin", 10)->default('');
                    $table->string("{$day}_timeout", 10)->default('');
                    $table->boolean("{$day}_crossday")->default(false);
                }
            });
        }

        if (!Schema::hasTable('attendance')) {
            Schema::create('attendance', function (Blueprint $table) {
                $table->id();
                $table->string('BadgeNumber', 50)->index();
                $table->string('attDate', 20);
                $table->string('attTime', 10)->default('');
                $table->integer('attType')->default(0);
            });
        }

        if (!Schema::hasTable('attendance_clean')) {
            Schema::create('attendance_clean', function (Blueprint $table) {
                $table->id();
                $table->string('BadgeNumber', 50)->index();
                $table->string('AttDate', 20);
                $table->string('StartTime1', 20)->default('');
                $table->string('StartTime2', 20)->default('');
                $table->string('StartTime3', 20)->default('');
                $table->string('StartTime4', 20)->default('');
                $table->string('OTIn', 10)->default('');
                $table->string('OTOut', 10)->default('');
                $table->integer('OT')->default(0);
                $table->integer('Tardiness')->default(0);
                $table->integer('undertime')->default(0);
                $table->integer('amlate')->default(0);
                $table->integer('pmlate')->default(0);
                $table->integer('amundertime')->default(0);
                $table->integer('pmuntertime')->default(0);
                $table->string('remarks', 255)->default('');
                $table->string('obtime', 50)->default('');
            });
        }

        if (!Schema::hasTable('request')) {
            Schema::create('request', function (Blueprint $table) {
                $table->id();
                $table->integer('attID')->nullable();
                $table->string('BadgeNumber', 50)->index();
                $table->string('AttDate', 20);
                $table->string('StartTime1', 10)->default('');
                $table->string('StartTime2', 10)->default('');
                $table->string('StartTime3', 10)->default('');
                $table->string('StartTime4', 10)->default('');
                $table->string('dateReq', 20)->default('');
                $table->string('log1', 10)->default('');
                $table->string('log2', 10)->default('');
                $table->string('log3', 10)->default('');
                $table->string('log4', 10)->default('');
                $table->string('remarks', 255)->default('');
            });
        }

        if (!Schema::hasTable('leaves')) {
            Schema::create('leaves', function (Blueprint $table) {
                $table->id();
                $table->string('controlno', 50);
                $table->string('badgeID', 50)->index();
                $table->integer('leave_type')->default(1);
                $table->string('date_start', 255)->default('');
                $table->string('date_end', 255)->default('');
                $table->text('leave_details')->nullable();
                $table->string('date_filed', 20)->default('');
                $table->decimal('noofdays', 5, 2)->default(0);
                $table->string('status', 20)->default('Pending');
                $table->decimal('credits_vl', 5, 2)->default(0);
                $table->decimal('credits_sl', 5, 2)->default(0);
                $table->decimal('ot_credits', 5, 2)->default(0);
                $table->decimal('service_credits', 5, 2)->default(0);
                $table->string('dateUpdated', 20)->default('');
            });
        }

        if (!Schema::hasTable('leave_type')) {
            Schema::create('leave_type', function (Blueprint $table) {
                $table->id();
                $table->string('leave_type', 100);
                $table->string('description', 255)->default('');
                $table->string('acronym', 10)->default('');
            });
        }

        if (!Schema::hasTable('submissions')) {
            Schema::create('submissions', function (Blueprint $table) {
                $table->id();
                $table->string('badgeID', 50)->index();
                $table->string('attRange', 20);
                $table->string('date_submitted', 20);
                $table->string('time_submitted', 20);
            });
        }

        if (!Schema::hasTable('heads')) {
            Schema::create('heads', function (Blueprint $table) {
                $table->id();
                $table->string('headname', 100);
                $table->string('headposition', 100);
            });
        }

        if (!Schema::hasTable('gatepass')) {
            Schema::create('gatepass', function (Blueprint $table) {
                $table->id();
                $table->string('controlno', 50);
                $table->string('badgeID', 50)->index();
                $table->string('gatepass_type', 50)->default('');
                $table->string('gatepass_date', 20)->default('');
                $table->string('gatepass_timeout', 10)->default('');
                $table->string('gatepass_timein', 10)->default('');
                $table->text('purpose')->nullable();
                $table->string('destination', 255)->default('');
                $table->string('gatepass_datefiled', 20)->default('');
                $table->string('actual_timeout', 10)->default('');
                $table->string('actual_timein', 10)->default('');
                $table->string('date_time_approved', 30)->default('');
                $table->string('time_consumed', 20)->default('');
                $table->string('status', 20)->default('Pending');
            });
        }

        if (!Schema::hasTable('empstatus')) {
            Schema::create('empstatus', function (Blueprint $table) {
                $table->id();
                $table->string('description', 50);
            });
        }

        if (!Schema::hasTable('dateparameters')) {
            Schema::create('dateparameters', function (Blueprint $table) {
                $table->id();
                $table->string('actualDate', 20)->index();
                $table->string('type', 100)->default('');
                $table->string('description', 255)->default('');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('empstatus');
        Schema::dropIfExists('gatepass');
        Schema::dropIfExists('heads');
        Schema::dropIfExists('submissions');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('leaves');
        Schema::dropIfExists('request');
        Schema::dropIfExists('attendance_clean');
        Schema::dropIfExists('attendance');
        Schema::dropIfExists('schedule');
        Schema::dropIfExists('employees');
    }
};
