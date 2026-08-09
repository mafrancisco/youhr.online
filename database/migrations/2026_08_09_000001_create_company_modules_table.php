<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $connection = config('saas.landlord_connection', 'landlord');

        if (Schema::connection($connection)->hasTable('company_modules')) {
            return;
        }

        Schema::connection($connection)->create('company_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('module', 60);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::connection(config('saas.landlord_connection', 'landlord'))->dropIfExists('company_modules');
    }
};
