<?php

namespace Tests\Feature\FormsApp;

use App\Models\FormMediaAttachments;
use App\Models\FormResponse;
use App\Models\FormType;
use Tests\Feature\DiningApp\DiningAppTestCase;

/**
 * Shared base for all forms-app feature tests.
 *
 * Extends DiningAppTestCase to inherit token helpers, room/user factories,
 * and the RefreshDatabase trait. Adds form-specific model factories.
 */
abstract class FormsAppTestCase extends DiningAppTestCase
{
    // -----------------------------------------------------------------------
    // Model factories
    // -----------------------------------------------------------------------

    protected function makeFormType(array $attrs = []): FormType
    {
        return FormType::create(array_merge([
            'name'        => 'Test Form',
            'allow_print' => 1,
            'allow_mail'  => 1,
        ], $attrs));
    }

    protected function makeFormResponse(FormType $formType, array $attrs = []): FormResponse
    {
        return FormResponse::create(array_merge([
            'form_type_id'            => $formType->id,
            'form_response'           => ['is_completed' => 0],
            'created_by'              => 1,
            'file_name'               => 'test.pdf',
            'is_follow_up_incomplete' => 0,
            'follow_up_assigned_to'   => 0,
        ], $attrs));
    }

    protected function makeFormAttachment(FormResponse $formResponse, array $attrs = []): FormMediaAttachments
    {
        return FormMediaAttachments::create(array_merge([
            'form_response_id' => $formResponse->id,
            'name'             => 'test.jpg',
            'type'             => 'image',
            'file_extension'   => 'jpg',
            'size_in_kb'       => 100,
        ], $attrs));
    }
}
