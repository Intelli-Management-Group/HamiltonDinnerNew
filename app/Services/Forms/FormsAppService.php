<?php

namespace App\Services\Forms;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

use Illuminate\Support\Facades\Hash;

use App\Support\ApiResponse;

use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\Forms\FormMediaAttachmentsRepositoryInterface;
use App\Repositories\Contracts\Forms\FormResponseRepositoryInterface;
use App\Repositories\Contracts\Forms\FormTypeRepositoryInterface;
use App\Repositories\Contracts\Forms\MoveInSummaryValuesRepositoryInterface;

class FormsAppService
{
    /**
     * Maps form_type_id → Blade view name used to render the PDF.
     *
     * form_type_id 1 = Incident Report     → resources/views/demo-form-copy-backup.blade.php
     * form_type_id 2 = Inspection Checklist → resources/views/temp-form-template.blade.php
     * form_type_id 3 = Resident Move-In Summary → resources/views/resident-move-in-summary.blade.php
     *
     * If a new form type is added to the form_types table, add a matching entry here
     * and create the corresponding Blade view, otherwise saveFormResponseToStorage()
     * will throw an exception.
     */
    private const FORM_TEMPLATES = [
        1 => 'demo-form-copy-backup',
        2 => 'temp-form-template',
        3 => 'resident-move-in-summary',
    ];

    public function __construct(
        private UserRepositoryInterface $users,
        private FormMediaAttachmentsRepositoryInterface $formMediaAttachments,
        private FormResponseRepositoryInterface $formResponses,
        private FormTypeRepositoryInterface $formTypes,
        private MoveInSummaryValuesRepositoryInterface $moveInSummaryValues
    ) {}

    public function getGeneratedForms($formTypeId = null)
    {
        try {
            $filters = [];
            if ($formTypeId !== null) {
                $filters['form_type_id'] = $formTypeId;
            }

            $list = $this->formResponses->getAll(
                filters: $filters,
                relations: ['formType'],
            );

            return ApiResponse::format(
                status: '1',
                message: 'List Retrieved Successfully',
                data: ['list' => $list]
            );
        } catch (\Exception $e) {
            return ApiResponse::format(
                status: '0',
                message: $e->getMessage()
            );
        }
    }

    public function getFormDetails($formId)
    {
        try {
            $form = $this->formResponses->findById($formId);

            if (!$form) {
                return ApiResponse::format(
                    status: '0',
                    message: 'No Form Details Found',
                    data: [
                        'form_data' => null,
                        'attachments' => [],
                        'follow_up_user' => null
                    ]
                );
            }

            $attachments = $this->formMediaAttachments
                ->getAll(filters: ['form_response_id' => $formId])
                ->toArray();

            $followUpUser = null;
            if (!empty($form->follow_up_assigned_to)) {
                $user = $this->users->findById(
                    $form->follow_up_assigned_to,
                    relations: ['roleModel']
                );
                $followUpUser = [
                    'id'        => $user->id,
                    'name'      => $user->name,
                    'email'     => $user->email,
                    'role_name' => $user->roleModel?->name,
                ];
            }

            return ApiResponse::format(
                status: '1',
                message: 'Fetched Form Data Successfully',
                data: [
                    'form_data'      => $form,
                    'attachments'    => $attachments,
                    'follow_up_user' => $followUpUser,
                ]
            );
        } catch (\Exception $e) {
            return ApiResponse::format(
                status: '0',
                message: $e->getMessage()
            );
        }
    }

    public function deleteFormResponse($formId)
    {
        try {
            $form = $this->formResponses->findById($formId);

            if (!$form) {
                return ApiResponse::format(
                    status: '0',
                    message: 'Form Not Found'
                );
            }

            // FormResponse uses hard delete (no SoftDeletes trait on the model)
            $this->formResponses->delete($form);

            // Attachments use SoftDeletes, so bulkDeleteByIds stamps deleted_at
            $attachments = $this->formMediaAttachments
                ->getAll(filters: ['form_response_id' => $formId])
                ->pluck('id')
                ->toArray();

            $this->formMediaAttachments->bulkDeleteByIds($attachments);

            // TODO: The physical media files in storage/public/FormResponses/media/ are
            // NOT deleted here. Soft-deleting the DB record is enough for the app to stop
            // serving them, but the files accumulate on disk. If storage becomes a concern,
            // uncomment and adapt:
            // foreach ($attachments as $attachment) {
            //     Storage::delete('public/FormResponses/media/' . $attachment->name);
            // }

            return ApiResponse::format(
                status: '1',
                message: 'Form Response Deleted Successfully'
            );
        } 
        catch (\Exception $e) {
            return ApiResponse::format(
                status: '0',
                message: $e->getMessage()
            );
        }
    }

