<?php

namespace Tests\Feature\FormsApp;

use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for POST /api/complete-log.
 *
 * Marks a form_type_id=2 (Inspection Checklist) form as completed,
 * regenerates its PDF, and returns jsonData + formLink.
 * Requires APIToken authentication.
 */
class CompleteLogTest extends FormsAppTestCase
{
    private array $authHeaders;

    protected function setUp(): void
    {
        parent::setUp();
        $room = $this->makeRoom();
        $this->authHeaders = $this->residentHeaders($room);
    }

    // -----------------------------------------------------------------------
    // Validation
    // -----------------------------------------------------------------------

    #[Test]
    public function complete_log_requires_form_id(): void
    {
        $this->withHeaders($this->authHeaders)
            ->postJson('/api/complete-log', ['completed_by' => 1])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '2', 'ResponseText' => 'Please enter Form Id']);
    }

    #[Test]
    public function complete_log_requires_completed_by(): void
    {
        $this->withHeaders($this->authHeaders)
            ->postJson('/api/complete-log', ['form_id' => 1])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '2', 'ResponseText' => 'Please Provide Completed By Id']);
    }

    #[Test]
    public function complete_log_requires_authentication(): void
    {
        $this->postJson('/api/complete-log', ['form_id' => 1, 'completed_by' => 1])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '11']);
    }

    // -----------------------------------------------------------------------
    // Not found / wrong type
    // -----------------------------------------------------------------------

    #[Test]
    public function returns_error_for_unknown_form(): void
    {
        $this->withHeaders($this->authHeaders)
            ->postJson('/api/complete-log', ['form_id' => 9999, 'completed_by' => 1])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '0', 'ResponseText' => 'Form Not Found']);
    }

    // -----------------------------------------------------------------------
    // Happy path — form type 2, Storage::fake() catches PDF write
    // -----------------------------------------------------------------------

    #[Test]
    public function marks_inspection_checklist_as_completed(): void
    {
        Storage::fake('public');

        // First FormType gets id=1; second gets id=2 (Inspection Checklist)
        $this->makeFormType(['name' => 'Incident Report']);
        $ft   = $this->makeFormType(['name' => 'Inspection Checklist']);
        $form = $this->makeFormResponse($ft, [
            'form_response' => ['is_completed' => 0],
        ]);

        $data = $this->withHeaders($this->authHeaders)
            ->postJson('/api/complete-log', [
                'form_id'      => $form->id,
                'completed_by' => 1,
            ])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '1', 'ResponseText' => 'Form Complete Logged Successfully'])
            ->json();

        $this->assertSame(1, $data['jsonData']['is_completed']);
        $this->assertSame(1, $data['jsonData']['completed_by']);
        $this->assertNotNull($data['formLink']);
    }

    #[Test]
    public function persists_completed_flag_in_database(): void
    {
        Storage::fake('public');

        $this->makeFormType(['name' => 'Incident Report']); // id=1
        $ft   = $this->makeFormType(['name' => 'Inspection Checklist']); // id=2
        $form = $this->makeFormResponse($ft, [
            'form_response' => ['is_completed' => 0],
        ]);

        $this->withHeaders($this->authHeaders)
            ->postJson('/api/complete-log', [
                'form_id'      => $form->id,
                'completed_by' => 42,
            ])
            ->assertJson(['ResponseCode' => '1']);

        $updated = $form->fresh();
        $this->assertSame(1, $updated->form_response['is_completed']);
        $this->assertSame(42, $updated->form_response['completed_by']);
    }
}
