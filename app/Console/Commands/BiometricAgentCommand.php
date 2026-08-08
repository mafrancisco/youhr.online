<?php

namespace App\Console\Commands;

use App\Services\ZKTecoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * On-premise biometric sync agent.
 *
 * Runs on a machine at the tenant's site, not on the server. Devices that only
 * speak the polling protocol (TCP/UDP) live on a private network that a cloud
 * server has no route to, so the traffic has to start from inside that network:
 * this command polls the device locally and posts the punches out over HTTPS.
 *
 * It holds no local state. Devices return their whole buffer and the server
 * de-duplicates on (device, user, timestamp, punch type), so a crashed or repeated
 * run cannot lose or double-count punches — which also means it is safe to run
 * from cron as often as you like.
 *
 * Setup on the site machine, in .env:
 *
 *   AGENT_SERVER_URL=https://your-hr.net
 *   AGENT_COMPANY_SLUG=siargao-electric-cooperative-inc
 *   AGENT_TOKEN=<from php artisan biometric:agent-token {slug}>
 *
 * Then run every 10 minutes:
 *
 *   ‪*‬/10 * * * * cd /path/to/app && php artisan biometric:agent >> storage/logs/agent.log 2>&1
 */
class BiometricAgentCommand extends Command
{
    protected $signature = 'biometric:agent
                            {--device= : Only poll this device id}
                            {--dry-run : Poll and report without sending anything}';

    protected $description = 'Poll biometric devices on this network and push their punches to the server';

    public function handle(ZKTecoService $zk): int
    {
        $server = rtrim((string) config('agent.server_url'), '/');
        $slug   = (string) config('agent.company_slug');
        $token  = (string) config('agent.token');

        if ($server === '' || $slug === '' || $token === '') {
            $this->error('Agent is not configured. Set AGENT_SERVER_URL, AGENT_COMPANY_SLUG and AGENT_TOKEN.');
            return self::FAILURE;
        }

        $this->line("[" . now()->toDateTimeString() . "] agent run → {$server} ({$slug})");

        // The device list comes from the server so connection details stay in the
        // tenant's own records rather than being duplicated into every site.
        $response = $this->request($server, $slug, $token)->get('/api/v1/biometric/devices');

        if (!$response->successful()) {
            $this->error("Could not fetch device list: HTTP {$response->status()} {$response->body()}");
            return self::FAILURE;
        }

        $devices = $response->json('devices') ?? [];

        if (empty($devices)) {
            $this->warn('No active devices registered for this tenant.');
            return self::SUCCESS;
        }

        if ($only = $this->option('device')) {
            $devices = array_values(array_filter($devices, fn ($d) => (string) $d['id'] === (string) $only));
        }

        $failures = 0;

        foreach ($devices as $device) {
            $failures += $this->syncDevice($zk, $device, $server, $slug, $token) ? 0 : 1;
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Poll one device and push whatever it holds.
     */
    private function syncDevice(ZKTecoService $zk, array $device, string $server, string $slug, string $token): bool
    {
        $label = "{$device['name']} ({$device['ip_address']}:{$device['port']})";

        try {
            // Read-only: the agent has no tenant database, it only forwards punches.
            $punches = $zk->readAttendance($device['ip_address'], (int) $device['port']);
        } catch (\Throwable $e) {
            $this->error("  {$label}: {$e->getMessage()}");
            $this->reportUnreachable($server, $slug, $token, $device);
            return false;
        }

        if ($punches === null) {
            $this->error("  {$label}: unreachable");
            $this->reportUnreachable($server, $slug, $token, $device);
            return false;
        }

        $this->line("  {$label}: read " . count($punches) . ' punch(es)');

        if ($this->option('dry-run')) {
            foreach (array_slice($punches, 0, 5) as $p) {
                $this->line("    {$p['pin']}  {$p['timestamp']}  status={$p['status']}");
            }
            $this->comment('    dry run — nothing sent');
            return true;
        }

        // Send in batches so a large backlog does not hit the request size limit.
        $stored = 0;
        $duplicates = 0;

        foreach (array_chunk($punches, 500) as $batch) {
            $res = $this->request($server, $slug, $token)->post('/api/v1/biometric/punches', [
                'device_id' => $device['id'],
                'reachable' => true,
                'punches'   => $batch,
            ]);

            if (!$res->successful()) {
                $this->error("  {$label}: upload failed HTTP {$res->status()} {$res->body()}");
                return false;
            }

            $stored     += (int) $res->json('stored', 0);
            $duplicates += (int) $res->json('duplicates', 0);
        }

        $this->info("  {$label}: {$stored} new, {$duplicates} already known");

        return true;
    }

    /**
     * Tell the server the device could not be reached, so its status reflects
     * reality instead of whenever a connection last succeeded.
     */
    private function reportUnreachable(string $server, string $slug, string $token, array $device): void
    {
        try {
            $this->request($server, $slug, $token)->post('/api/v1/biometric/punches', [
                'device_id' => $device['id'],
                'reachable' => false,
                'punches'   => [],
            ]);
        } catch (\Throwable) {
            // Reporting status is best-effort; the next run will try again.
        }
    }

    private function request(string $server, string $slug, string $token)
    {
        return Http::baseUrl($server)
            ->withToken($token)
            ->withHeaders(['X-Company-Slug' => $slug])
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 500);
    }
}