    /**
     * Mark an Inspection Checklist (form_type_id == 2) as completed and regenerate its PDF.
     *
     * IMPORTANT: This function only acts on form_type_id == 2.
     * If called with any other form type, it silently does nothing and returns null.
     * This is intentional by design but easy to miss — the function name suggests it
     * works generically, but it does not.
     *
     * Flow:
     *   1. Load the form response. Return error if not found.
     *   2. Check if form_response is already decoded (array) or still a JSON string.
     *      The form_response column may arrive as an array (via Eloquent cast) or as
     *      a raw JSON string depending on how the model was retrieved / mutated.
     *   3. If type == 2 and not yet completed:
     *      a. Stamp is_completed, completed_by, completed_at into the JSON payload.
     *      b. Delete the old PDF file from storage.
     *      c. Generate a new PDF with the updated data and return a link.
     *   4. If the JSON is a string (not array): check is_completed and return an error
     *      if already completed. (Note: this branch does not return on success — it
     *      falls through and returns null implicitly. This is a known limitation.)
     */
    public function completeFormLog($formId, $completedBy)
    {
        try {
            $form = $this->formResponses->findById($formId);
            if (!$form) {
                return ApiResponse::format(
                    status: '0',
                    message: 'Form Not Found'
                );
            }

            $jsonData = $form->form_response;

            // form_response may be pre-decoded (array) via Eloquent cast, or still a string
            if (is_array($jsonData)) {
                // Only Inspection Checklist (type 2) is supported by this endpoint
                if ($form->form_type_id == 2 && $jsonData['is_completed'] != 1) {

                    $jsonData['is_completed'] = 1;
                    $jsonData['completed_by'] = $completedBy;
                    $jsonData['completed_at'] = Carbon::now()->toDateTimeString();

                    $form->form_response = $jsonData;

                    $uniqueFileName = uniqid() . time() . '.pdf';
                    if ($form->file_name) {
                        Storage::delete('public/FormResponses/' . $form->file_name);
                    }

                    $form->file_name = $uniqueFileName;
                    $this->formResponses->save($form);

                    $this->saveFormResponseToStorage(
                        $form->form_type_id,
                        $jsonData,
                        $uniqueFileName
                    );

                    return ApiResponse::format(
                        status: '1',
                        message: 'Form Complete Logged Successfully',
                        data: [
                            'jsonData' => $jsonData,
                            'formLink' => Storage::url('public/FormResponses/' . $uniqueFileName)
                        ]
                    );
                }
                // If form_type_id != 2, or already completed: falls through and returns null.
            } else {
                $jsonResponse = json_decode($jsonData, true);

                if ($jsonResponse['is_completed'] == 1) {
                    return ApiResponse::format(
                        status: '0',
                        message: 'Form is already completed !'
                    );
                }
                // If not yet completed and not type 2: also falls through and returns null.
            }
        } catch (\Exception $e) {
            return ApiResponse::format(
                status: '0',
                message: $e->getMessage()
            );
        }
    }

