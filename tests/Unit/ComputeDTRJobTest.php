<?php

namespace Tests\Unit;

use App\Jobs\ComputeDTRJob;
use App\Services\AttendanceComputationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class ComputeDTRJobTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_job_sets_status_to_processing_then_completed(): void
    {
        // Mock the service since it uses MySQL-specific SQL
        $mock = $this->createMock(AttendanceComputationService::class);
        $mock->expects($this->once())->method('compute');

        $job = new ComputeDTRJob('2025-05-05', '2025-05-05');
        $job->handle($mock);

        $this->assertEquals('completed', Cache::get('dtr_computation_status'));
    }

    public function test_job_sets_status_to_failed_on_exception(): void
    {
        // Mock the service to throw an exception
        $mock = $this->createMock(AttendanceComputationService::class);
        $mock->method('compute')->willThrowException(new \RuntimeException('Test error'));

        $job = new ComputeDTRJob('2025-05-05', '2025-05-05');

        try {
            $job->handle($mock);
        } catch (\RuntimeException) {
            // Expected
        }

        $this->assertEquals('failed', Cache::get('dtr_computation_status'));
    }

    public function test_job_is_queueable(): void
    {
        $job = new ComputeDTRJob('2025-05-01', '2025-05-31');

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }
}
