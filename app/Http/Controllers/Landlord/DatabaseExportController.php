<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\SaaS\Company;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatabaseExportController extends Controller
{
    /**
     * Download a mysqldump of the tenant's database.
     */
    public function download(Company $company): StreamedResponse
    {
        $database = $company->database;

        // Use the landlord connection credentials (same MySQL server)
        $host = Config::get('database.connections.landlord.host', '127.0.0.1');
        $port = Config::get('database.connections.landlord.port', '3306');
        $username = Config::get('database.connections.landlord.username', 'root');
        $password = Config::get('database.connections.landlord.password', '');

        // Build mysqldump command
        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s %s --single-transaction --routines --triggers %s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            $password !== '' ? '--password=' . escapeshellarg($password) : '',
            escapeshellarg($database)
        );

        $filename = $database . '_' . now()->format('Y-m-d_His') . '.sql';

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