    public function sendEmail($toEmail, $formId)
    {
        try {
            $form = $this->formResponses->findById($formId);

            $fileName = $form->file_name;
            $userId = $form->created_by;
            $formTypeId = $form->form_type_id;

            $userName = $this->users->findById($userId)?->name;
            $formType = $this->formTypes->findById($formTypeId)?->name;

            $data = [
                'email' => $toEmail,
                'title' => $formType . " Submitted By " . $userName,
                'body' => 'The User Response can be seen in the Attachment'
            ];

            Mail::send(
                'emails.form-response',
                $data,
                function ($message) use ($data, $fileName) {
                    $message->to($data['email'], $data['email'])
                        ->subject($data['title'])
                        ->attach(
                            storage_path('app/public/FormResponses/' . $fileName)
                        );
                }
            );

            return ApiResponse::format(
                status: '1',
                message: 'Mailed Successfully'
            );

        } catch (\Exception $e) {
            return ApiResponse::format(
                status: '0',
                message: $e->getMessage()
            );
        }
    }

    public function getMoveInSummaryValues()
    {
        $data = $this->moveInSummaryValues->getAll(
            filters: [],
            columns: ['key_param', 'value']
        );

        // transform to key-value pair
        $finalData = $data->pluck('value', 'key_param')->toArray();

        return ApiResponse::format(
            status: '1',
            message: '', 
            data: ['Data' => $finalData]
        );
    }

    /**
     * Create a new form response from a submitted form.
     *
     * IMPORTANT — file handling limitation:
     *   This function references $files, which is NOT a parameter — it reads directly
     *   from the $_FILES superglobal (implicitly available as $files via the controller).
     *   This means file uploads cannot be tested via Laravel's HTTP test infrastructure
     *   ($request->file() is populated, but $_FILES is not). Only validation-failure
     *   paths are testable in automated tests for this function.
     *
     * Flow:
     *   1. Validate form_type_id is known (in FORM_TEMPLATES).
     *   2. For form type 3 (Move-In Summary), require a signature file upfront.
     *   3. Create the form_response DB record with a placeholder file_name.
     *   4. Iterate $_FILES: save each non-thumbnail file to storage; for videos,
     *      also save the paired thumbnail (keyed as "thumbnail{N}" for file "file{N}").
     *   5. Build the PDF data array, injecting image filenames and follow-up assignee name.
     *   6. For type 3: require at least one image (the signature) or abort.
     *   7. Generate the PDF and write it to storage/public/FormResponses/.
     *   8. Set is_follow_up_incomplete flag for type 1 (Incident Report) based on
     *      whether any follow-up fields were filled in.
     *
     * is_follow_up_incomplete: 1 if any of the followUp_* fields are non-empty, else 0.
     * This flag is used by the client to visually mark forms that need follow-up.
     */
    public function saveForm(
        $userId,
        $formTypeId,
        $formData,
        $roomId = null,
        $followUpAssignedTo = null,
        $uploads = []
    ) {
        if (!array_key_exists($formTypeId, self::FORM_TEMPLATES)) {
            return ApiResponse::format(
                status: '2',
                message: 'Invalid Form Type'
            );
        }

        if ($formTypeId == 3 && !array_key_exists('file', $uploads)) {
            return ApiResponse::format(
                status: '2',
                message: 'Signature Not Found'
            );
        }

        try {
            $uniqueFileName = uniqid() . time() . '.pdf';

            $form = $this->formResponses->create([
                'created_by' => $userId,
                'form_type_id' => $formTypeId,
                'room_id' => $roomId,
                'form_response' => json_decode($formData, true),
                'file_name' => $uniqueFileName,
                'follow_up_assigned_to' => ($formTypeId == 1) ? ($followUpAssignedTo ?? 0) : 0
            ]);

            $imageOnlyAttachments = [];
            $mediaLinks = [];
            $filesToDelete = [];

            foreach ($files as $key => $file) {
                $thumbnailFileName = null;

                if (substr($key, 0, -1) != 'thumbnail') {

                    $fileExtension = explode("/", $file['type']);
                    $mediaFileName = uniqid() . time() . '.' . end($fileExtension);

                    Storage::put(
                        'public/FormResponses/media/' . $mediaFileName,
                        file_get_contents($file['tmp_name'])
                    );

                    $mediaLinks[] = Storage::url('public/FormResponses/media/' . $mediaFileName);

                    if ($fileExtension[0] == 'image') {
                        $imageOnlyAttachments[] = $mediaFileName;
                        $filesToDelete[] = 'public/FormResponses/media/' . $mediaFileName;
                    }

                    if (array_key_exists("thumbnail" . substr($key, -1), $files) && $fileExtension[0] == 'video') {
                        $thumbnail = $files["thumbnail" . substr($key, -1)];

                        $thumbnailExtension = explode("/", $thumbnail['type']);
                        $thumbnailFileName = uniqid() . time() . '.' . end($thumbnailExtension);

                        Storage::put(
                            'public/FormResponses/media/' . $thumbnailFileName,
                            file_get_contents($thumbnail['tmp_name'])
                        );
                    }

                    $this->formMediaAttachments->create([
                        'name' => $mediaFileName,
                        'form_response_id' => $form->id,
                        'type' => $fileExtension[0],
                        'file_extension' => end($fileExtension),
                        'size_in_kb' => ceil($file['size'] / 1024),
                        'thumbnail' => $thumbnailFileName,
                    ]);
                }
            }

            $data = json_decode($formData, true);
            $data['formType'] = $this->formTypes->findById($formTypeId)?->name;
            $data['images'] = $imageOnlyAttachments;

            if ($formTypeId == 1) {
                $data['followUp_done_by'] = $followUpAssignedTo ? $this->users->findById($followUpAssignedTo)?->name : null;
            }
            else if ($formTypeId == 3) {
                if (!count($imageOnlyAttachments)) {
                    return ApiResponse::format(
                        status: '2',
                        message: 'Signature Not Found after saving the attachments'
                    );
                }

                $data['signature'] = $imageOnlyAttachments[0];
            }

            $this->saveFormResponseToStorage(
                $formTypeId,
                $data,
                $uniqueFileName
            );


            $data = json_decode($formData, true);
            if ($formTypeId == 1) {
                $isFollowUpIncomplete = count(array_filter(
                    array_intersect_key($data, array_flip([
                        "followUp_issue",
                        "followUp_findings",
                        "followUp_action_plan",
                        "followUp_possible_solutions",
                        "followUp_examine_result"
                    ]))
                )) > 0;

                $form->is_follow_up_incomplete = (int) $isFollowUpIncomplete;
            } else {
                $form->is_follow_up_incomplete = 0;
            }

            return ApiResponse::format(
                status: '1',
                message: 'Form Saved Successfully',
                data: [
                    'submitted_form_id' => $form->id,
                    'form_link' => Storage::url('public/FormResponses/' . $uniqueFileName),
                    'media_links' => $mediaLinks,
                    'isFollowUpIncomplete' => $form->is_follow_up_incomplete
                ]
            );
        } catch (\Exception $e) {
            return ApiResponse::format(
                status: '0',
                message: $e->getMessage()
            );
        }
    }

