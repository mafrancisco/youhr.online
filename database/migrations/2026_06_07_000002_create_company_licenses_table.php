<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $connection = config('saas.landlord_connection', 'landlord');

        if (Schema::connection($connection)->hasTable('company_licenses')) {
            return;
        }

        Schema::connection($connection)->create('company_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('license_key_hash', 64)->index();
            $table->string('bound_email', 191)->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('activated_by_email', 191)->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection(config('saas.landlord_connection', 'landlord'))->dropIfExists('company_licenses');
    }
};
