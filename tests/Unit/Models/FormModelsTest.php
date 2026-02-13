<?php

namespace Tests\Unit\Models;

use App\Models\FormMediaAttachments;
use App\Models\FormResponse;
use App\Models\FormType;
use App\Models\TempFormMediaAttachments;
use App\Models\TempFormResponse;
use App\Models\TempFormType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FormModelsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function form_media_attachments_accessors_build_paths()
    {
        Storage::fake('public');

        $attachment = new FormMediaAttachments([
            'name' => 'file.pdf',
            'thumbnail' => 'thumb.jpg',
        ]);

        $this->assertStringContainsString(
            'FormResponses/media/file.pdf',
            $attachment->path
        );

        $this->assertStringContainsString(
            'FormResponses/media/thumbnail/thumb.jpg',
            $attachment->thumbImage
        );
    }

    /** @test */
    public function temp_form_media_attachments_accessors_build_paths()
    {
        Storage::fake('public');

        $attachment = new TempFormMediaAttachments([
            'name' => 'file.pdf',
            'thumbnail' => 'thumb.jpg',
        ]);

        $this->assertStringContainsString(
            'TempFormResponses/media/file.pdf',
            $attachment->path
        );

        $this->assertStringContainsString(
            'TempFormResponses/media/thumbnail/thumb.jpg',
            $attachment->thumbImage
        );
    }

    /** @test */
    public function form_response_accessors_return_links_and_json()
    {
        Storage::fake('public');

        $response = new FormResponse([
            'file_name' => 'response.pdf',
            'form_response' => ['foo' => 'bar'],
        ]);

        $this->assertStringContainsString(
            'FormResponses/response.pdf',
            $response->formLink
        );
        $this->assertSame(['foo' => 'bar'], $response->jsonData);

        $response->form_response = json_encode(['hello' => 'world']);
        $this->assertSame(['hello' => 'world'], $response->jsonData);
    }

    /** @test */
    public function form_response_relations_are_configured()
    {
        $response = new FormResponse();

        $this->assertInstanceOf(BelongsTo::class, $response->formType());
        $this->assertInstanceOf(HasMany::class, $response->attachments());
    }

    /** @test */
    public function temp_form_response_formats_timestamps()
    {
        $response = new TempFormResponse();
        $response->setRawAttributes([
            'created_at' => '2026-02-12 00:00:00',
            'updated_at' => '2026-02-12 01:00:00',
        ]);

        $this->assertSame('2026-02-12 00:00:00', $response->created_at);
        $this->assertSame('2026-02-12 01:00:00', $response->updated_at);
    }

    /** @test */
    public function form_type_uses_soft_deletes()
    {
        $this->assertContains(
            SoftDeletes::class,
            class_uses_recursive(FormType::class)
        );
    }

    /** @test */
    public function temp_form_type_fillable_includes_expected_fields()
    {
        $tempFormType = new TempFormType();

        $this->assertSame(
            ['name', 'form_fields', 'is_published', 'allow_print', 'allow_mail'],
            $tempFormType->getFillable()
        );
    }
}