    /**
     * Edit an existing form response and regenerate its PDF.
     *
     * Behaviour branches entirely on form_type_id:
     *
     *   Type 1 — Incident Report:
     *     Updates form_response JSON, recalculates is_follow_up_incomplete flag,
     *     updates follow_up_assigned_to, then calls regenerateFormResponse() to
     *     rebuild the PDF (which re-fetches image attachments automatically).
     *
     *   Type 2 — Inspection Checklist:
     *     Same as type 1 but no follow-up fields; just saves and regenerates PDF.
     *
     *   Type 3 — Resident Move-In Summary:
     *     Requires a new signature image in $uploads. Deletes all previous attachments
     *     for this form, saves the new signature image, and regenerates the PDF with it.
     *     Unlike types 1 and 2, this does NOT call regenerateFormResponse() — it builds
     *     the PDF inline using the fresh signature URL.
     *
     * IMPORTANT — file handling limitation (same as saveForm):
     *   File uploads for types 1 and 2 are not expected here (attachments are managed
     *   separately via addAttachmentsToExistingForm). For type 3, $uploads must contain
     *   the signature image, but this uses $_FILES indirectly — not testable via HTTP tests.
     */
    public function editGeneratedFormResponse(
        $formId,
        $formData,
        $followUpAssignedTo = null,
        $uploads = []
    ) {
        try {
            $form = $this->formResponses->findById($formId);

            if (!$form) {
                return ApiResponse::format(
                    status: '0',
                    message: 'Form with This Id is not exist'
                );
            }

            if ($form->file_name) {
                Storage::delete('public/FormResponses/' . $form->file_name);
            }

            $formData = json_decode($formData, true);
            $form->form_response = $formData;

            // Handle Form Type 1 - Incident Report Form
            if ($form->form_type_id == 1) {
                // is_follow_up_incomplete is 1 if the submitter filled in any follow-up fields.
                // The client uses this to badge the form as "needs attention".
                $followUpKeys = [
                    "followUp_issue",
                    "followUp_findings",
                    "followUp_action_plan",
                    "followUp_possible_solutions",
                    "followUp_examine_result"
                ];

                $hasAnyFollowUp = count(array_filter(
                    array_intersect_key($formData, array_flip($followUpKeys))
                )) > 0;

                $form->is_follow_up_incomplete = $hasAnyFollowUp ? 1 : 0;
                $form->follow_up_assigned_to = $followUpAssignedTo ?? 0;
                $this->formResponses->save($form);

                $newLink = $this->regenerateFormResponse($formId);

                return ApiResponse::format(
                    status: '1',
                    message: 'Successfully Submitted',
                    data: [
                        'newLink' => $newLink,
                        'isFollowUpIncomplete' => $form->is_follow_up_incomplete
                    ]
                );
            }

            // Handle Form Type 2 - Inspection Checklist Form
            else if ($form->form_type_id == 2) {
                $form->is_follow_up_incomplete = 0;
                $form->follow_up_assigned_to = 0;
                $this->formResponses->save($form);

                $newLink = $this->regenerateFormResponse($formId);

                return ApiResponse::format(
                    status: '1',
                    message: 'Successfully Submitted',
                    data: [
                        'newLink' => $newLink,
                        'isFollowUpIncomplete' => $form->is_follow_up_incomplete
                    ]
                );
            }

            // Handle Form Type 3 - Resident Move-In Summary
            else if ($form->form_type_id == 3) {
                if (!array_key_exists('file', $uploads)) {
                    return ApiResponse::format(
                        status: '0',
                        message: 'Signature is not sent'
                    );
                }

                $uniqueFileName = uniqid() . time() . '.pdf';

                $form->is_follow_up_incomplete = 0;
                $form->file_name = $uniqueFileName;
                $this->formResponses->save($form);

                $mediaLinks = [];
                
                foreach ($uploads as $key => $file) {

                    $fileExtension = explode("/", $file['type']);
                    
                    if ($fileExtension[0] == 'image') {
                        $mediaFileName = uniqid() . time() . '.' . end($fileExtension);

                        Storage::put(
                            'public/FormResponses/media/' . $mediaFileName,
                            file_get_contents($file['tmp_name'])
                        );

                        $mediaLinks[] = Storage::url('public/FormResponses/media/' . $mediaFileName);

                        // Delete attachments
                        $attachmentIds = $this->formMediaAttachments
                            ->getAll(filters: ['form_response_id' => $formId])
                            ->pluck('id')
                            ->toArray();
                        $this->formMediaAttachments->bulkDeleteByIds($attachmentIds);

                        // Regenerate attachments
                        $attachment = $this->formMediaAttachments->create([
                            'name' => $mediaFileName,
                            'form_response_id' => $formId,
                            'type' => $fileExtension[0],
                            'file_extension' => end($fileExtension),
                            'size_in_kb' => ceil($file['size'] / 1024),
                            'thumbnail' => null,
                        ]);
                    }
                }

                $pdfData = $form->form_response;
                $pdfData['signature'] = $mediaLinks[0];

                $this->saveFormResponseToStorage(
                    $form->form_type_id,
                    $pdfData,
                    $uniqueFileName
                );

                return ApiResponse::format(
                    status: '1',
                    message: 'Successfully Submitted',
                    data: [
                        'newLink' => Storage::url('public/FormResponses/' . $uniqueFileName),
                        'isFollowUpIncomplete' => $form->is_follow_up_incomplete
                    ]
                );
            }

            else {
                return ApiResponse::format(
                    status: '0',
                    message: 'Unsupported Form Type'
                );
            }
        } catch (\Exception $e) {
            return ApiResponse::format(
                status: '0',
                message: $e->getMessage()
            );
        }
    }

