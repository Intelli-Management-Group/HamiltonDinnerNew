<?php


namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

use Illuminate\Support\Facades\Hash;

// Models
use App\Models\CategoryDetail;
use App\Models\ItemDetail;
use App\Models\OrderDetail;
use App\Models\MenuDetail;
use App\Models\RoomDetail;
use App\Models\FormMediaAttachments;
use App\Models\SpecialInstructionDetail;
use App\Models\TableDetail;
use App\Models\User;
use App\Models\ItemOption;
use App\Models\ItemPreference;
use App\Models\FormType;
use App\Models\FormResponse;
use App\Models\Role;
use App\Models\DateWiseOccupancy;
use App\Models\TempFormType;
use App\Models\TempFormResponse;
use App\Models\TempFormMediaAttachments;
use App\Models\BackendPermission;
use App\Models\BackendUser;
use App\Models\ItemOption as ItemOptionModel;
use App\Models\MoveInSummaryValues;
use App\Models\Permission;
use App\Models\UserActivity;

// Services
use App\Services\MenuDetailService;
use App\Services\RoomDetailService;
use App\Services\DiningAppService;
use App\Services\Forms\FormsAppService;
use App\Services\ItemDetailService;
use App\Services\Reports\ChargeReportService;
use App\Services\TempFormService;

// Support
use App\Support\ApiResponse;

use Tymon\JWTAuth\Facades\JWTAuth;

// Traits
use App\Http\Controllers\Api\Traits\LegacyDinningMethods;


class DinningController extends Controller
{
    use LegacyDinningMethods;

