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

            $list = $this->formResponses->getAllWithFormType(
                filters: $filters,
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

            $data = $form->form_response;

            if(!empty($form->follow_up_assigned_to)) {

                $followUpUser = $this->users->findById(
                    $form->follow_up_assigned_to,
                    relations: ['role']
                );

                $data = [
                    'id' => $followUpUser->id,
                    'name' => $followUpUser->name,
                    'email' => $followUpUser->email,
                    'role_name' => $followUpUser->role?->name
                ];

                $attachments = $this->formMediaAttachments
                    ->getAll(filters: ['form_response_id' => $formId])
                    ->toArray();

                return ApiResponse::format(
                    status: '1',
                    message: 'Fetched Form Data Successfully',
                    data: [
                        'form_data' => $form,
                        'attachments' => $attachments,
                        'follow_up_user' => $data
                    ]
                );
            }
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

            $this->formResponses->delete($form);

            $attachments = $this->formMediaAttachments
                ->getAll(filters: ['form_response_id' => $formId])
                ->pluck('id')
                ->toArray();

            $this->formMediaAttachments->bulkDeleteByIds($attachments);

            // foreach ($attachments as $attachment) {
                // this line wasn't in the original code, 
                // but it's necessary to delete the file from storage
                // Storage::delete('public/FormResponses/media/' . $attachment->name);
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

            if (is_array($jsonData)) {
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
            } else {
                $jsonResponse = json_decode($jsonData, true);

                if ($jsonResponse['is_completed'] == 1) {
                    return ApiResponse::format(
                        status: '0',
                        message: 'Form is already completed !'
                    );
                }
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

    public function addAttachmentsToExistingForm($formId, $uploads)
    {
        try {
            foreach ($uploads as $key => $file) {

                $thumbnailFileName = null;

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

    // Helper function to save PDF to storage
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

    // Helper function to regenerate PDF for a form response
    private function resolvePdfView(int $formTypeId): ?string
    {
        return self::FORM_TEMPLATES[$formTypeId] ?? null;
    }

    public function regenerateFormResponse($formId)
    {
        try {
            $existingFormResponse = $this->formResponses->findById($formId);
            $existingFileName = $existingFormResponse->file_name;

            if ($existingFileName) {
                Storage::delete('public/FormResponses/' . $existingFileName);
            }

            $existingFormResponse->file_name = $uniqueFileName;
            $this->formResponses->save($existingFormResponse);

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
            
            $data = array_merge($data, $newFormData);

            $pdfView = $this->resolvePdfView($existingFormTypeId);
            if ($pdfView === null) {
                return ApiResponse::format(
                    status: '0',
                    message: "Unknown Form Type Id in Edit Form Response:-{$existingFormTypeId}"
                );
            }

            if ($existingFormTypeId == 1) {
                $assignee_id = $existingFormResponse->follow_up_assigned_to;
                $data['followUp_done_by'] = $this->users->findById($assignee_id)?->name;
            }

            $uniqueFileName = $this->saveFormResponseToStorage($existingFormTypeId, $data);
            return Storage::url('public/FormResponses/' . $uniqueFileName);

        } catch (\Exception $e) {
            return ApiResponse::format(
                status: '0',
                message: "Error in Edit PDF Method:- " . $e->getMessage()
            );
        }
    }
}