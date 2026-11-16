<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Users are identified by `username` (see User::getAuthIdentifierName()), so the
 * database session handler writes the username into sessions.user_id. With an
 * integer column and strict SQL mode the session insert fails and Laravel's
 * fallback UPDATE silently affects no rows, so every login is lost on redirect.
 */
return new class extends Migration
{
    private function connection(): ?string
    {
        return config('session.connection');
    }

    private function table(): string
    {
        return config('session.table', 'sessions');
    }

    public function up(): void
    {
        $schema = Schema::connection($this->connection());

        if (!$schema->hasTable($this->table())) {
            return;
        }

        $schema->table($this->table(), function (Blueprint $table) {
            $table->string('user_id', 191)->nullable()->change();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection($this->connection());

        if (!$schema->hasTable($this->table())) {
            return;
        }

        // Non-numeric values cannot be kept in an integer column.
        DB::connection($this->connection())->table($this->table())->update(['user_id' => null]);

        $schema->table($this->table(), function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }
};
