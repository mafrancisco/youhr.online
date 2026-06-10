<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $connection = config('saas.landlord_connection', 'landlord');

        if (Schema::connection($connection)->hasTable('landlord_audit_logs')) {
            return;
        }

        Schema::connection($connection)->create('landlord_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('license_id')->nullable()->constrained('company_licenses')->nullOnDelete();
            $table->string('actor_email', 191)->nullable();
            $table->string('action', 100);
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection(config('saas.landlord_connection', 'landlord'))->dropIfExists('landlord_audit_logs');
    }
};
