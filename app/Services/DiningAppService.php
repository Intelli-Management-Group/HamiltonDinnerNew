<?php

namespace App\Services;

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

use App\Repositories\Contracts\Forms\FormTypeRepositoryInterface;
use App\Repositories\Contracts\MenuDetailRepositoryInterface;
use App\Repositories\Contracts\OrderDetailRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\RoomDetailRepositoryInterface;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;

class DiningAppService
{
    public function __construct(
        private FormTypeRepositoryInterface $formTypes,
        private MenuDetailRepositoryInterface $menuDetails,
        private OrderDetailRepositoryInterface $orderDetails,
        private RoleRepositoryInterface $roles,
        private RoomDetailRepositoryInterface $roomDetails,
        private SettingRepositoryInterface $settings,
        private UserRepositoryInterface $users
    ) {}

    public function login(
        string $room_no,
        string $password
    ) {
        $last_date = $this->menuDetails->getLatestMenuDate() ?? "";
        if ($last_date) {
            $last_date = Carbon::parse($last_date)->format('Y-m-d');
        }

        $rooms = $this->roomDetails->getAll(
            filters: ['is_active' => 1],
            relations: [],
            columns: ['id', 'room_name', 'occupancy', 'resident_name']
        );

        array_walk($rooms->toArray(), function (&$room) {
            $room['name'] = $room['room_name'];
            unset($room['room_name']);
        });

        $settings = $this->settings->getAllKeyValues();

        $user = $this->roomDetails->getAll(
            filters: [
                'room_name' => $room_no,
                'password' => $password
            ]
        )->first();

        if ($user) {
            if ($user->is_active == 1) {
                $user_token =  $this->generateAccessToken($user->id, "user");
            
                return ApiResponse::format(
                    status: '1',
                    message: 'Successfully Login',
                    data: array(
                        'room_id' => $user->id,
                        'rooms' => $rooms_array,
                        'breakfast_guideline' => $settingsArray['site.app_breakfast_msg'],
                        'breakfast_guideline_cn' => $settingsArray['site.app_breakfast_msg_cn'] != "" ? $settingsArray['site.app_breakfast_msg_cn'] : $settingsArray['site.app_breakfast_msg'],
                        'lunch_guideline' => $settingsArray['site.app_lunch_msg'],
                        'lunch_guideline_cn' => $settingsArray['site.app_lunch_msg_cn'] != "" ? $settingsArray['site.app_lunch_msg_cn'] : $settingsArray['site.app_lunch_msg'],
                        'dinner_guideline' => $settingsArray['site.app_dinner_msg'],
                        'dinner_guideline_cn' => $settingsArray['site.app_dinner_msg_cn'] != "" ? $settingsArray['site.app_dinner_msg_cn'] : $settingsArray['site.app_dinner_msg'],
                        'room_number' => $user->room_name,
                        'occupancy' => $user->occupancy,
                        'resident_name' => $user->resident_name,
                        'language' => intval($user->language),
                        'last_menu_date' => $last_date,
                        'authentication_token' => $user_token,
                        'role' => "user"
                    )
                );
            } else {
                return ApiResponse::format(
                    status: '3',
                    message: 'User not active'
                );
            }
            
        } else {
            $user = $this->users->getAll(
                filters: [
                    'user_name' => $room_no,
                ],
                relations: [
                    'role'
                ]
            )->first();

            if (!$user) {
                if ($this->roomDetails->getAll(filters: [
                    'room_name' => $room_no
                ])->first()) {
                    return ApiResponse::format(
                        status: '2',
                        message: 'Room Number or Password is incorrect'
                    );
                } else {
                    return ApiResponse::format(
                        status: '2',
                        message: 'User Not Found'
                    );
                }
            }

            if (!Hash::check($password, $user->password)) {
                return ApiResponse::format(
                    status: '2',
                    message: 'User Not Found'
                );
             }

             if ($user->is_active == 0) {
                return ApiResponse::format(
                    status: '3',
                    message: 'User not active'
                );
            }

            $role = intval($user->role_id) == 1 ? 'admin' : 'kitchen';
            $roleName = $this->roles->getById($user->role_id)?->name;

            $user_token =  $this->generateAccessToken($user->id, $role);

            $formTypes = $this->formTypes->getAll();

            $userResults = $this->users->getAll(
                filters: [
                    'deleted_at' => 'without',
                    'role_id' => [3,4,5,6,7]
                ],
                relations: [
                    'role'
                ],
            );

            $userList = $userResults->map(fn ($user) => [
                'id'        => $user->id,
                'name'      => $user->name,
                'email'     => $user->email,
                'role_name' => $user->role?->name,
            ])->values()->all();

            return ApiResponse::format(
                status: '1',
                message: 'Successfully Login',
                data: array(
                    'room_id' => 0,
                    'rooms' => $rooms_array,
                    'breakfast_guideline' => $settings['site.app_breakfast_msg'],
                    'breakfast_guideline_cn' => $settings['site.app_breakfast_msg_cn'] != "" ? $settings['site.app_breakfast_msg_cn'] : $settings['site.app_breakfast_msg'],
                    'lunch_guideline' => $settings['site.app_lunch_msg'],
                    'lunch_guideline_cn' => $settings['site.app_lunch_msg_cn'] != "" ? $settings['site.app_lunch_msg_cn'] : $settings['site.app_lunch_msg'],
                    'dinner_guideline' => $settings['site.app_dinner_msg'],
                    'dinner_guideline_cn' => $settings['site.app_dinner_msg_cn'] != "" ? $settings['site.app_dinner_msg_cn'] : $settings['site.app_dinner_msg'],
                    'room_number' => "",
                    'occupancy' => 0,
                    'resident_name' => "",
                    'language' => 0,
                    'last_menu_date' => $last_date,
                    'authentication_token' => $user_token,
                    'role' => $roleName,
                    'form_types' => $formTypes,
                    'user_list' => $userList,
                    'user_id' => $user->id,
                    'show_incident' => $settings['show_incident'],
                    'show_dining' => $settings['show_dining']
                )
            );
        }
    }

