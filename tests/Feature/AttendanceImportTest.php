<?php

namespace Tests\Feature;

use App\Jobs\ComputeDTRJob;
use App\Services\AttendanceImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class AttendanceImportTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_hr_can_access_upload_page(): void
    {
        $user = $this->createHRUser();

        $response = $this->actingAs($user)->get('/attendance/upload');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Attendance/Upload')
                ->has('computationStatus')
        );
    }

    public function test_hr_can_import_attendance_file(): void
    {
        Queue::fake();

        $user = $this->createHRUser();
        $employee = $this->createEmployee(['badgeID' => '10001', 'empStatus' => 1]);

        // Create a simple DAT file with valid format
        $content = "10001\t2025-05-05 08:02:00\t0\t0\n10001\t2025-05-05 17:05:00\t0\t3\n";
        $file = UploadedFile::fake()->createWithContent('attendance.dat', $content);

        // Mock the import service since it uses MySQL-specific SQL
        $this->mock(AttendanceImportService::class, function ($mock) {
            $mock->shouldReceive('import')->once()->andReturn(2);
        });

        $response = $this->actingAs($user)->post('/attendance/import', [
            'file'       => $file,
            'start_date' => '2025-05-05',
            'end_date'   => '2025-05-05',
            'emp_status' => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify computation job was dispatched
        Queue::assertPushed(ComputeDTRJob::class);
    }

    public function test_import_validates_required_fields(): void
    {
        $user = $this->createHRUser();

        $response = $this->actingAs($user)->post('/attendance/import', []);

        $response->assertSessionHasErrors(['file', 'start_date', 'end_date', 'emp_status']);
    }

    public function test_import_validates_end_date_after_start_date(): void
    {
        $user = $this->createHRUser();
        $file = UploadedFile::fake()->createWithContent('test.dat', "10001\t2025-05-05 08:00:00\t0\t0");

        $response = $this->actingAs($user)->post('/attendance/import', [
            'file'       => $file,
            'start_date' => '2025-05-10',
            'end_date'   => '2025-05-01', // before start
            'emp_status' => 1,
        ]);

        $response->assertSessionHasErrors('end_date');
    }

    public function test_import_validates_emp_status(): void
    {
        $user = $this->createHRUser();
        $file = UploadedFile::fake()->createWithContent('test.dat', "10001\t2025-05-05 08:00:00\t0\t0");

        $response = $this->actingAs($user)->post('/attendance/import', [
            'file'       => $file,
            'start_date' => '2025-05-01',
            'end_date'   => '2025-05-31',
            'emp_status' => 5, // invalid
        ]);

        $response->assertSessionHasErrors('emp_status');
    }

    public function test_hr_can_trigger_recompute(): void
    {
        Queue::fake();

        $user = $this->createHRUser();

        $response = $this->actingAs($user)->post('/attendance/compute', [
            'start_date' => '2025-05-01',
            'end_date'   => '2025-05-31',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Queue::assertPushed(ComputeDTRJob::class);
    }

    public function test_computation_status_endpoint_returns_json(): void
    {
        $user = $this->createHRUser();

        $response = $this->actingAs($user)->get('/attendance/computation-status');

        $response->assertStatus(200);
        $response->assertJsonStructure(['status']);
    }

    public function test_employee_cannot_import_attendance(): void
    {
        $user = $this->createEmployeeUser();
        $file = UploadedFile::fake()->createWithContent('test.dat', "data");

        $response = $this->actingAs($user)->post('/attendance/import', [
            'file'       => $file,
            'start_date' => '2025-05-01',
            'end_date'   => '2025-05-31',
            'emp_status' => 1,
        ]);

        $response->assertStatus(403);
    }
}
