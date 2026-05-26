<?php

namespace Tests\Feature\FormsApp;

use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for POST /api/list-forms.
 *
 * Returns FormResponse records filtered by form_type_id.
 * Requires APIToken authentication.
 */
class ListFormsTest extends FormsAppTestCase
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
    public function list_forms_requires_form_type(): void
    {
        $this->withHeaders($this->authHeaders)
            ->postJson('/api/list-forms', [])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '2', 'ResponseText' => 'Please enter Form type']);
    }

    #[Test]
    public function list_forms_requires_authentication(): void
    {
        $this->postJson('/api/list-forms', ['form_type' => 1])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '11']);
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function returns_empty_list_when_no_forms_for_type(): void
    {
        $ft = $this->makeFormType();

        $data = $this->withHeaders($this->authHeaders)
            ->postJson('/api/list-forms', ['form_type' => $ft->id])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '1', 'ResponseText' => 'List Retrieved Successfully'])
            ->json();

        $this->assertEmpty($data['list']);
    }

    #[Test]
    public function returns_forms_for_given_type(): void
    {
        $ft   = $this->makeFormType(['name' => 'Incident Report']);
        $form = $this->makeFormResponse($ft);

        $data = $this->withHeaders($this->authHeaders)
            ->postJson('/api/list-forms', ['form_type' => $ft->id])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '1'])
            ->json();

        $this->assertCount(1, $data['list']);
        $this->assertEquals($form->id, $data['list'][0]['id']);
    }

    #[Test]
    public function does_not_return_forms_of_other_types(): void
    {
        $ftA = $this->makeFormType(['name' => 'Type A']);
        $ftB = $this->makeFormType(['name' => 'Type B']);
        $this->makeFormResponse($ftA);
        $this->makeFormResponse($ftB);

        $data = $this->withHeaders($this->authHeaders)
            ->postJson('/api/list-forms', ['form_type' => $ftA->id])
            ->assertStatus(200)
            ->json();

        $this->assertCount(1, $data['list']);
        $this->assertSame($ftA->id, $data['list'][0]['form_type_id']);
    }

    #[Test]
    public function returns_multiple_forms_for_same_type(): void
    {
        $ft = $this->makeFormType();
        $this->makeFormResponse($ft);
        $this->makeFormResponse($ft);
        $this->makeFormResponse($ft);

        $data = $this->withHeaders($this->authHeaders)
            ->postJson('/api/list-forms', ['form_type' => $ft->id])
            ->assertStatus(200)
            ->json();

        $this->assertCount(3, $data['list']);
    }

    #[Test]
    public function each_form_includes_form_type_relation(): void
    {
        $ft = $this->makeFormType(['name' => 'Inspection Checklist']);
        $this->makeFormResponse($ft);

        $data = $this->withHeaders($this->authHeaders)
            ->postJson('/api/list-forms', ['form_type' => $ft->id])
            ->assertStatus(200)
            ->json();

        $this->assertArrayHasKey('form_type', $data['list'][0]);
        $this->assertSame('Inspection Checklist', $data['list'][0]['form_type']['name']);
    }
}
