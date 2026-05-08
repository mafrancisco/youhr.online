<?php

namespace Tests\Unit;

use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class SubmissionModelTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_is_locked_returns_true_when_submitted(): void
    {
        $this->createSubmission('10001', '2025-05');

        $this->assertTrue(Submission::isLocked('10001', '2025-05'));
    }

    public function test_is_locked_returns_false_when_not_submitted(): void
    {
        $this->assertFalse(Submission::isLocked('10001', '2025-05'));
    }

    public function test_is_locked_is_badge_specific(): void
    {
        $this->createSubmission('10001', '2025-05');

        $this->assertTrue(Submission::isLocked('10001', '2025-05'));
        $this->assertFalse(Submission::isLocked('10002', '2025-05'));
    }

    public function test_is_locked_is_period_specific(): void
    {
        $this->createSubmission('10001', '2025-05');

        $this->assertTrue(Submission::isLocked('10001', '2025-05'));
        $this->assertFalse(Submission::isLocked('10001', '2025-06'));
    }
}
