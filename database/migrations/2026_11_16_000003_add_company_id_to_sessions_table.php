<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Record which company each session belongs to (see App\Session\TenantAwareDatabaseSessionHandler).
 * Runs on the session connection (the shared main database), not on tenant databases.
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

        if (!$schema->hasTable($this->table()) || $schema->hasColumn($this->table(), 'company_id')) {
            return;
        }

        $schema->table($this->table(), function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('user_id');
            $table->index(['company_id', 'user_id'], 'sessions_company_user_index');
        });

        // Tag existing sessions from the company id stored in their payload.
        $key = config('saas.company_session_key', 'tenant_company_id');
        $db = DB::connection($this->connection());

        $db->table($this->table())->select('id', 'payload')->orderBy('id')->chunk(500, function ($rows) use ($db, $key) {
            foreach ($rows as $row) {
                $data = @unserialize(base64_decode((string) $row->payload), ['allowed_classes' => false]);
                $companyId = is_array($data) && isset($data[$key]) && is_numeric($data[$key]) ? (int) $data[$key] : null;

                if ($companyId) {
                    $db->table($this->table())->where('id', $row->id)->update(['company_id' => $companyId]);
                }
            }
        });
    }

    public function down(): void
    {
        $schema = Schema::connection($this->connection());

        if (!$schema->hasTable($this->table()) || !$schema->hasColumn($this->table(), 'company_id')) {
            return;
        }

        $schema->table($this->table(), function (Blueprint $table) {
            $table->dropIndex('sessions_company_user_index');
            $table->dropColumn('company_id');
        });
    }
};