    /**
     * Append new media files to an existing form response and regenerate the PDF.
     *
     * File key convention in $uploads (from $_FILES):
     *   Regular files are keyed as "file0", "file1", etc.
     *   Thumbnails (for video files) are keyed as "thumbnail0", "thumbnail1", etc.
     *   Any key starting with "thumbnail" is skipped in the main loop — it's picked up
     *   when processing the corresponding video file.
     *
     * After saving all files, regenerateFormResponse() is called to rebuild the PDF
     * so it includes any newly added images.
     */
    public function addAttachmentsToExistingForm($formId, $uploads)
    {
        try {
            foreach ($uploads as $key => $file) {

                $thumbnailFileName = null;

                // Skip thumbnail keys — they are processed alongside their video file
                if (substr($key, 0, -1) != 'thumbnail') {

                    $fileExtension = explode("/", $file['type']);
                    $mediaFileName = uniqid() . time() . '.' . end($fileExtension);

                    Storage::put(
                        'public/FormResponses/media/' . $mediaFileName,
                        file_get_contents($file['tmp_name'])
                    );

                    if (array_key_exists('thumbnail' . substr($key, 0, -1), $uploads) && $fileExtension[0] == 'video') {
                        $thumbnailFile = $uploads['thumbnail' . substr($key, 0, -1)];
                        $thumbnailFileExtension = explode("/", $thumbnailFile['type']);
                        $thumbnailFileName = uniqid() . time() . '_thumb.' . end($thumbnailFileExtension);

                        Storage::put(
                            'public/FormResponses/media/' . $thumbnailFileName,
                            file_get_contents($thumbnailFile['tmp_name'])
                        );
                    }

                    $attachment = $this->formMediaAttachments->create([
                        'name' => $mediaFileName,
                        'form_response_id' => $formId,
                        'type' => $fileExtension[0],
                        'file_extension' => end($fileExtension),
                        'size_in_kb' => ceil($file['size'] / 1024),
                        'thumbnail' => $thumbnailFileName,
                    ]);
                }
            }

            $attachments = $this->formMediaAttachments->getAll(
                filters: [
                    'form_response_id' => $formId,
                ]
            )->toArray();

            $newLink = $this->regenerateFormResponse($formId);

            return ApiResponse::format(
                status: '1',
                message: 'Attachments Added Successfully',
                data: [
                    'newLink' => $newLink,
                    'attachments' => $attachments,
                ]
            );
        } catch (\Exception $e) {
            return ApiResponse::format(
                status: '0',
                message: $e->getMessage()
            );
        }
    }

