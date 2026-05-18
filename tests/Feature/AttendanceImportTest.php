<?php

namespace Tests\Feature;

use App\Services\AttendanceComputationService;
use App\Services\AttendanceImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class AttendanceImportTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    public function test_hr_can_access_upload_page(): void
    {
        $user = $this->createHRUser();

        $response = $this->actingAs($user)->get('/attendance/upload');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Attendance/Upload')
        );
    }

    public function test_hr_can_import_attendance_file(): void
    {
        $user = $this->createHRUser();
        $this->createEmployee(['badgeID' => '10001', 'empStatus' => 1]);

        $content = "10001\t2025-05-05 08:02:00\t0\t0\n10001\t2025-05-05 17:05:00\t0\t3\n";
        $file = UploadedFile::fake()->createWithContent('attendance.dat', $content);

        // Mock both services since they use MySQL-specific SQL
        $this->mock(AttendanceImportService::class, function ($mock) {
            $mock->shouldReceive('import')->once()->andReturn(2);
        });
        $this->mock(AttendanceComputationService::class, function ($mock) {
            $mock->shouldReceive('compute')->once();
        });

        $response = $this->actingAs($user)->post('/attendance/import', [
            'files'      => [$file],
            'start_date' => '2025-05-05',
            'end_date'   => '2025-05-05',
            'emp_status' => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_import_validates_required_fields(): void
    {
        $user = $this->createHRUser();

        $response = $this->actingAs($user)->post('/attendance/import', []);

        $response->assertSessionHasErrors(['files', 'start_date', 'end_date', 'emp_status']);
    }

    public function test_import_validates_end_date_after_start_date(): void
    {
        $user = $this->createHRUser();
        $file = UploadedFile::fake()->createWithContent('test.dat', "10001\t2025-05-05 08:00:00\t0\t0");

        $response = $this->actingAs($user)->post('/attendance/import', [
            'files'      => [$file],
            'start_date' => '2025-05-10',
            'end_date'   => '2025-05-01',
            'emp_status' => 1,
        ]);

        $response->assertSessionHasErrors('end_date');
    }

    public function test_import_validates_emp_status(): void
    {
        $user = $this->createHRUser();
        $file = UploadedFile::fake()->createWithContent('test.dat', "10001\t2025-05-05 08:00:00\t0\t0");

        $response = $this->actingAs($user)->post('/attendance/import', [
            'files'      => [$file],
            'start_date' => '2025-05-01',
            'end_date'   => '2025-05-31',
            'emp_status' => 5,
        ]);

        $response->assertSessionHasErrors('emp_status');
    }

    public function test_hr_can_trigger_recompute(): void
    {
        $user = $this->createHRUser();

        $this->mock(AttendanceComputationService::class, function ($mock) {
            $mock->shouldReceive('compute')->once();
        });

        $response = $this->actingAs($user)->post('/attendance/compute', [
            'start_date' => '2025-05-01',
            'end_date'   => '2025-05-31',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_employee_cannot_import_attendance(): void
    {
        $user = $this->createEmployeeUser();
        $file = UploadedFile::fake()->createWithContent('test.dat', "data");

        $response = $this->actingAs($user)->post('/attendance/import', [
            'files'      => [$file],
            'start_date' => '2025-05-01',
            'end_date'   => '2025-05-31',
            'emp_status' => 1,
        ]);

        $response->assertStatus(403);
    }
}
