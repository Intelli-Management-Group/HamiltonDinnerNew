<?php

namespace Tests\Unit\Repositories;

use App\Models\FormMediaAttachments;
use App\Models\FormResponse;
use App\Models\FormType;
use App\Models\MoveInSummaryValues;
use App\Repositories\Eloquent\Forms\FormMediaAttachmentsRepository;
use App\Repositories\Eloquent\Forms\FormResponseRepository;
use App\Repositories\Eloquent\Forms\FormTypeRepository;
use App\Repositories\Eloquent\Forms\MoveInSummaryValuesRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormRepositoriesTest extends TestCase
{
    use RefreshDatabase;

    private FormMediaAttachmentsRepository $attachments;
    private FormResponseRepository $responses;
    private FormTypeRepository $types;
    private MoveInSummaryValuesRepository $summaryValues;

    protected function setUp(): void
    {
        parent::setUp();
        $this->attachments = new FormMediaAttachmentsRepository(new FormMediaAttachments());
        $this->responses = new FormResponseRepository(new FormResponse());
        $this->types = new FormTypeRepository(new FormType());
        $this->summaryValues = new MoveInSummaryValuesRepository(new MoveInSummaryValues());
    }

    /** @test */
    public function it_creates_and_lists_form_types()
    {
        $this->types->create([
            'name' => 'Incident',
            'allow_print' => 1,
            'allow_mail' => 1,
        ]);

        $results = $this->types->getAll();

        $this->assertCount(1, $results);
    }

    /** @test */
    public function it_fetches_form_responses_with_form_type()
    {
        $formType = $this->types->create([
            'name' => 'Incident',
            'allow_print' => 1,
            'allow_mail' => 1,
        ]);

        $this->responses->create([
            'form_type_id' => $formType->id,
            'room_id' => 1,
            'form_response' => ['foo' => 'bar'],
            'is_follow_up_incomplete' => 0,
            'file_name' => 'file.pdf',
            'follow_up_assigned_to' => 0,
            'created_by' => 1,
        ]);

        $results = $this->responses->getAll(relations: ['formType']);

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->relationLoaded('formType'));
    }

    /** @test */
    public function it_creates_form_media_attachments()
    {
        $formType = $this->types->create([
            'name' => 'Incident',
            'allow_print' => 1,
            'allow_mail' => 1,
        ]);

        $response = $this->responses->create([
            'form_type_id' => $formType->id,
            'room_id' => 1,
            'form_response' => ['foo' => 'bar'],
            'is_follow_up_incomplete' => 0,
            'file_name' => 'file.pdf',
            'follow_up_assigned_to' => 0,
            'created_by' => 1,
        ]);

        $this->attachments->create([
            'name' => 'photo.jpg',
            'type' => 'image',
            'file_extension' => 'jpg',
            'size_in_kb' => 10,
            'thumbnail' => null,
            'form_response_id' => $response->id,
        ]);

        $results = $this->attachments->getAll();

        $this->assertCount(1, $results);
    }

    /** @test */
    public function it_creates_and_finds_move_in_summary_values()
    {
        $record = $this->summaryValues->create([
            'key_param' => 'move_in_date',
            'value' => '2026-01-01',
        ]);

        $found = $this->summaryValues->findById($record->id);

        $this->assertNotNull($found);
        $this->assertSame('move_in_date', $found->key_param);
    }
}