    public function __construct(
        private RoomDetailService $roomDetailService,
        private DiningAppService $diningAppService,
        private FormsAppService $formsAppService,
        private ItemDetailService $itemDetailService,
        private ChargeReportService $chargeReportService,
        private TempFormService $tempFormService
    )
    {
        ini_set('max_execution_time', 0);
    }

    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "room_no" => "required",
                "password" => "required"
            ], [
                "room_no.required" => "Please enter room no",
                "password.required" => "Please enter password",
            ]);
            if ($validator->fails()) {
                return ApiResponse::format(
                    status: '2',
                    message: $validator->errors()->first()
                );
            }
            $room_no      = $request->input("room_no");
            $password     = $request->input("password");
            $device_token = $request->input("device_token");

            return $this->diningAppService->login($room_no, $password, $device_token);

        } catch (\Exception $e) {
            return ApiResponse::format(
                status: '0',
                message: $e->getMessage()
            );
        }
    }

    function generate_access_token($user_id, $role)
    {
        $token = json_encode(array(
            'user_id' => $user_id,
            'timestamp' => Carbon::Now()->timestamp,
            'role' => $role
        ));
        return 'Bearer ' . base64_encode(base64_encode($token));
    }

    public function getRoomList()
    {
        return $this->diningAppService->getRoomList();
    }

    // TODO: move into dining app service
    public function getOrderList(Request $request)
    {
        if (!session("user_details")) {
            return $this->sendResultJSON("11", "Unauthorised");
        }

        $room_id = intval($request->input('room_id'));

        $date = $request->input('date');

        return $this->diningAppService->getOrderList($room_id, $date);
    }

    public function updateOrder(Request $request)
    {
        if (!session("user_details")) {
            return ApiResponse::format(
                status: '11',
                message: 'Unauthorised'
            );
        }

        $room_id = $request->input('room_id');
        $date = $request->input('date');

        if (empty($room_id) || empty($date)) {
            return ApiResponse::format(
                status: '2',
                message: 'Room id or date is missing'
            );
        }

        $orderData = [
            'is_for_guest' => (int) $request->input('is_for_guest', 0),
            'is_brk_tray_service' => (int) $request->input('is_brk_tray_service', 0),
            'is_lunch_tray_service' => (int) $request->input('is_lunch_tray_service', 0),
            'is_dinner_tray_service' => (int) $request->input('is_dinner_tray_service', 0),
            'is_brk_escort_service' => (int) $request->input('is_brk_escort_service', 0),
            'is_lunch_escort_service' => (int) $request->input('is_lunch_escort_service', 0),
            'is_dinner_escort_service' => (int) $request->input('is_dinner_escort_service', 0),
            'orders_to_change' => $request->input('orders_to_change'),
            'occupancy' => $request->input('occupancy')
        ];

        return $this->diningAppService->updateOrder($room_id, $date, $orderData);
    }

    public function updateOrderBulk(Request $request)
    {
        // if (!session("user_details")) {
        //     return $this->sendResultJSON("11", "Unauthorised");
        // }

        $room_id = $request->input('room_id');
        $date = $request->input('current_date');
        $ordersToChange = $request->input('orders_to_change');

        return $this->diningAppService->updateOrderBulk($room_id, $date, $ordersToChange);
    }

    public function updatePrintStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'room_id'   => 'required|integer',
            'is_guest'  => 'required|integer|in:0,1',
            'date'      => 'required|date_format:Y-m-d',
            'meal_type' => 'required|string|in:breakfast,lunch,dinner',
        ]);

        if ($validator->fails()) {
            return ApiResponse::format(status: '0', message: $validator->errors()->first());
        }

        return $this->diningAppService->updatePrintStatus(
            roomId:   (int) $request->input('room_id'),
            isGuest:  (int) $request->input('is_guest'),
            date:     $request->input('date'),
            mealType: $request->input('meal_type'),
        );
    }

    public function setDeviceToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::format(status: '0', message: $validator->errors()->first());
        }

        return $this->diningAppService->setDeviceToken($request->input('token'));
    }

    public function sendPush(Request $request)
    {
        return $this->diningAppService->sendPush();
    }

    public function getUserData()
    {
        if (!session("user_details")) {
            return ApiResponse::format(
                status: '11',
                message: 'Unauthorised'
            );
        }
        $user = session("user_details");

        return $this->diningAppService->getUserData($user);
    }

    public function sendEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "to_id" => "required",
            "form_id" => "required"
        ], [
            "to_id.required" => "Please enter TO Email Id",
            "form_id.required" => "Please enter Form Id",
        ]);

        if ($validator->fails()) {
            return ApiResponse::format(
                "2",
                $validator->errors()->first()
            );
        }

        $toEmail = $request->input('to_id');
        $formId = $request->input('form_id');

        return $this->formsAppService->sendEmail($toEmail, $formId);
    }

    public function getFormDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "form_id" => "required"
        ], [
            "form_id.required" => "Please enter Form Id",
        ]);

        if ($validator->fails()) {
            return ApiResponse::format(
                "2",
                $validator->errors()->first()
            );
        }

        $generatedFormId = $request->input('form_id');

        return $this->formsAppService->getFormDetails($generatedFormId);
    }

    public function getTempFormDetails(Request $request)
    {
        return $this->tempFormService->getTempFormDetails($request);
    }

    public function getGeneratedForms(Request $request)
    {

        $validator = Validator::make($request->all(), [
            "form_type" => "required"
        ], [
            "form_type.required" => "Please enter Form type"
        ]);

        if ($validator->fails()) {
            return ApiResponse::format(
                "2",
                $validator->errors()->first()
            );
        }

        return $this->formsAppService->getGeneratedForms($request->form_type);
    }

    public function deleteFormResponse(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "form_id" => "required"
        ], [
            "form_id.required" => "Please enter Form Id"
        ]);

        if ($validator->fails()) {
            return ApiResponse::format(
                "2",
                $validator->errors()->first()
            );
        }

        return $this->formsAppService->deleteFormResponse($request->form_id);
    }

    public function completeFormLog(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "form_id" => "required",
            "completed_by" => "required"
        ], [
            "form_id.required" => "Please enter Form Id",
            "completed_by.required" => "Please Provide Completed By Id",
        ]);

        if ($validator->fails()) {
            return ApiResponse::format(
                "2",
                $validator->errors()->first()
            );
        }

        return $this->formsAppService->completeFormLog(
            $request->form_id,
            $request->completed_by
        );
    }

    public function getGuestOrderList(Request $request)
    {
        if (!session("user_details")) {
            return $this->sendResultJSON("11", "Unauthorised");
        }

        $room_id = intval($request->input('room_id'));
        $date = $request->input('date');

        return $this->diningAppService->getGuestOrderList($room_id, $date);
    }

    public function getDynamicFormDemoDataById($id)
    {
        return $this->tempFormService->getDynamicFormDemoDataById($id);
    }

    public function saveTempFormByUser(Request $request)
    {
        return $this->tempFormService->saveTempFormByUser($request);
    }

    public function getTempFormTypesList()
    {
        return $this->tempFormService->getTempFormTypesList();
    }

    public function getTempFormResponseList(Request $request)
    {
        return $this->tempFormService->getTempFormResponseList($request);
    }

    public function saveTempForm(Request $request)
    {
        return $this->tempFormService->saveTempForm($request);
    }

    public function editGeneratedTempFormResponse(Request $request)
    {
        return $this->tempFormService->editGeneratedTempFormResponse($request);
    }

    public function regenerateTempFormResponse($formId)
    {
        return $this->tempFormService->regenerateTempFormResponse($formId);
    }

    public function tempSendMail(Request $request)
    {
        return $this->tempFormService->tempSendMail($request);
    }

    public function iosFormLogin(Request $request)
    {
        return $this->tempFormService->iosFormLogin($request);
    }

    public function getTempUserData()
    {
        return $this->tempFormService->getTempUserData();
    }

    public function deleteTempFormResponse($id)
    {
        return $this->tempFormService->deleteTempFormResponse($id);
    }

    public function deleteTempFormType($id)
    {
        return $this->tempFormService->deleteTempFormType($id);
    }

    public function tempFormTypeList()
    {
        return $this->tempFormService->tempFormTypeList();
    }

    public function tempFormTypeById($id)
    {
        return $this->tempFormService->tempFormTypeById($id);
    }

    public function deleteTempFormAttachment(Request $request)
    {
        return $this->tempFormService->deleteTempFormAttachment($request);
    }

    public function addAttachmentsToExistingTempForm(Request $request)
    {
        return $this->tempFormService->addAttachmentsToExistingTempForm($request);
    }

    public function reportData(Request $request)
    {
        try {
            $date     = $request->get('date') ?: null;
            $roomName = ($v = (int)$request->get('room_name')) ? $v : null;

            $data = $this->chargeReportService->getOrderReport($date, $roomName);

            return $this->sendResultJSON('1', 'success', $data);
        } catch (\Exception $e) {
            return response()->json(['ResponseCode' => '11', 'ResponseText' => $e->getMessage()], 200);
        }
    }

    public function getCategoryWiseDataDemo(Request $request)
    {
        $date = $request->input('date');
        $data = $this->diningAppService->getCategoryWiseData($date);

        return $this->sendResultJSON('1', $data['message'], array_diff_key($data, ['message' => '']));
    }

    // V2 of charge report functions
    public function getChargeReportV2(Request $request)
    {
        $start_date = $end_date = null;
        if ($request->has('start_date') && $request->has('end_date')) {
            $start_date = $request->input('start_date');
            $end_date = $request->input('end_date');
        } elseif ($request->has('date')) {
            $start_date = $end_date = $request->input('date');
        } else {
            return $this->sendResultJSON('0', 'Invalid parameters!!', []);
        }

        $chargeReport = $this->chargeReportService->getChargeReport(
            $start_date,
            $end_date
        );

        return $this->sendResultJSON('1', '', $chargeReport);
    }

    public function saveFormPhase1(Request $request)
    {
        $userId = null;
        $files = $_FILES;

        if ($request->header('Authorization')) {
            $token = $request->header('Authorization');
            $token = explode(" ", $token);
            if (is_array($token) && count($token) == 2 && in_array("Bearer", $token)) {
                $token = base64_decode(base64_decode($token[1]));
                if ($token != "") {
                    $token_parts = json_decode($token, true);
                    if (is_array($token_parts) && count($token_parts) == 3) {
                        $userId = $token_parts["user_id"];
                    } else {
                        return response()->json([
                            'ResponseCode' => "11",
                            'ResponseText' => "Unauthorised"
                        ], 200);
                    }
                }
            }
        } else {
            return response()->json([
                'ResponseCode' => "11",
                'ResponseText' => "Unauthorised"
            ], 200);
        }

        $validator = Validator::make($request->all(), [
            "form_type" => "required",
            "data" => "required",
            "file.*" => "required"
        ], [
            "form_type.required" => "Please enter Form Type",
            "data.required" => "Please enter Form Data",
            "file.*.required" => "Please Upload File(s)",
        ]);
        if ($validator->fails()) {
            return ApiResponse::format(
                "2",
                $validator->errors()->first()
            );
        }

        $form_type = $request->input('form_type');
        $form_data = $request->input('data');
        $room_id = $request->input('room_id');
        $follow_up_assigned_to = $request->input('follow_up_assigned_to');

        return $this->formsAppService->saveForm(
            $userId,
            $form_type,
            $form_data,
            $room_id,
            $follow_up_assigned_to,
            $files
        );
    }

    public function editGeneratedFormResponsePhase1(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "form_id" => "required",
            "data" => "required"
        ], [
            "form_id.required" => "Please enter Form Id",
            "data.required" => "Please enter Form Data",
        ]);
        if ($validator->fails()) {
            return ApiResponse::format(
                "2",
                $validator->errors()->first()
            );
        }

        $form_id = $request->input('form_id');
        $form_data = $request->input('data');
        $follow_up_assigned_to = $request->input('follow_up_assigned_to');
        $files = $_FILES;

        return $this->formsAppService->editGeneratedFormResponse(
            $form_id,
            $form_data,
            $follow_up_assigned_to,
            $files
        );
    }

    public function addAttachmentsToExistingFormPhase1(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "form_id" => "required",
            "file.*" => "required"
        ], [
            "form_id.required" => "Please enter Form Id",
            "file.*.required" => "Please Upload File(s)",
        ]);

        if ($validator->fails()) {
            return ApiResponse::format(
                "2",
                $validator->errors()->first()
            );
        }

        $formId = $request->get('form_id');
        $files = $_FILES;
        return $this->formsAppService
            ->addAttachmentsToExistingForm($formId, $files);
    }

    public function deleteFormAttachmentPhase1(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "form_id" => "required",
            "attachment_id" => "required"
        ], [
            "form_id.required" => "Please enter Form Id",
            "attachment_id.required" => "Please enter Attachment Id"
        ]);

        if ($validator->fails()) {
            return ApiResponse::format(
                "2",
                $validator->errors()->first()
            );
        }

        $attachmentId = $request->get('attachment_id');
        $formId = $request->get('form_id');

        return $this->formsAppService
            ->deleteFormAttachment($attachmentId, $formId);
    }

    public function printOrderDataTemp(Request $request)
    {

        $date = $request->input('date');
        $room_name = intval($request->input('room_name'));
        $meal_type = $request->input('meal_type');

        return $this->diningAppService->printOrderData(
            $room_name,
            $date,
            $meal_type
        );
    }

    public function getMoveInSummaryValues()
    {
        return $this->formsAppService->getMoveInSummaryValues();
    }

    public function getRoomDetails($room_id)
    {
        return $this->diningAppService->getRoomDetails($room_id);
    }

    public function updateRoomDetails(Request $request, $room_id)
    {
        return $this->diningAppService->updateRoomDetails($room_id, $request->all());
    }

    public function getCategorySpecificItems(Request $request)
    {
        try {
            $categoryId = (int) $request->input('categoryId');
            $data = $this->itemDetailService->getItemsByCategory($categoryId);

            return $this->sendResultJSON('1', 'Items Found', $data);
        } catch (\Exception $e) {
            return $this->sendResultJSON('0', 'Error in fetching items: ' . $e->getMessage());
        }
    }

    public function logActivity() {}
}
