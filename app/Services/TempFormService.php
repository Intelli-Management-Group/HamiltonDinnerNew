<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

use App\Models\TempFormType;
use App\Models\TempFormResponse;
use App\Models\TempFormMediaAttachments;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;

use Barryvdh\DomPDF\Facade\Pdf;
use Tymon\JWTAuth\Facades\JWTAuth;

class TempFormService
{
    /**
     * Method to return result json
     * @param Boolean $status = true if success,false if error
     * @param String $msg = Success/Error message
     * @param Array $data = Array containing data to return
     * @return \Illuminate\Http\JsonResponse
     */
    private function sendResultJSON($status, $msg = null, $data = array())
    {
        $return_data = array(
            'ResponseCode' => $status,
            'ResponseText' => $msg
        );
        foreach ($data as $key => $value) {
            $return_data[$key] = $value;
        }
        return response()->json($return_data, 200);
    }

    public function getTempFormDetails(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                "form_id" => "required"
            ], [
                "form_id.required" => "Please enter Form Id",
            ]);

            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }

            $generatedFormId = $request->input('form_id');

            $submittedForm = TempFormResponse::find($generatedFormId);

            $attachments = [];
            $data = null;

            if ($submittedForm) {
                $data = $submittedForm->form_response;

                $attachments = TempFormMediaAttachments::where('form_response_id', $generatedFormId)->orderBy('id', 'DESC')->get();


                return $this->sendResultJSON("1", "Fetched Form Data Successfully", ['form_data' => $data, 'attachments' => $attachments]);
            }

            return $this->sendResultJSON("1", "No Form Details Found", ['form_data' => $data, 'attachments' => $attachments]);
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function saveTempFormByUser(Request $request)
    {

        try {
            $formName = $request->input('name');
            $fields = $request->input('fields');
            $formId = $request->input('id');
            $isPublished = $request->input('is_published');
            $allowPrint = $request->input('allow_print');
            $allowMail = $request->input('allow_mail');


            //  $formType = TempFormType::create);

            $formType = TempFormType::updateOrCreate([
                'id' => $formId,
            ], [
                'name' => $formName,
                'form_fields' => $fields,
                'is_published' => $isPublished,
                'allow_print' => $allowPrint,
                'allow_mail' => $allowMail
            ]);


            return $this->sendResultJSON("1", "Successfully Submitted", array("submitted_form_type_id" => $formType->id));
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function getTempFormTypesList()
    {

        try {
            $results = TempFormType::select('id', 'name', 'allow_print', 'allow_mail')->where('is_published', 1)->get();

            return $this->sendResultJSON("1", "Successfully Fetched", array("list" => $results));
        } catch (\Exception $e) {
            return $this->sendResultJson("0", $e->getMessage());
        }
    }

    public function getDynamicFormDemoDataById($id)
    {
        try {

            $result = TempFormType::find($id);

            return $this->sendResultJSON("1", '', ['body' => json_decode(json_decode($result->form_fields, true), true)]);
            // return $this->sendResultJSON("1", '',['body' =>$result->form_fields ]);

        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function getTempFormResponseList(Request $request)
    {
        try {

            $formTypeId = $request->get('form_type_id');

            if ($formTypeId) {

                $results = TempFormResponse::select('form_type_id', 'form_response', 'id', 'file_name', 'created_at', 'updated_at')->where('form_type_id', $formTypeId)->get();
            } else {
                $results = TempFormResponse::select('form_type_id', 'form_response', 'id', 'file_name', 'created_at', 'updated_at')->get();
            }


            $list = [];

            foreach ($results as $result) {

                $list[] = [
                    'form_response' =>  json_decode($result->form_response, true),
                    'form_type_id' =>  $result->form_type_id,
                    'id' =>  $result->id,
                    'formLink' => Storage::url('public/TempFormResponses/' . $result->file_name),
                    'created_at' => $result->created_at,
                    'updated_at' => $result->updated_at,
                    'form_type_name' => TempFormType::find($result->form_type_id)->name
                ];
            }


            return $this->sendResultJSON("1", "Successfully Fetched", array("list" => $list));
        } catch (\Exception $e) {
            return $this->sendResultJson("0", $e->getMessage());
        }
    }

    public function saveTempForm(Request $request)
    {

        // Array
        // (
        // [thumbnail_1] => Array
        //     (
        //         [name] => istockphoto-1080057124-612x612.jpg
        //         [type] => image/jpeg
        //         [tmp_name] => /tmp/php6TeaGg
        //         [error] => 0
        //         [size] => 16308
        //     )

        // [thumbnail_2] => Array
        //     (
        //         [name] => 659f5176af4cc1704939894.jpeg
        //         [type] => image/jpeg
        //         [tmp_name] => /tmp/phpdLyCzr
        //         [error] => 0
        //         [size] => 61522
        //     )

        // )

        // echo "stop here";die;
        $userId = null;
        $files = $_FILES;

        // print_r($files);die;

        try {

            //authorisation is disabled


            // if ($request->header('Authorization')) {

            //     $token = $request->header('Authorization');
            //     $token = explode(" ", $token);
            //     if (is_array($token) && count($token) == 2 && in_array("Bearer", $token)) {
            //         $token = base64_decode(base64_decode($token[1]));
            //         if ($token != "") {
            //             $token_parts = json_decode($token, true);
            //             if (is_array($token_parts) && count($token_parts) == 3) {

            //                 $userId = $token_parts["user_id"];
            //             } else {
            //                 return response()->json(['ResponseCode' => "11", 'ResponseText' => "Unauthorised"], 200);
            //             }
            //         }
            //     }
            // } else {

            //     return response()->json(['ResponseCode' => "11", 'ResponseText' => "Unauthorised"], 200);
            // }



            $validator = Validator::make($request->all(), [
                "form_type" => "required",
                "data" => "required"

            ], [
                "form_type.required" => "Please enter Form Type",
                "data.required" => "Please enter Form Data"

            ]);

            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }

            $form_type = $request->input('form_type');
            $form_data = $request->input('data');



            $uniqueFileName = uniqid() . time() . '.pdf';
            //   echo "exit";die;
            $form = TempFormResponse::create([
                'form_type_id' => $form_type,
                'form_response' => $form_data,
                'created_by' => $userId,
                'file_name' => $uniqueFileName
            ]);

            $imageOnlyAttachments = [];
            $mediaLinks = [];

            $mediaFormData = json_decode($form_data, true);
            $imageLabelMap = [];

            $videoThumbnailMap = [];

            foreach ($mediaFormData as $formData) {

                $imageLabelMap[$formData['fieldLabel']] = array_merge($formData['mediaName'], $formData['thumbMediaName']);

                foreach ($formData['VideoAndThumbName'] as $item) {

                    $array = explode(",", $item);

                    $videoThumbnailMap[$array[0]] = $array[1];
                }
            }

            // print_r($files);
            // print_r($imageLabelMap);die;

            foreach ($files as $key => $file) {

                $thumbnailFileName = null;

                if (substr($key, 0, -1) != 'thumbnail') { // remove the trailing 1,2 .....

                    $fileExtension = explode("/", $file['type']);
                    $mediaFileName = uniqid() . time() . '.' . end($fileExtension);
                    Storage::put('public/TempFormResponses/media/' . $mediaFileName, file_get_contents($file['tmp_name']));
                    $mediaLinks[] = Storage::url('public/TempFormResponses/media/' . $mediaFileName);

                    $fieldNameToBeSaved = null;

                    if ($fileExtension[0] == 'image') {
                        foreach ($imageLabelMap as $fieldName => $imageNameArray) {
                            if (in_array($file['name'], $imageNameArray)) {

                                $fieldNameToBeSaved = $fieldName;
                                $imageOnlyAttachments[$fieldName][] = Storage::url('public/TempFormResponses/media/' . $mediaFileName);
                            }
                        }
                    }

                    if (array_key_exists("thumbnail" . substr($key, -1), $files) && $fileExtension[0] == 'video') {

                        // $originalThumbnailFile = $files["thumbnail".substr($key, -1)];

                        foreach ($files as $internalKey => $internalFile) {

                            if ($videoThumbnailMap[$file['name']] == $internalFile['name']) {

                                $originalThumbnailFile = $files[$internalKey];
                                break;
                            }

                        }


                        $thumbnailExtension = explode("/", $originalThumbnailFile['type']);
                        $thumbnailFileName = uniqid() . time() . '.' . end($thumbnailExtension);
                        Storage::put('public/TempFormResponses/media/thumbnail/' . $thumbnailFileName, file_get_contents($originalThumbnailFile['tmp_name']));

                        foreach ($imageLabelMap as $fieldName => $imageNameArray) {
                            if (in_array($file['name'], $imageNameArray)) {

                                $fieldNameToBeSaved = $fieldName;
                            }
                        }
                    }

                    $attachmentCreated = TempFormMediaAttachments::create([
                        'name' => $mediaFileName,
                        'form_response_id' => $form->id,
                        'type' => $fileExtension[0],
                        'file_extension' => end($fileExtension),
                        'size_in_kb' => ceil($file['size'] / 1024),
                        'thumbnail' => $thumbnailFileName,
                        'form_field_name' => $fieldNameToBeSaved
                    ]);
                }
            }
            // print_r($imageOnlyAttachments);die;

            $data = [];

            $convertedFormData = [];

            // $formData = (json_decode( preg_replace('/[\x00-\x1F\x80-\xFF]/', '', json_decode($form_data,true)),true));
            $formData =  json_decode($form_data, true);

            foreach ($formData as $item) {
                $convertedFormData[$item['fieldLabel']] = $item['fieldVal'];
            }

            $data['formType'] = TempFormType::find($form_type)->name;
            $data['data'] =  $convertedFormData;
            $data['images'] = $imageOnlyAttachments;

            // print_r($data);
            //     foreach ($data['data'] as $key => $value){
            //         // echo  $key . $value ;

            //         if( isset($data['images'][$key])){
            //             // <p>Attachments:-</p>

            //             foreach ($data['images'][$key] as $images){
            //                 // gettype($images);
            //                 echo $key;
            //                 echo $images;
            //                     // foreach ($images as $image){
            //                             // echo ($image);
            //                             echo "<br>";
            //                     // }
            //                 //
            //             }
            //         }

            //     }

            // die;


            $pdf = PDF::loadView('form-template', $data);
            $content = $pdf->download()->getOriginalContent();

            Storage::put('public/TempFormResponses/' . $uniqueFileName, $content);

            $formData = json_decode($form_data, true);

            // if (array_key_exists("followUp_issue" , $formData)
            // || array_key_exists("followUp_findings" , $formData)
            // || array_key_exists("followUp_action_plan" , $formData)
            // || array_key_exists("followUp_possible_solutions" , $formData)
            // || array_key_exists("followUp_examine_result" , $formData)
            // ){
            //     if ($formData["followUp_issue"] ||
            //     $formData["followUp_findings"] ||
            //     $formData["followUp_action_plan"] ||
            //     $formData["followUp_possible_solutions"] ||
            //     $formData["followUp_examine_result"])
            //     {
            //         $form->is_follow_up_incomplete = 0;
            //     }

            //     else{
            //         $form->is_follow_up_incomplete = 1;
            //     }
            // }

            // else{
            //     $form->is_follow_up_incomplete = 1;
            // }

            // $form->save();
            // return $this->sendResultJSON("1", "Successfully Submitted" );
            return $this->sendResultJSON("1", "Successfully Submitted", array("submitted_form_id" => $form->id, 'form_link' => Storage::url('public/TempFormResponses/' . $uniqueFileName), 'media_links' => $mediaLinks));
        } catch (\Exception $e) {
            // echo "error occured";
            // die;
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function editGeneratedTempFormResponse(Request $request)
    {

        try {

            $validator = Validator::make($request->all(), [
                "form_id" => "required",
                "data" => "required"
            ], [
                "form_id.required" => "Please enter Form Id",
                "data.required" => "Please enter Form Data",
            ]);
            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }

            $form_id = $request->input('form_id');
            $form_data = $request->input('data');

            // $uniqueFileName = uniqid() . time() . '.pdf';

            $existingFormResponse = TempFormResponse::find($form_id);

            if (!$existingFormResponse) {
                return $this->sendResultJSON("0", "Form with This Id is not exist");
            }

            if ($existingFormResponse->file_name) {

                Storage::delete('public/TempFormResponses/' . $existingFormResponse->file_name);
            }

            $existingFormResponse->form_response = $form_data;

            $existingFormResponse->save();



            $newLink = $this->regenerateTempFormResponse($form_id);
            // return $this->sendResultJSON("1",$form_id, array());
            // die;

            return $this->sendResultJSON("1", "Successfully Submitted", array('new_form_link' => $newLink));
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function regenerateTempFormResponse($formId)
    {

        $uniqueFileName = uniqid() . time() . '.pdf';

        $existingFormResponse = TempFormResponse::find($formId);

        if ($existingFormResponse->file_name) {

            Storage::delete('public/TempFormResponses/' . $existingFormResponse->file_name);
        }

        $existingFormResponse->file_name = $uniqueFileName;

        $existingFormResponse->save();

        $results = TempFormMediaAttachments::where([
            'form_response_id' => $formId,
            'type' => 'image'
        ])->get();

        $images = [];

        foreach ($results as $attachment) {
            $images[$attachment['form_field_name']][] = Storage::url('public/TempFormResponses/media/' . $attachment['name']);
        }

        $data = [];

        $data['formType'] = TempFormType::find($existingFormResponse->form_type_id)->name;

        $formData =  json_decode($existingFormResponse->form_response, true);

        foreach ($formData as $item) {
            $convertedFormData[$item['fieldLabel']] = $item['fieldVal'];
        }

        $data['data'] = $convertedFormData;

        $data['images'] = $images;

        $pdf = PDF::loadView('form-template', $data);
        $content = $pdf->download()->getOriginalContent();

        Storage::put('public/TempFormResponses/' . $uniqueFileName, $content);

        return Storage::url('public/TempFormResponses/' . $uniqueFileName);
    }

    public function tempSendMail(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "to_id" => "required",
                "form_id" => "required"
            ], [
                "to_id.required" => "Please enter TO Email Id",
                "form_id.required" => "Please enter Form Id",
            ]);

            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }

            $toEmail = $request->input('to_id');
            $generatedFormId = $request->input('form_id');

            $data = [];

            $submittedForm = TempFormResponse::find($generatedFormId);
            $fileName = $submittedForm->file_name;
            $userId = $submittedForm->created_by;
            $formTypeId = $submittedForm->form_type_id;

            // $userName = User::find($userId)->name;
            // $formType = TempFormType::find($formTypeId)->name;

            $data["email"] = $toEmail;
            // $data["title"] = $formType . " Submitted By ". $userName;
            $data['title'] = "Dynamic Form Title";
            $data["body"] = "The User Response can be seen in the Attachment";

            Mail::send('emails.form-response', $data, function ($message) use ($data, $fileName) {

                $message->to($data["email"], $data["email"])
                    ->subject('DYNAMIC FORM TEMP MAIL')
                    ->attach(public_path() . '/uploads/public/TempFormResponses/' . $fileName);
            });

            return $this->sendResultJSON("1", "Mailed Successfully");
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function iosFormLogin(Request $request)
    {
        try {


            $validator = Validator::make($request->all(), [
                "email" => "required",
                "password" => "required"
            ], [
                "email.required" => "Please enter email",
                "password.required" => "Please enter password",
            ]);
            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }

            $email = $request->input("email");
            $password = $request->input("password");

            JWTAuth::factory()->setTTL(null);
            JWTAuth::factory()->setRefreshTTL(null);

            $token =  auth()->attempt([
                'email' => $email,
                'password' => $password
            ]);


            if (!$token) {

                // return response()->json(['error' => 'User Not Found'], 200);
                return $this->sendResultJSON("2", "User not Found");
            }

            $user = auth()->user();

            $roleName = null;

            if (!empty($user->role_id)) {

                $roleName = Role::select('name')->where('id', $user->role_id)->get()->toArray();
            }

            if (!empty($roleName)) {
                if ($roleName[0]) {

                    if ($roleName[0]['name']) {
                        $user->roleName = $roleName[0]['name'];
                    }
                }
            }

            $allPermissionsResult = Permission::select('name')->pluck('name')->toArray();

            $allPermissions = [];

            foreach ($allPermissionsResult as $item) {

                $allPermissions[$item] = 0;
            }

            $newUser = User::with('permissionList')->where('id', $user->id)->get()->toArray();

            $data = [];

            foreach ($newUser as $result) {

                $data['user_id'] = $result['id'];
                $data['user_name'] = $result['name'];
                $data['authentication_token'] = $token;
                $data['role'] = $user->roleName;

                foreach ($result['permission_list'] as $permission) {

                    $allPermissions[$permission['name']] = 1;
                }

                $data['permissions'] = $allPermissions;
            }

            JWTAuth::factory()->setTTL(config('jwt.ttl'));
            JWTAuth::factory()->setRefreshTTL(config('jwt.refresh_ttl'));

            return $this->sendResultJSON("1", "success", $data);
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function getTempUserData()
    {

        try {

            $user = auth()->user();

            if (!$user) {

                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $newUser = User::with('permissionList', 'roleModel')->where('id', $user->id)->get()->toArray();

            if (empty($newUser)) {
                // return $this->sendResultJSON("11", "Unauthorised");

                return response()->json(['ResponseCode' => "11", 'ResponseText' => "Unauthorised", "error" => "User Not Found"], 200);
            }

            $roleName = null;

            if (!empty($user->role_id)) {

                $roleName = User::select('name')->where('id', $user->role_id)->get()->toArray();
            }

            if (!empty($roleName)) {

                if ($roleName[0]['name']) {
                    $user->roleName = $roleName[0]['name'];
                }
            }

            $allPermissionsResult = Permission::select('name')->pluck('name')->toArray();

            $allPermissions = [];

            foreach ($allPermissionsResult as $item) {

                $allPermissions[$item] = 0;
            }

            $newUser = User::with('permissionList', 'roleModel')->where('id', $user->id)->get()->toArray();

            $data = [];

            foreach ($newUser as $result) {

                $data['user_id'] = $result['id'];
                $data['user_name'] = $result['name'];
                $data['role'] = !empty($result['role_model']) ? $result['role_model']['name'] : null;

                foreach ($result['permission_list'] as $permission) {

                    $allPermissions[$permission['name']] = 1;
                }

                $data['permissions'] = $allPermissions;
            }


            return $this->sendResultJSON("1", "success", $data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function deleteTempFormResponse($id)
    {
        try {
            if ($id) {
                TempFormResponse::where("id", $id)->delete();
                return $this->sendResultJSON("1", "Temp Form Response Deleted Successfully");
            }
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function deleteTempFormType($id)
    {
        try {
            if ($id) {
                TempFormType::where("id", $id)->delete();
                return $this->sendResultJSON("1", "Temp Form Type Deleted Successfully");
            }
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function tempFormTypeList()
    {
        try {

            $results  = TempFormType::all();

            return $this->sendResultJSON("1", " Form Types Fetched Successfully", ['list' => $results]);
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function tempFormTypeById($id)
    {
        try {

            $obj  = TempFormType::where('id', $id)->get();

            return $this->sendResultJSON("1", " Form Types Fetched Successfully", ['response' => $obj]);
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function deleteTempFormAttachment(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "form_id" => "required",
                "attachment_id" => "required"
            ], [
                "form_id.required" => "Please enter Form Id",
                "attachment_id.required" => "Please enter Attachment Id"
            ]);
            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }

            $attachmentId = $request->get('attachment_id');
            $formId = $request->get('form_id');

            TempFormMediaAttachments::where(['id' => $attachmentId, 'form_response_id' => $formId])->delete();

            $attachments = TempFormMediaAttachments::where('form_response_id', $formId)->orderBy('id', 'DESC')->get();

            $newLink = $this->regenerateTempFormResponse($formId);

            return $this->sendResultJSON("1", "Attachment Deleted Successfully", array("newLink" => $newLink, "attachments" => $attachments));
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function addAttachmentsToExistingTempForm(Request $request)
    {

        try {
            $files = $_FILES;
            $validator = Validator::make($request->all(), [
                "form_id" => "required",
                "file.*" => "required"
            ], [
                "form_id.required" => "Please enter Form Id",
                "file.*.required" => "Please Upload File(s)",
            ]);
            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }

            $imageOnlyAttachments = [];
            $mediaLinks = [];

            $formId = $request->input('form_id');
            $formFieldName = $request->input('form_field_name');

            foreach ($files as $key => $file) {

                $thumbnailFileName = null;


                if (substr($key, 0, -1) != 'thumbnail') { // remove the trailing 1,2 .....

                    $fileExtension = explode("/", $file['type']);
                    $mediaFileName = uniqid() . time() . '.' . end($fileExtension);
                    Storage::put('public/TempFormResponses/media/' . $mediaFileName, file_get_contents($file['tmp_name']));
                    $mediaLinks[] = Storage::url('public/TempFormResponses/media/' . $mediaFileName);

                    if ($fileExtension[0] == 'image') {
                        $imageOnlyAttachments[] = Storage::url('public/TempFormResponses/media/' . $mediaFileName);
                    }

                    if (array_key_exists("thumbnail" . substr($key, -1), $files) && $fileExtension[0] == 'video') {

                        $originalThumbnailFile = $files["thumbnail" . substr($key, -1)];

                        $thumbnailExtension = explode("/", $originalThumbnailFile['type']);
                        $thumbnailFileName = uniqid() . time() . '.' . end($thumbnailExtension);
                        Storage::put('public/TempFormResponses/media/thumbnail/' . $thumbnailFileName, file_get_contents($originalThumbnailFile['tmp_name']));
                    }

                    $attachmentCreated = TempFormMediaAttachments::create([
                        'name' => $mediaFileName,
                        'form_response_id' => $formId,
                        'type' => $fileExtension[0],
                        'file_extension' => end($fileExtension),
                        'size_in_kb' => ceil($file['size'] / 1024),
                        'thumbnail' => $thumbnailFileName,
                        'form_field_name' => $formFieldName

                    ]);
                }
            }

            $results = TempFormMediaAttachments::where([
                'form_response_id' => $formId,
            ])->orderBy('id', 'DESC')->get();

            $attachments = [];

            foreach ($results as $attachment) {
                $attachments[] = $attachment;
            }

            $newLink = $this->regenerateTempFormResponse($formId);

            return $this->sendResultJSON("1", "Attachments Uploaded Successfully", array("new_form_link" => $newLink, "attachments" => $attachments));
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }
}
