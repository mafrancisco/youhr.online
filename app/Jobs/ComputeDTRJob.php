<?php

namespace App\Jobs;

use App\Services\AttendanceComputationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class ComputeDTRJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(
        private string $startDate,
        private string $endDate,
    ) {}

    public function handle(AttendanceComputationService $computer): void
    {
        Cache::put('dtr_computation_status', 'processing', now()->addMinutes(10));

        try {
            $computer->compute($this->startDate, $this->endDate);
            Cache::put('dtr_computation_status', 'completed', now()->addMinutes(5));
        } catch (\Throwable $e) {
            Cache::put('dtr_computation_status', 'failed', now()->addMinutes(5));
            throw $e;
        }
    }
}
