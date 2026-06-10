<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $connection = config('saas.landlord_connection', 'landlord');

        if (Schema::connection($connection)->hasTable('companies')) {
            return;
        }

        Schema::connection($connection)->create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 120)->unique();
            $table->string('database', 120)->unique();
            $table->string('owner_google_email', 191);
            $table->string('status', 30)->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection(config('saas.landlord_connection', 'landlord'))->dropIfExists('companies');
    }
};
