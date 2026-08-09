<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\SaaS\Company;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatabaseExportController extends Controller
{
    /**
     * Download a full mysqldump of a single tenant's database (all tables, all data).
     */
    public function download(Company $company): StreamedResponse
    {
        $database = $company->database;
        $command = $this->buildDumpCommand([$database]);
        $filename = $database . '_' . now()->format('Y-m-d_His') . '.sql';

        return $this->streamDump($command, $filename);
    }

    /**
     * Download a full mysqldump of ALL databases (landlord + all tenants).
     */
    public function downloadAll(): StreamedResponse
    {
        $landlordDb = Config::get('database.connections.landlord.database', 'yourhr');
        $tenantDbs = Company::where('status', 'active')->pluck('database')->all();

        $databases = array_merge([$landlordDb], $tenantDbs);
        $command = $this->buildDumpCommand($databases);
        $filename = 'yourhr_full_backup_' . now()->format('Y-m-d_His') . '.sql';

        return $this->streamDump($command, $filename);
    }

    /**
     * Build the mysqldump command ensuring ALL data is included.
     */
    private function buildDumpCommand(array $databases): string
    {
        $host = Config::get('database.connections.landlord.host', '127.0.0.1');
        $port = Config::get('database.connections.landlord.port', '3306');
        $username = Config::get('database.connections.landlord.username', 'root');
        $password = Config::get('database.connections.landlord.password', '');

        $dbArgs = implode(' ', array_map('escapeshellarg', $databases));

        return sprintf(
            'mysqldump --host=%s --port=%s --user=%s %s'
            . ' --single-transaction'
            . ' --routines'
            . ' --triggers'
            . ' --events'
            . ' --complete-insert'
            . ' --hex-blob'
            . ' --add-drop-database'
            . ' --add-drop-table'
            . ' --create-options'
            . ' --set-gtid-purged=OFF'
            . ' --databases %s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            $password !== '' ? '--password=' . escapeshellarg($password) : '',
            $dbArgs
        );
    }

    /**
     * Stream the dump as a downloadable file.
     */
    private function streamDump(string $command, string $filename): StreamedResponse
    {
        return new StreamedResponse(function () use ($command) {
            $process = popen($command . ' 2>/dev/null', 'r');

            if ($process) {
                while (!feof($process)) {
                    echo fread($process, 8192);
                    flush();
                }
                pclose($process);
            }
        }, 200, [
            'Content-Type' => 'application/sql',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, no-store',
        ]);
    }
}