    private function generateAccessToken($user_id, $role)
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
        // Retrieve Rooms
        $rooms = $this->roomDetails->getAll(
            filters: ['is_active' => 1],
            relations: [],
            columns: ['id', 'room_name', 'occupancy']);

        // Rename column
        array_walk($rooms->toArray(), function (&$room) {
            $room['name'] = $room['room_name'];
            unset($room['room_name']);
        });

        // Retrieve latest menu date
        $last_date = $this->menuDetails->getLatestMenuDate() ?? "";

        // Define data to return
        $data = [
            'rooms' => $rooms,
            'last_menu_date' => $last_date,
        ];

        return ApiResponse::format(
            status: '1',
            message: '',
            data: $data
        );
    }

    public function getRoomDetails(int $roomId)
    {
        try {
            $room = $this->roomDetails->findById($roomId);

            if (!$room) {
                return ApiResponse::format(
                    status: '0',
                    message: 'Room Not Found'
                );
            }

            return ApiResponse::format(
                status: '1',
                message: 'Room Details Found',
                data: ['Data' => $room]
            );

        } catch (\Exception $e) {
            return ApiResponse::format(
                status: '0',
                message: "Error in fetching room details: " . $e->getMessage()
            );
        }
    }

    public function updateRoomDetails(int $roomId, array $data)
    {
        try {
            $room = $this->roomDetails->findById($roomId);

            if (!$room) {
                return ApiResponse::format(
                    status: '0',
                    message: 'Room Not Found'
                );
            }

            $updatedRoom = $this->roomDetails->update($roomId, $data);

            return ApiResponse::format(
                status: '1',
                message: 'Room Details Updated Successfully',
                data: ['Data' => $updatedRoom]
            );

        } catch (\Exception $e) {
            return ApiResponse::format(
                status: '0',
                message: "Error in updating room details: " . $e->getMessage()
            );
        }
    }

    public function updateOrder(int $roomId, string $date, array $orderData)
    {
        try {
            $room = $this->roomDetails->findById($roomId);

            if (!$room) {
                return ApiResponse::format(
                    status: '2',
                    message: 'Room Not Found'
                );
            }

            $is_for_guest = $orderData['is_for_guest'];
            $is_brk_tray_service = $orderData['is_brk_tray_service'];
            $is_lunch_tray_service = $orderData['is_lunch_tray_service'];
            $is_dinner_tray_service = $orderData['is_dinner_tray_service'];
            $is_brk_escort_service = $orderData['is_brk_escort_service'];
            $is_lunch_escort_service = $orderData['is_lunch_escort_service'];
            $is_dinner_escort_service = $orderData['is_dinner_escort_service'];
            $orders_to_change = $orderData['orders_to_change'];
            $occupancy = $orderData['occupancy'];

            $toUpdate = $this->orderDetails->getAll(
                filters: [
                    'room_id' => $roomId,
                    'date' => $date,
                    'is_for_guest' => $is_for_guest
            ])->update([
                'is_brk_tray_service' => $is_brk_tray_service,
                'is_lunch_tray_service' => $is_lunch_tray_service,
                'is_dinner_tray_service' => $is_dinner_tray_service,
                'is_brk_escort_service' => $is_brk_escort_service,
                'is_lunch_escort_service' => $is_lunch_escort_service,
                'is_dinner_escort_service' => $is_dinner_escort_service,
            ]);

            
            // TODO: finish

            return ApiResponse::format(
                status: '1',
                message: 'Order Updated Successfully'
            );

        } catch (\Exception $e) {
            return ApiResponse::format(
                status: '0',
                message: "Error in updating order: " . $e->getMessage()
            );
        }
    }
}