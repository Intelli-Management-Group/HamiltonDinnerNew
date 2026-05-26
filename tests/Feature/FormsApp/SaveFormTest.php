<?php

namespace Tests\Feature\FormsApp;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for form submission and attachment endpoints:
 *
 *   POST /api/general-form-submit-phase1   (saveFormPhase1)
 *   POST /api/edit-form-phase1             (editGeneratedFormResponsePhase1)
 *   POST /api/add-form-attachment-phase1   (addAttachmentsToExistingFormPhase1)
 *   POST /api/delete-form-attachment-phase1 (deleteFormAttachmentPhase1)
 *
 * Notes on file uploads:
 *   These controllers read $_FILES directly (not $request->file()). In the
 *   test environment the superglobal is always empty, so only validation
 *   failure paths and non-file logic can be exercised via HTTP tests.
 *   The service-layer happy paths are covered by unit tests.
 */
class SaveFormTest extends FormsAppTestCase
{
    private array $authHeaders;

    protected function setUp(): void
    {
        parent::setUp();
        $room = $this->makeRoom();
        $this->authHeaders = $this->residentHeaders($room);
    }

    // -----------------------------------------------------------------------
    // POST /api/general-form-submit-phase1 — validation
    // -----------------------------------------------------------------------

    #[Test]
    public function save_form_requires_authentication(): void
    {
        $this->postJson('/api/general-form-submit-phase1', [])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '11']);
    }

    #[Test]
    public function save_form_requires_form_type(): void
    {
        $this->withHeaders($this->authHeaders)
            ->postJson('/api/general-form-submit-phase1', [
                'data' => json_encode(['field' => 'value']),
            ])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '2', 'ResponseText' => 'Please enter Form Type']);
    }

    #[Test]
    public function save_form_requires_data(): void
    {
        $this->withHeaders($this->authHeaders)
            ->postJson('/api/general-form-submit-phase1', [
                'form_type' => 1,
            ])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '2', 'ResponseText' => 'Please enter Form Data']);
    }

    // -----------------------------------------------------------------------
    // POST /api/edit-form-phase1 — validation
    // -----------------------------------------------------------------------

    #[Test]
    public function edit_form_requires_authentication(): void
    {
        $this->postJson('/api/edit-form-phase1', [])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '11']);
    }

    #[Test]
    public function edit_form_requires_form_id(): void
    {
        $this->withHeaders($this->authHeaders)
            ->postJson('/api/edit-form-phase1', [
                'data' => json_encode(['field' => 'value']),
            ])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '2', 'ResponseText' => 'Please enter Form Id']);
    }

    #[Test]
    public function edit_form_requires_data(): void
    {
        $this->withHeaders($this->authHeaders)
            ->postJson('/api/edit-form-phase1', [
                'form_id' => 1,
            ])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '2', 'ResponseText' => 'Please enter Form Data']);
    }

    // -----------------------------------------------------------------------
    // POST /api/add-form-attachment-phase1 — validation
    // -----------------------------------------------------------------------

    #[Test]
    public function add_attachment_requires_authentication(): void
    {
        $this->postJson('/api/add-form-attachment-phase1', [])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '11']);
    }

    #[Test]
    public function add_attachment_requires_form_id(): void
    {
        $this->withHeaders($this->authHeaders)
            ->postJson('/api/add-form-attachment-phase1', [])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '2', 'ResponseText' => 'Please enter Form Id']);
    }

    // -----------------------------------------------------------------------
    // POST /api/delete-form-attachment-phase1 — validation
    // -----------------------------------------------------------------------

    #[Test]
    public function delete_attachment_requires_authentication(): void
    {
        $this->postJson('/api/delete-form-attachment-phase1', [])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '11']);
    }

    #[Test]
    public function delete_attachment_requires_form_id(): void
    {
        $this->withHeaders($this->authHeaders)
            ->postJson('/api/delete-form-attachment-phase1', ['attachment_id' => 1])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '2', 'ResponseText' => 'Please enter Form Id']);
    }

    #[Test]
    public function delete_attachment_requires_attachment_id(): void
    {
        $this->withHeaders($this->authHeaders)
            ->postJson('/api/delete-form-attachment-phase1', ['form_id' => 1])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '2', 'ResponseText' => 'Please enter Attachment Id']);
    }

    // -----------------------------------------------------------------------
    // POST /api/delete-form-attachment-phase1 — not found
    // -----------------------------------------------------------------------

    #[Test]
    public function delete_attachment_returns_error_for_unknown_attachment(): void
    {
        Storage::fake('public');

        $ft         = $this->makeFormType();
        $form       = $this->makeFormResponse($ft);

        $this->withHeaders($this->authHeaders)
            ->postJson('/api/delete-form-attachment-phase1', [
                'form_id'       => $form->id,
                'attachment_id' => 9999,
            ])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '0', 'ResponseText' => 'Attachment Not Found']);
    }

    #[Test]
    public function delete_attachment_returns_error_when_attachment_belongs_to_different_form(): void
    {
        Storage::fake('public');

        $ft      = $this->makeFormType();
        $formA   = $this->makeFormResponse($ft);
        $formB   = $this->makeFormResponse($ft);
        $att     = $this->makeFormAttachment($formA);

        // Try to delete an attachment that belongs to formA using formB's id
        $this->withHeaders($this->authHeaders)
            ->postJson('/api/delete-form-attachment-phase1', [
                'form_id'       => $formB->id,
                'attachment_id' => $att->id,
            ])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '0', 'ResponseText' => 'Attachment Not Found']);
    }
}