    public function deleteFormAttachment($attachmentId, $formId)
    {
        try {
            $attachment = $this->formMediaAttachments->findById($attachmentId);

            if (!$attachment || $attachment->form_response_id != $formId) {
                return ApiResponse::format(
                    status: '0',
                    message: 'Attachment Not Found'
                );
            }

            $this->formMediaAttachments->delete($attachment);

            // TODO: verify if this line is needed
            // Storage::delete('public/FormResponses/media/' . $attachment->name);
            
            $attachments = $this->formMediaAttachments
                ->getAll(filters: ['form_response_id' => $formId])
                ->pluck('id')
                ->toArray();

            $newLink = $this->regenerateFormResponse($formId);

            return ApiResponse::format(
                status: '1',
                message: 'Attachment Deleted Successfully',
                data: [
                    'newLink' => $newLink,
                    'attachments' => $attachments
                ]
            );
        } catch (\Exception $e) {
            return ApiResponse::format(
                status: '0',
                message: $e->getMessage()
            );
        }
    }

    /**
     * Render the PDF from a Blade view and write it to storage.
     *
     * $formTypeId must be a key in FORM_TEMPLATES, or this will throw an
     * undefined-index error. Callers should validate the type before calling.
     *
     * $pdfData is passed directly as variables to the Blade view. The required
     * keys depend on the specific template (formType, images, and the form's
     * own JSON fields are all merged in by callers before passing here).
     *
     * Returns the file name (not a URL). Callers that need a public URL must
     * wrap it: Storage::url('public/FormResponses/' . $fileName).
     */
    private function saveFormResponseToStorage($formTypeId, $pdfData, $fileName = null): string
    {
        if ($fileName === null) {
            $fileName = uniqid() . time() . '.pdf';
        }

        $pdf = PDF::loadView(self::FORM_TEMPLATES[$formTypeId], $pdfData)
            ->download()
            ->getOriginalContent();

        Storage::put('public/FormResponses/' . $fileName, $pdf);

        return $fileName;
    }

