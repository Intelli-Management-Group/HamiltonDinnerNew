<?php

namespace Tests\Feature\FormsApp;

use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for POST /api/form-details.
 *
 * Returns form_data, attachments, and follow_up_user for a given form_id.
 * Requires APIToken authentication.
 */
class FormDetailsTest extends FormsAppTestCase
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
    public function form_details_requires_form_id(): void
    {
        $this->withHeaders($this->authHeaders)
            ->postJson('/api/form-details', [])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '2', 'ResponseText' => 'Please enter Form Id']);
    }

    #[Test]
    public function form_details_requires_authentication(): void
    {
        $this->postJson('/api/form-details', ['form_id' => 1])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '11']);
    }

    // -----------------------------------------------------------------------
    // Not found
    // -----------------------------------------------------------------------

    #[Test]
    public function returns_not_found_response_for_unknown_form(): void
    {
        $this->withHeaders($this->authHeaders)
            ->postJson('/api/form-details', ['form_id' => 9999])
            ->assertStatus(200)
            ->assertJson([
                'ResponseCode'   => '0',
                'ResponseText'   => 'No Form Details Found',
                'form_data'      => null,
                'attachments'    => [],
                'follow_up_user' => null,
            ]);
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function returns_form_data_with_empty_attachments(): void
    {
        $ft   = $this->makeFormType(['name' => 'Incident Report']);
        $form = $this->makeFormResponse($ft);

        $data = $this->withHeaders($this->authHeaders)
            ->postJson('/api/form-details', ['form_id' => $form->id])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '1', 'ResponseText' => 'Fetched Form Data Successfully'])
            ->json();

        $this->assertSame($form->id, $data['form_data']['id']);
        $this->assertEmpty($data['attachments']);
        $this->assertNull($data['follow_up_user']);
    }

    #[Test]
    public function returns_attachments_for_form(): void
    {
        $ft         = $this->makeFormType();
        $form       = $this->makeFormResponse($ft);
        $attachment = $this->makeFormAttachment($form, ['name' => 'photo.jpg', 'type' => 'image']);

        $data = $this->withHeaders($this->authHeaders)
            ->postJson('/api/form-details', ['form_id' => $form->id])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '1'])
            ->json();

        $this->assertCount(1, $data['attachments']);
        $this->assertSame($attachment->id, $data['attachments'][0]['id']);
    }

    #[Test]
    public function does_not_return_attachments_from_other_forms(): void
    {
        $ft    = $this->makeFormType();
        $formA = $this->makeFormResponse($ft);
        $formB = $this->makeFormResponse($ft);
        $this->makeFormAttachment($formA, ['name' => 'belongs_to_a.jpg']);
        $this->makeFormAttachment($formB, ['name' => 'belongs_to_b.jpg']);

        $data = $this->withHeaders($this->authHeaders)
            ->postJson('/api/form-details', ['form_id' => $formA->id])
            ->assertStatus(200)
            ->json();

        $this->assertCount(1, $data['attachments']);
        $this->assertSame('belongs_to_a.jpg', $data['attachments'][0]['name']);
    }

    #[Test]
    public function does_not_return_soft_deleted_attachments(): void
    {
        $ft         = $this->makeFormType();
        $form       = $this->makeFormResponse($ft);
        $attachment = $this->makeFormAttachment($form);
        $attachment->delete(); // soft delete

        $data = $this->withHeaders($this->authHeaders)
            ->postJson('/api/form-details', ['form_id' => $form->id])
            ->assertStatus(200)
            ->json();

        $this->assertEmpty($data['attachments']);
    }
}
