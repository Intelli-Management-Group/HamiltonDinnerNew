<?php

namespace Tests\Feature\FormsApp;

use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for POST /api/delete-form.
 *
 * Soft-deletes a FormResponse and bulk-soft-deletes its attachments.
 * Requires APIToken authentication.
 */
class DeleteFormTest extends FormsAppTestCase
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
    public function delete_form_requires_form_id(): void
    {
        $this->withHeaders($this->authHeaders)
            ->postJson('/api/delete-form', [])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '2', 'ResponseText' => 'Please enter Form Id']);
    }

    #[Test]
    public function delete_form_requires_authentication(): void
    {
        $this->postJson('/api/delete-form', ['form_id' => 1])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '11']);
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function deletes_form_response(): void
    {
        $ft   = $this->makeFormType();
        $form = $this->makeFormResponse($ft);

        $this->withHeaders($this->authHeaders)
            ->postJson('/api/delete-form', ['form_id' => $form->id])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '1', 'ResponseText' => 'Form Response Deleted Successfully']);

        // FormResponse uses a hard delete (no SoftDeletes trait on the model)
        $this->assertDatabaseMissing('form_responses', ['id' => $form->id]);
    }

    #[Test]
    public function also_soft_deletes_attachments(): void
    {
        $ft   = $this->makeFormType();
        $form = $this->makeFormResponse($ft);
        $att1 = $this->makeFormAttachment($form, ['name' => 'a.jpg']);
        $att2 = $this->makeFormAttachment($form, ['name' => 'b.jpg']);

        $this->withHeaders($this->authHeaders)
            ->postJson('/api/delete-form', ['form_id' => $form->id])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '1']);

        $this->assertSoftDeleted('form_media_attachments', ['id' => $att1->id]);
        $this->assertSoftDeleted('form_media_attachments', ['id' => $att2->id]);
    }

    #[Test]
    public function returns_error_for_unknown_form_id(): void
    {
        $this->withHeaders($this->authHeaders)
            ->postJson('/api/delete-form', ['form_id' => 9999])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '0', 'ResponseText' => 'Form Not Found']);
    }
}