    /**
     * Look up the Blade view name for a form type.
     * Returns null if the type ID has no registered template.
     * Callers use null to return a "Unknown Form Type" error rather than crashing.
     */
    private function resolvePdfView(int $formTypeId): ?string
    {
        return self::FORM_TEMPLATES[$formTypeId] ?? null;
    }

    /**
     * Rebuild the PDF for an existing form response and return the new file URL.
     *
     * This is called whenever a form is edited or an attachment is added/removed.
     * It always generates a brand-new file (new unique name) rather than overwriting,
     * and deletes the old file first.
     *
     * Flow:
     *   1. Load the form response and record the old file name.
     *   2. Generate a new unique file name; delete the old PDF from storage.
     *   3. Persist the new file name to DB before generating the file (so the record
     *      is consistent even if PDF generation later fails).
     *   4. Fetch only image-type attachments — videos are not embedded in the PDF.
     *      Image URLs are resolved to their public storage path and passed to the view.
     *   5. Merge form_response JSON data with metadata (formType name, image URLs).
     *   6. For type 1 (Incident Report): also inject the follow-up assignee's name,
     *      looked up from the users table via follow_up_assigned_to.
     *   7. Render the Blade PDF view and write to storage/public/FormResponses/.
     *   8. Return the public URL of the newly generated PDF.
     *
     * Returns a URL string on success, or an ApiResponse JsonResponse object on error
     * (inconsistent return type — callers must handle both).
     */
    public function regenerateFormResponse($formId)
    {
        try {
            $existingFormResponse = $this->formResponses->findById($formId);
            $existingFileName = $existingFormResponse->file_name;

            $uniqueFileName = uniqid() . time() . '.pdf';

            if ($existingFileName) {
                Storage::delete('public/FormResponses/' . $existingFileName);
            }

            // Persist the new filename before generating the PDF so the DB record
            // stays consistent even if saveFormResponseToStorage() throws
            $existingFormResponse->file_name = $uniqueFileName;
            $this->formResponses->save($existingFormResponse);

            // Only image attachments are embedded in the PDF; video thumbnails are excluded
            $results = $this->formMediaAttachments->getAll(
                filters: [
                    'form_response_id' => $formId,
                    'type' => 'image'
                ]
            );

            $images = array_map(function ($item) {
                return Storage::url('public/FormResponses/media/' . $item['name']);
            }, $results->toArray());

            $existingFormTypeId = $existingFormResponse->form_type_id;
            $data = [
                'formType' => $this->formTypes->findById($existingFormTypeId)->name,
                'images' => $images
            ];
            $newFormData = $existingFormResponse->form_response;

            // Merge: form_response fields win over the metadata defaults above
            $data = array_merge($data, $newFormData);

            $pdfView = $this->resolvePdfView($existingFormTypeId);
            if ($pdfView === null) {
                return ApiResponse::format(
                    status: '0',
                    message: "Unknown Form Type Id in Edit Form Response:-{$existingFormTypeId}"
                );
            }

            // Incident Report additionally needs the follow-up assignee's display name
            if ($existingFormTypeId == 1) {
                $assignee_id = $existingFormResponse->follow_up_assigned_to;
                $data['followUp_done_by'] = $this->users->findById($assignee_id)?->name;
            }

            $this->saveFormResponseToStorage($existingFormTypeId, $data, $uniqueFileName);
            return Storage::url('public/FormResponses/' . $uniqueFileName);

        } catch (\Exception $e) {
            return ApiResponse::format(
                status: '0',
                message: "Error in Edit PDF Method:- " . $e->getMessage()
            );
        }
    }
}