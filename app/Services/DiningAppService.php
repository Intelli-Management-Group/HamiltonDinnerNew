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

use App\Repositories\Contracts\CategoryDetailRepositoryInterface;
use App\Repositories\Contracts\DateWiseOccupancyRepositoryInterface;
use App\Repositories\Contracts\Forms\FormTypeRepositoryInterface;
use App\Repositories\Contracts\ItemDetailRepositoryInterface;
use App\Repositories\Contracts\ItemOptionRepositoryInterface;
use App\Repositories\Contracts\ItemPreferenceRepositoryInterface;
use App\Repositories\Contracts\MenuDetailRepositoryInterface;
use App\Repositories\Contracts\OrderDetailRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\RoomDetailRepositoryInterface;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;

class DiningAppService
{
    public function __construct(
        private CategoryDetailRepositoryInterface $categoryDetails,
        private DateWiseOccupancyRepositoryInterface $dateWiseOccupancies,
        private FormTypeRepositoryInterface $formTypes,
        private ItemDetailRepositoryInterface $itemDetails,
        private ItemOptionRepositoryInterface $itemOptions,
        private ItemPreferenceRepositoryInterface $itemPreferences,
        private MenuDetailRepositoryInterface $menuDetails,
        private OrderDetailRepositoryInterface $orderDetails,
        private RoleRepositoryInterface $roles,
        private RoomDetailRepositoryInterface $roomDetails,
        private SettingRepositoryInterface $settings,
        private UserRepositoryInterface $users
    ) {}

    /**
     * Shared login endpoint for residents, admins, and kitchen staff.
     *
     * There are two completely separate authentication paths:
     *
     * PATH 1 — Resident (room_no = room number, password = plain-text stored in room_details):
     *   - Matches room_name + password directly in room_details table.
     *   - On success: returns resident payload with role="user".
     *   - ResponseCode "3" if the room exists but is inactive.
     *
     * PATH 2 — Admin / Kitchen (room_no = username, password = bcrypt in users table):
     *   - Reached only when PATH 1 finds no match.
     *   - Uses Hash::check() against the bcrypt-stored password.
     *   - role_id == 1 → "admin" token; anything else → "kitchen" token.
     *   - Returns extra fields used by the forms app: form_types, user_list.
     *
     * Both paths embed meal guidelines in the response. If a Chinese translation is empty,
     * the English value is used as a fallback.
     *
     * Settings keys that must exist or this will throw an undefined-index error:
     *   site.app_breakfast_msg, site.app_lunch_msg, site.app_dinner_msg,
     *   site.app_breakfast_msg_cn, site.app_lunch_msg_cn, site.app_dinner_msg_cn,
     *   show_incident, show_dining.
     */
    public function login(
        string $room_no,
        string $password
    ) {
        $last_date = $this->menuDetails->findLatestDate() ?? "";
        if ($last_date) {
            $last_date = Carbon::parse($last_date)->format('Y-m-d');
        }

        $rooms = $this->roomDetails->getAll(
            filters: ['is_active' => 1],
            relations: [],
            columns: ['id', 'room_name', 'occupancy', 'resident_name']
        );

        $rooms_array = [];
        foreach ($rooms as $room) {
            $rooms_array[] = [
                'id' => $room->id,
                'name' => $room->room_name,
                'occupancy' => $room->occupancy,
                'resident_name' => $room->resident_name,
            ];
        }

        $settingsArray = $this->settings->getAllKeyValues();

        // Fall back to English if no Chinese translation is set
        $breakfast_guideline_cn = $settingsArray['site.app_breakfast_msg_cn'] != ""
            ? $settingsArray['site.app_breakfast_msg_cn']
            : $settingsArray['site.app_breakfast_msg'];
        $lunch_guideline_cn = $settingsArray['site.app_lunch_msg_cn'] != ""
            ? $settingsArray['site.app_lunch_msg_cn']
            : $settingsArray['site.app_lunch_msg'];
        $dinner_guideline_cn = $settingsArray['site.app_dinner_msg_cn'] != ""
            ? $settingsArray['site.app_dinner_msg_cn']
            : $settingsArray['site.app_dinner_msg'];

        $guidelines = [
            'breakfast_guideline' => $settingsArray['site.app_breakfast_msg'],
            'breakfast_guideline_cn' => $breakfast_guideline_cn,
            'lunch_guideline' => $settingsArray['site.app_lunch_msg'],
            'lunch_guideline_cn' => $lunch_guideline_cn,
            'dinner_guideline' => $settingsArray['site.app_dinner_msg'],
            'dinner_guideline_cn' => $dinner_guideline_cn,
        ];

        // --- PATH 1: Resident login (plain-text password stored in room_details) ---
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
                    data: array_merge($guidelines, [
                        'room_id' => $user->id,
                        'rooms' => $rooms_array,
                        'room_number' => $user->room_name,
                        'occupancy' => $user->occupancy,
                        'resident_name' => $user->resident_name,
                        'language' => intval($user->language),
                        'last_menu_date' => $last_date,
                        'authentication_token' => $user_token,
                        'role' => "user"
                    ])
                );
            }

            return ApiResponse::format(
                status: '3',
                message: 'User not active'
            );
        }

        // --- PATH 2: Admin / Kitchen login (bcrypt password in users table) ---
        $user = $this->users->getAll(
            filters: [
                'user_name' => $room_no,
            ],
            relations: [
                'roleModel'
            ]
        )->first();

        if (!$user) {
            // Give a more specific error if the room name exists but the password was wrong
            if ($this->roomDetails->getAll(filters: [
                'room_name' => $room_no
            ])->first()) {
                return ApiResponse::format(
                    status: '2',
                    message: 'Room Number or Password is incorrect'
                );
            }

            return ApiResponse::format(
                status: '2',
                message: 'User not Found'
            );
        }

        if (!Hash::check($password, $user->password)) {
            return ApiResponse::format(
                status: '2',
                message: 'User not Found'
            );
         }

        // role_id 1 = admin, everything else treated as kitchen
        $role = intval($user->role_id) == 1 ? 'admin' : 'kitchen';
        $roleName = $this->roles->findById($user->role_id)?->name;

        $user_token =  $this->generateAccessToken($user->id, $role);

        $formTypes = $this->formTypes->getAll();

        // user_list populates "assign follow-up" dropdowns in the forms app.
        // Role IDs 3–7 are non-admin staff; adjust if the roles table changes.
        $userResults = $this->users->getAll(
            filters: [
                'deleted_at' => 'without',
                'role_id' => [3,4,5,6,7]
            ],
            relations: [
                'roleModel'
            ],
        );

        $userList = $userResults->map(fn ($user) => [
            'id' => $user->id,
            'role_name' => $user->roleModel?->name,
            'name' => $user->name,
            'email' => $user->email,
        ])->values()->all();

        return ApiResponse::format(
            status: '1',
            message: 'Successfully Login',
            data: array_merge($guidelines, [
                'room_id' => 0,
                'rooms' => $rooms_array,
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
                'show_incident' => $settingsArray['show_incident'],
                'show_dining' => $settingsArray['show_dining']
            ])
        );
    }

    /**
     * Generate the APIToken used by the resident/kitchen/admin app.
     *
     * Token format: "Bearer " + base64(base64(json({user_id, timestamp, role})))
     *
     * The double base64 encoding is intentional (legacy decision) and must be preserved —
     * APIToken middleware decodes it with two rounds of base64_decode.
     *
     * role values: "user" (resident), "admin", "kitchen"
     * For "user" role, user_id is room_details.id.
     * For "admin"/"kitchen", user_id is users.id.
     */
    private function generateAccessToken($user_id, $role)
    {
        $token = json_encode(array(
            'user_id' => $user_id,
            'timestamp' => Carbon::Now()->timestamp,
            'role' => $role
        ));
        return 'Bearer ' . base64_encode(base64_encode($token));
    }

    public function getUserData($user)
    {
        $roleName = $this->roles->findById($user->role_id)?->name;

        $lastDate = $this->menuDetails->findLatestDate() ?? "";
        if ($lastDate) {
            $lastDate = Carbon::parse($lastDate)->format('Y-m-d');
        }

        $rooms = $this->roomDetails->getAll(
            filters: ['is_active' => 1],
            relations: [],
            columns: ['id', 'room_name', 'occupancy', 'resident_name']
        );

        // Rename room_name → name for API compatibility
        $roomsArray = $rooms->toArray();
        array_walk($roomsArray, function (&$room) {
            $room['name'] = $room['room_name'];
            unset($room['room_name']);
        });

        $settingsArray = $this->settings->getAllKeyValues();

        if ($roleName == "user") {
            return ApiResponse::format(
                status: '1',
                message: '',
                data: [
                    'occupancy' => $user->occupancy,
                    'language' => intval($user->language),
                    'last_menu_date' => $lastDate,
                    'role' => $roleName,
                    'breakfast_guideline' => $settingsArray['site.app_breakfast_msg'],
                    'breakfast_guideline_cn' => (
                        $settingsArray['site.app_breakfast_msg_cn'] != "" 
                        ? $settingsArray['site.app_breakfast_msg_cn']
                        : $settingsArray['site.app_breakfast_msg']
                    ),
                    'lunch_guideline' => $settingsArray['site.app_lunch_msg'],
                    'lunch_guideline_cn' => (
                        $settingsArray['site.app_lunch_msg_cn'] != ""
                        ? $settingsArray['site.app_lunch_msg_cn']
                        : $settingsArray['site.app_lunch_msg']
                    ),
                    'dinner_guideline' => $settingsArray['site.app_dinner_msg'],
                    'dinner_guideline_cn' => (
                        $settingsArray['site.app_dinner_msg_cn'] != ""
                        ? $settingsArray['site.app_dinner_msg_cn']
                        : $settingsArray['site.app_dinner_msg']
                    ),
                    'rooms' => $roomsArray,
                ]
            );
        } else {
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
                'id' => $user->id,
                'role_name' => $user->roleModel?->name,
                'name' => $user->name,
                'email' => $user->email,
            ])->values()->all();

            return ApiResponse::format(
                status: '1',
                message: '',
                data: [
                    'occupancy' => 0,
                    'language' => 0,
                    'last_menu_date' => $lastDate,
                    'role' => $roleName,
                    'breakfast_guideline' => $settingsArray['site.app_breakfast_msg'],
                    'breakfast_guideline_cn' => (
                        $settingsArray['site.app_breakfast_msg_cn'] != "" 
                        ? $settingsArray['site.app_breakfast_msg_cn']
                        : $settingsArray['site.app_breakfast_msg']
                    ),
                    'lunch_guideline' => $settingsArray['site.app_lunch_msg'],
                    'lunch_guideline_cn' => (
                        $settingsArray['site.app_lunch_msg_cn'] != ""
                        ? $settingsArray['site.app_lunch_msg_cn']
                        : $settingsArray['site.app_lunch_msg']
                    ),
                    'dinner_guideline' => $settingsArray['site.app_dinner_msg'],
                    'dinner_guideline_cn' => (
                        $settingsArray['site.app_dinner_msg_cn'] != ""
                        ? $settingsArray['site.app_dinner_msg_cn']
                        : $settingsArray['site.app_dinner_msg']
                    ),
                    'form_types' => $formTypes,
                    'rooms' => $roomsArray,
                    'user_list' => $userList,
                    'user_id' => $user->id,
                    'show_incident' => $settingsArray['show_incident'],
                    'show_dining' => $settingsArray['show_dining']
                ]
            );
        }
    }

    public function getRoomList()
    {
        // Retrieve Rooms
        $rooms = $this->roomDetails->getAll(
            filters: ['is_active' => 1],
            relations: [],
            columns: ['id', 'room_name', 'occupancy']);

        // Rename room_name → name for API compatibility
        $roomsArray = $rooms->toArray();
        array_walk($roomsArray, function (&$room) {
            $room['name'] = $room['room_name'];
            unset($room['room_name']);
        });

        // Retrieve latest menu date
        $last_date = $this->menuDetails->findLatestDate() ?? "";

        // Define data to return
        $data = [
            'rooms' => $roomsArray,
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

    /**
     * Build the full menu/order list for a given date.
     *
     * OVERVIEW:
     * The menu for a date is stored in menu_details.items as a JSON array of item IDs.
     * This function expands those IDs into full item objects, annotates each with the
     * current room's order state (or aggregate counts for the admin view), and groups
     * everything into breakfast / lunch / dinner buckets by category type.
     *
     * STEP 1 — Extract item IDs from the menu:
     *   menu_details.items can be a flat array [1,2,3] or a nested array [[1,2],[3]].
     *   Both shapes are handled; duplicates are removed.
     *
     * STEP 2 — Split items into two tiers:
     *   - "Top-level category items" (findByIdsAndParentFlag(..., true)):
     *     items whose category has parent_id = 0.
     *   - "Sub-category items" (findByIdsAndParentFlag(..., false)):
     *     items under a child category (e.g. "Soup" inside "Lunch").
     *
     * STEP 3 — Two rendering paths based on $roomId:
     *   $roomId != 0 → Resident/room view:
     *     Loads the room's existing order for each item. Marks which option/preference
     *     is_selected, and returns qty from the order (0 if not ordered).
     *   $roomId == 0 → Admin/kitchen view:
     *     No per-room order lookup. qty = sum of all orders for that item/date.
     *     Per-option counts (item_count) are returned so kitchen can see breakdowns.
     *
     * STEP 4 — Assemble sub-categories into their parent:
     *   Sub-cat items are grouped under a "sub_cat" placeholder row inside the parent
     *   category's items array. The final structure per category is:
     *     [ {type:"item"}, ..., {type:"sub_cat"}, {type:"sub_cat_item"}, ... ]
     *
     * STEP 5 — Sort categories into meal buckets:
     *   category.type: 1 = breakfast, 2 = lunch, 3 = dinner.
     *
     * OPTIONS: stored as JSON array of option IDs on item_details.options.
     *   Each item can have at most one selected option (radio-style).
     *   Stored as a single integer in order_details.item_options.
     *
     * PREFERENCES: stored as JSON array of preference IDs on item_details.preference.
     *   Multiple preferences can be selected (checkbox-style).
     *   Stored as a comma-separated string in order_details.preference.
     */
    public function getOrderList(int $roomId, string $date)
    {
        $subCatDetails = array();
        $catArray = array();
        $breakfast = $lunch = $dinner = array();

        $items = array();

        $menuData = $this->menuDetails->getAll(
            filters: ['date' => $date],
            columns: ['items']
        );

        // STEP 1: Extract a flat, unique list of item IDs from the menu.
        // menu_details.items is JSON and may be a flat array or an array of arrays.
        foreach ($menuData as $menu) {
            $menuItems = $menu->items;

            if (is_string($menuItems)) {
                $menuItems = json_decode($menuItems, true);
            }

            if (!is_array($menuItems)) {
                continue;
            }

            foreach ($menuItems as $menuItem) {
                if (empty($menuItem)) {
                    continue;
                }

                if (is_array($menuItem)) {
                    foreach ($menuItem as $id) {
                        if ($id !== '' && $id !== null) {
                            $items[] = (int) $id;
                        }
                    }
                } else {
                    $items[] = (int) $menuItem;
                }
            }
        }

        $items = array_values(array_unique($items));

        // Load all options and preferences in bulk (keyed by ID) to avoid N+1 queries
        // when iterating over items below.
        $optionDetails = $preferenceDetails = array();

        if (!empty($items)) {
            $optionDetails = $this->itemOptions->getAll()
                ->keyBy('id')
                ->map(fn ($o) => [
                    'option_name' => $o->option_name,
                    'option_name_cn' => $o->option_name_cn ?? $o->option_name,
                ])
                ->all();

            $preferenceDetails = $this->itemPreferences->getAll()
                ->keyBy('id')
                ->map(fn ($p) => [
                    'name' => $p->pname,
                    'name_cn' => $p->pname_cn ?? $p->pname,
                ])
                ->all();

            // STEP 2a: Top-level category items (category has parent_id = 0)
            $categoryData = $this->itemDetails
                ->findByIdsAndParentFlag($items, true);

            foreach ($categoryData as $c) {
                if (!isset($catArray[$c->cat_id])) {
                    $catArray[$c->cat_id] = [
                        'cat_id' => $c->cat_id,
                        'cat_name' => $c->category?->cat_name ?? "",
                        'chinese_name' => $c->category?->category_chinese_name ?? "",
                        'items' => [],
                        'type' => $c->category?->type ?? ""
                    ];
                }

                $options = $preference = [];

                // STEP 3: Two rendering paths
                if ($roomId != 0) {
                    // --- Resident view: show this room's order state for each item ---
                    $orderData = $this->orderDetails->getAll(
                        filters: [
                            'room_id' => $roomId,
                            'date' => $date,
                            'item_id' => $c->id,
                            'is_for_guest' => 0
                        ]
                    )->first();

                    if ($c->options != "") {
                        $cOptions = json_decode($c->options, true);
                        foreach ($cOptions as $co) {
                            $co = intval($co);
                            if ($optionDetails[$co]) {
                                $options[$co] = array(
                                    'id' => $co,
                                    'name' => $optionDetails[$co]['option_name'],
                                    'c_name' => $optionDetails[$co]['option_name_cn'],
                                    'is_selected' => (
                                        $orderData?->item_options != null
                                        ? intval($co == $orderData->item_options) 
                                        : 0
                                    )
                                );
                            }
                        }
                    }

                    if ($c->preference != "") {
                        $cPreferences = json_decode($c->preference, true);
                        foreach ($cPreferences as $cp) {
                            $cp = intval($cp);
                            if ($preferenceDetails[$cp]) {
                                $isSelected = (
                                    $orderData?->preference != null
                                    && in_array($cp, explode(',', $orderData->preference))
                                );

                                $preference[$cp] = array(
                                    'id' => $cp,
                                    'name' => $preferenceDetails[$cp]['name'],
                                    'c_name' => $preferenceDetails[$cp]['name_cn'],
                                    'is_selected' => intval($isSelected)
                                );
                            }
                        }
                    }

                    $catArray[$c->cat_id]['items'][] = array(
                        'type' => "item",
                        'parent_id' => $c->category->parent_id ?? 0,
                        'item_id' => $c->id,
                        'item_name' => $c->item_name,
                        'chinese_name' => $c->item_chinese_name,
                        'options' => array_values($options),
                        'preference' => array_values($preference),
                        'item_image' => !empty($c->item_image)
                            ? Storage::url($c->item_image) : null,
                        'qty' => $orderData?->is_for_guest === 0
                            ? $orderData->quantity : 0,
                        'comment' => "",
                        'order_id' => $orderData?->id ?? 0
                    );
                } else {
                    // --- Admin/kitchen view: show aggregate order counts across all rooms ---
                    $sum = $this->orderDetails->sumQuantityByDateAndItem($date, $c->id);

                    if ($c->options != "") {
                        $cOptions = json_decode($c->options, true);
                        foreach ($cOptions as $co) {
                            $co = intval($co);

                            $itemCount = $this->orderDetails->getAll(
                                filters: [
                                    'date' => $date,
                                    'item_id' => $c->id,
                                    'item_options' => $co,
                                ]
                            )->count();

                            if ($optionDetails[$co]) {
                                $options[$co] = array(
                                    'id' => $co,
                                    'name' => $optionDetails[$co]['option_name'],
                                    'c_name' => $optionDetails[$co]['option_name_cn'],
                                    'is_selected' => 0,
                                    'item_count' => $itemCount
                                );
                            }
                        }
                    }

                    $catArray[$c->cat_id]['items'][] = array(
                        'type' => "item",
                        'parent_id' => $c->category->parent_id ?? 0,
                        'item_id' => $c->id,
                        'item_name' => $c->item_name,
                        'chinese_name' => $c->item_chinese_name,
                        'is_expanded' => (int) (count(array_values($options)) > 0),
                        'options' => array_values($options),
                        'preference' => array_values($preference),
                        'item_image' => !empty($c->item_image)
                            ? Storage::url($c->item_image) : null,
                        'qty' => $sum,
                        'comment' => "",
                        'order_id' => 0  
                     );
                }
            }

            // STEP 2b: Sub-category items (category has a non-zero parent_id).
            // These are collected separately and then injected into their parent
            // category's items array in STEP 4.
            $subCatData = $this->itemDetails
                ->findByIdsAndParentFlag($items, false);

            foreach ($subCatData as $sc) {
                if (!isset($subCatDetails[$sc->cat_id])) {
                    $subCatDetails[$sc->cat_id] = [
                        "cat_id" => $sc->cat_id,
                        "cat_name" => $sc->category?->cat_name ?? "",
                        "chinese_name" => $sc->category?->category_chinese_name ?? "",
                        "parent_id" => $sc->category?->parent_id ?? 0,
                        "items" => []
                    ];
                }

                $scParentId = $sc->category?->parent_id ?? 0;

                // Create a placeholder entry for the parent category if it wasn't in
                // the top-level item set (e.g. a parent with no direct items)
                if (!isset($catArray[$scParentId])) {
                    $scParent = $this->categoryDetails->findById($scParentId);

                    $catArray[$scParentId] = [
                        'cat_id' => $scParentId,
                        'cat_name' => $scParent?->cat_name ?? "",
                        'chinese_name' => $scParent?->category_chinese_name ?? "",
                        'items' => [],
                        'type' => $scParent?->type ?? ""
                    ];
                }

                $options = $preference = [];

                if ($roomId != 0) {
                    $orderData = $this->orderDetails->getAll(
                        filters: [
                            'room_id' => $roomId,
                            'date' => $date,
                            'item_id' => $sc->id,
                            'is_for_guest' => 0
                        ]
                    )->first();

                    if ($sc->options != "") {
                        $scOptions = json_decode($sc->options, true);
                        foreach ($scOptions as $sco) {
                            $sco = intval($sco);
                            if ($optionDetails[$sco]) {
                                $options[$sco] = array(
                                    'id' => $sco,
                                    'name' => $optionDetails[$sco]['option_name'],
                                    'c_name' => $optionDetails[$sco]['option_name_cn'],
                                    'is_selected' => (
                                        $orderData?->item_options != null
                                        ? intval($sco == $orderData->item_options) 
                                        : 0
                                    )
                                );
                            }
                        }
                    }

                    if ($sc->preference != "") {
                        $scPreferences = json_decode($sc->preference, true);
                        foreach ($scPreferences as $scp) {
                            $scp = intval($scp);
                            if ($preferenceDetails[$scp]) {
                                $isSelected = (
                                    $orderData?->preference != null
                                    && in_array($scp, explode(',', $orderData->preference))
                                );

                                $preference[$scp] = array(
                                    'id' => $scp,
                                    'name' => $preferenceDetails[$scp]['name'],
                                    'c_name' => $preferenceDetails[$scp]['name_cn'],
                                    'is_selected' => intval($isSelected)
                                );
                            }
                        }
                    }

                    $subCatDetails[$sc->cat_id]['items'][] = array(
                        'item_id' => $sc->id,
                        'parent_id' => $scParentId,
                        'item_name' => $sc->item_name,
                        'chinese_name' => $sc->item_chinese_name,
                        'item_image' => !empty($sc->item_image)
                            ? Storage::url($sc->item_image) : null,
                        'options' => array_values($options),
                        'preference' => array_values($preference),
                        'qty' => $orderData?->is_for_guest === 0
                            ? $orderData->quantity : 0,
                        'comment' => "",
                        'order_id' => $orderData?->id ?? 0
                    );
                } else {
                    $sum = $this->orderDetails->sumQuantityByDateAndItem($date, $sc->id);

                    if ($sc->options != "") {
                        $scOptions = json_decode($sc->options, true);
                        foreach ($scOptions as $sco) {
                            $sco = intval($sco);

                            $itemCount = $this->orderDetails->getAll(
                                filters: [
                                    'date' => $date,
                                    'item_id' => $sc->id,
                                    'item_options' => $sco,
                                ]
                            )->count();

                            if ($optionDetails[$sco]) {
                                $options[$sco] = array(
                                    'id' => $sco,
                                    'name' => $optionDetails[$sco]['option_name'],
                                    'c_name' => $optionDetails[$sco]['option_name_cn'],
                                    'is_selected' => 0,
                                    'item_count' => $itemCount
                                );
                            }
                        }
                    }

                    $subCatDetails[$sc->cat_id]['items'][] = array(
                        'item_id' => $sc->id,
                        'parent_id' => $scParentId,
                        'item_name' => $sc->item_name,
                        'chinese_name' => $sc->item_chinese_name,
                        'item_image' => !empty($sc->item_image)
                            ? Storage::url($sc->item_image) : null,
                        'is_expanded' => (int) (count(array_values($options)) > 0),
                        'options' => array_values($options),
                        'preference' => array_values($preference),
                        'qty' => $sum,
                        'comment' => "",
                        'order_id' => 0
                    );
                }
            }

            // STEP 4: Embed sub-category items into their parent category.
            // The client expects: [...top-level items..., {type:"sub_cat"}, {type:"sub_cat_item"}, ...]
            // The sub_cat row is a header/placeholder; sub_cat_item rows are the actual items.
            foreach ($subCatDetails as $scd) {
                if (isset($catArray[$scd['parent_id']])) {
                    $catArray[$scd['parent_id']]['items'][] = array(
                        'type' => "sub_cat",
                        'item_id' => null,
                        'cat_id' => $scd['cat_id'],
                        'parent_id' => $scd['parent_id'],
                        'item_name' => $scd['cat_name'],
                        'chinese_name' => $scd['chinese_name'],
                        'options' => [],
                        'preference' => [],
                        'item_image' => "",
                        'qty' => 0,
                        'comment' => "",
                        'order_id' => 0
                    );

                    foreach ($scd['items'] as $item) {
                        $catArray[$scd['parent_id']]['items'][] = array_merge(
                            $item,
                            ['type' => "sub_cat_item"]
                        );
                    }
                }
            }

        }

        // STEP 5: Sort categories into meal buckets by category type (1=breakfast, 2=lunch, 3=dinner)
        foreach ($catArray as $cat) {
            $type = intval($cat['type'] ?? 0);
            unset($cat['type']);

            if ($type == 1) {
                $breakfast[] = $cat;
            } else if ($type == 2) {
                $lunch[] = $cat;
            } else if ($type == 3) {
                $dinner[] = $cat;
            }
        }

        $lastMenuDate = $this->menuDetails->findLatestDate() ?? "";
        if ($lastMenuDate) {
            $lastMenuDate = Carbon::parse($lastMenuDate)->format('Y-m-d');
        }

    $instructions = $this->roomDetails->findById($roomId)?->special_instrucations ?? "";

        $trayServiceData = $this->orderDetails->getAll(
            filters: [
                'room_id' => $roomId,
                'date' => $date,
                'is_for_guest' => 0,
                'order_by' => 'id',
                'order_direction' => 'desc'
            ]
        )->first();

        return ApiResponse::format(
            status: '1',
            message: '',
            data: [
                'breakfast' => $breakfast,
                'lunch' => $lunch,
                'dinner' => $dinner,
                'last_menu_date' => $lastMenuDate,
                'special_instruction' => $instructions,
                'is_brk_tray_service' => $trayServiceData?->is_brk_tray_service ?? 0,
                'is_lunch_tray_service' => $trayServiceData?->is_lunch_tray_service ?? 0,
                'is_dinner_tray_service' => $trayServiceData?->is_dinner_tray_service ?? 0,
                'is_brk_escort_service' => $trayServiceData?->is_brk_escort_service ?? 0,
                'is_lunch_escort_service' => $trayServiceData?->is_lunch_escort_service ?? 0,
                'is_dinner_escort_service' => $trayServiceData?->is_dinner_escort_service ?? 0,
                'is_brk_takeout_service' => $trayServiceData?->is_brk_takeout_service ?? 0,
                'is_lunch_takeout_service' => $trayServiceData?->is_lunch_takeout_service ?? 0,
                'is_dinner_takeout_service' => $trayServiceData?->is_dinner_takeout_service ?? 0
            ]
        );
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

    /**
     * Save or update a resident's meal order for a single date.
     *
     * There are two layers to every order submission:
     *
     * LAYER 1 — Service flags (tray/escort) are always upserted first.
     *   These flags apply to the whole day for this room, not per-item.
     *   They're stored on the order_details row matched by (room_id, date, is_for_guest).
     *   If it's a guest order, the date_wise_occupancies table is also updated.
     *
     * LAYER 2 — Individual item orders (orders_to_change JSON array).
     *   Each element has: item_id, order_id, qty, item_options, preference.
     *   Logic per item:
     *     order_id == 0 && qty > 0  → CREATE a new order row
     *     order_id != 0 && qty > 0  → UPDATE the existing order row
     *     order_id != 0 && qty == 0 → DELETE the order (treated as "deselected")
     *     order_id == 0 && qty == 0 → No-op (was never ordered, still not ordered)
     *
     * The response returns item_id/order_id arrays only for rows that were created
     * or deleted (so the client can update its local state).
     */
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

            // LAYER 1: Always persist service flags first, regardless of item changes
            $this->orderDetails->upsertByFilters([
                'room_id' => $roomId,
                'date' => $date,
                'is_for_guest' => $is_for_guest
            ], [
                'is_brk_tray_service' => $is_brk_tray_service,
                'is_lunch_tray_service' => $is_lunch_tray_service,
                'is_dinner_tray_service' => $is_dinner_tray_service,
                'is_brk_escort_service' => $is_brk_escort_service,
                'is_lunch_escort_service' => $is_lunch_escort_service,
                'is_dinner_escort_service' => $is_dinner_escort_service,
            ]);

            if ($is_for_guest) {
                $this->dateWiseOccupancies->upsertByFilters([
                    'date' => $date,
                    'room_id' => $roomId,
                ], [
                    'occupancy' => $occupancy,
                ]);
            }

            $item_array = $order_array = array();

            // LAYER 2: Process per-item order changes
            if ($orders_to_change) {
                $newData = json_decode($orders_to_change);

                foreach ($newData as $n) {
                    $n->order_id = intval($n->order_id);
                    $n->qty = intval($n->qty);

                    if ($n->order_id == 0) {
                        // New item — only create a row if qty > 0
                        if ($n->qty != 0) {
                            $order = $this->orderDetails->create([
                                'room_id' => $roomId,
                                'date' => $date,
                                'item_id' => $n->item_id,
                                'item_options' => $n->item_options,
                                'preference' => $n->preference,
                                'quantity' => $n->qty,
                                'comment' => '',
                                'status' => 0,

                                'is_for_guest' => $is_for_guest,
                                'is_brk_tray_service' => $is_brk_tray_service,
                                'is_lunch_tray_service' => $is_lunch_tray_service,
                                'is_dinner_tray_service' => $is_dinner_tray_service,
                                'is_brk_escort_service' => $is_brk_escort_service,
                                'is_lunch_escort_service' => $is_lunch_escort_service,
                                'is_dinner_escort_service' => $is_dinner_escort_service,
                            ]);

                            $item_array[] = $n->item_id;
                            $order_array[] = $order->id;
                        }
                    } else {
                        // Existing order — update if qty > 0, delete if qty == 0
                        $order = $this->orderDetails->findById($n->order_id);
                        if ($order) {
                            if ($n->qty != 0) {
                                $order->quantity = $n->qty;
                                $order->item_options = $n->item_options;
                                $order->preference = $n->preference;
                                $order->comment = '';

                                $this->orderDetails->save($order);
                            } else {
                                $this->orderDetails->delete($order);
                                $item_array[] = $n->item_id;
                                $order_array[] = 0;
                            }
                        }
                    }
                }
            }

            return ApiResponse::format(
                status: '1',
                message: 'success',
                data: [
                    'item_id' => $item_array,
                    'order_id' => $order_array
                ]
            );

        } catch (\Exception $e) {
            return ApiResponse::format(
                status: '0',
                message: "Error in updating order: " . $e->getMessage()
            );
        }
    }

    /**
     * Bulk version of updateOrder — applies order changes across multiple dates in one call.
     *
     * $ordersToChange is a JSON array where each element represents one date:
     * [
     *   {
     *     "date": "2026-01-01",
     *     "is_brk_tray_service": 0, "is_lunch_tray_service": 0, ...  (service flags)
     *     "items": [                                                   (optional)
     *       { "order_id": 0, "item_id": 5, "qty": 1, "item_options": 3, "preference": "" },
     *       ...
     *     ]
     *   },
     *   ...
     * ]
     *
     * The item order logic is identical to updateOrder (see that method's docblock):
     *   order_id == 0 && qty > 0 → create; order_id != 0 && qty > 0 → update;
     *   order_id != 0 && qty == 0 → delete.
     *
     * GOTCHA — variable shadowing: the outer loop variable is named $order, and inside
     * the items loop a new $order is assigned when creating a new order row. This shadows
     * the outer $order for the rest of that iteration. The code works correctly because
     * $internalDate and the service-flag variables are all captured before the items loop,
     * but be careful if you add logic after the create block that refers to the outer $order.
     */
    public function updateOrderBulk(
        int $roomId,
        string $date,
        string $ordersToChange
    ) {
        if (!$roomId || !$date) {
            return ApiResponse::format(
                status: '2',
                message: 'Room id or date is missing'
            );
        }

        $room = $this->roomDetails->findById($roomId);
        if (!$room) {
            return ApiResponse::format(
                status: '2',
                message: 'Room not found'
            );
        }

        $orderData = json_decode($ordersToChange, true);
        $item_array = $order_array = array();
        foreach ($orderData as $order) {
            
            $internalDate = $order['date'];
            $is_brk_tray_service = $order['is_brk_tray_service'] ?? 0;
            $is_lunch_tray_service = $order['is_lunch_tray_service'] ?? 0;
            $is_dinner_tray_service = $order['is_dinner_tray_service'] ?? 0;
            $is_brk_escort_service = $order['is_brk_escort_service'] ?? 0;
            $is_lunch_escort_service = $order['is_lunch_escort_service'] ?? 0;
            $is_dinner_escort_service = $order['is_dinner_escort_service'] ?? 0;
            $is_brk_takeout_service = $order['is_brk_takeout_service'] ?? 0;
            $is_lunch_takeout_service = $order['is_lunch_takeout_service'] ?? 0;
            $is_dinner_takeout_service = $order['is_dinner_takeout_service'] ?? 0;

            $this->orderDetails->upsertByFilters([
                'room_id' => $roomId,
                'date' => $internalDate,
            ], [
                'is_brk_tray_service' => $is_brk_tray_service,
                'is_lunch_tray_service' => $is_lunch_tray_service,
                'is_dinner_tray_service' => $is_dinner_tray_service,
                'is_brk_escort_service' => $is_brk_escort_service,
                'is_lunch_escort_service' => $is_lunch_escort_service,
                'is_dinner_escort_service' => $is_dinner_escort_service,
                'is_brk_takeout_service' => $is_brk_takeout_service,
                'is_lunch_takeout_service' => $is_lunch_takeout_service,
                'is_dinner_takeout_service' => $is_dinner_takeout_service,
            ]);

            if ($order['items'] ?? false) {
                foreach($order['items'] as $n) {
                    $n['order_id'] = intval($n['order_id']);
                    $n['qty'] = intval($n['qty']);

                    if ($n['order_id'] == 0) {
                        if ($n['qty'] != 0) {
                            $order = $this->orderDetails->create([
                                'room_id' => $roomId,
                                'date' => $internalDate,
                                'item_id' => $n['item_id'],
                                'item_options' => $n['item_options'],
                                'preference' => $n['preference'],
                                'quantity' => $n['qty'],
                                'comment' => '',
                                'status' => 0,

                                'is_brk_tray_service' => $is_brk_tray_service,
                                'is_lunch_tray_service' => $is_lunch_tray_service,
                                'is_dinner_tray_service' => $is_dinner_tray_service,

                                'is_brk_escort_service' => $is_brk_escort_service,
                                'is_lunch_escort_service' => $is_lunch_escort_service,
                                'is_dinner_escort_service' => $is_dinner_escort_service,

                                'is_brk_takeout_service' => $is_brk_takeout_service,
                                'is_lunch_takeout_service' => $is_lunch_takeout_service,
                                'is_dinner_takeout_service' => $is_dinner_takeout_service,
                            ]);
                            
                            $item_array[] = $n['item_id'];
                            $order_array[] = $order->id;
                        }
                    } else {
                        $order = $this->orderDetails->findById($n['order_id']);
                        if ($order) {
                            if ($n['qty'] != 0) {
                                $order->quantity = $n['qty'];
                                $order->item_options = $n['item_options'];
                                $order->preference = $n['preference'];
                                $order->comment = '';

                                $this->orderDetails->save($order);
                            } else {
                                $this->orderDetails->delete($order);
                                $item_array[] = $n['item_id'];
                                $order_array[] = 0;    
                            }
                        }
                    }
                }
            }
        }

        return ApiResponse::format(
            status: '1',
            message: 'success',
            data: [
                'item_id' => $item_array,
                'order_id' => $order_array
            ]
        );
    }

    public function getGuestOrderList($roomId, $date)
    {
        $subCatDetails = array();
        $catArray = array();
        $breakfast = $lunch = $dinner = array();

        $items = array();

        $menuData = $this->menuDetails->getAll(
            filters: ['date' => $date],
            columns: ['items']
        );

        foreach ($menuData as $menu) {
            $menuItems = $menu->items;

            if (is_string($menuItems)) {
                $menuItems = json_decode($menuItems, true);
            }

            if (!is_array($menuItems)) {
                continue;
            }

            foreach ($menuItems as $menuItem) {
                if (empty($menuItem)) {
                    continue;
                }

                if (is_array($menuItem)) {
                    foreach ($menuItem as $id) {
                        if ($id !== '' && $id !== null) {
                            $items[] = (int) $id;
                        }
                    }
                } else {
                    $items[] = (int) $menuItem;
                }
            }
        }

        $items = array_values(array_unique($items));

        $optionDetails = $preferenceDetails = array();
        if (!empty($items)) {
            $optionDetails = $this->itemOptions->getAll()
                ->keyBy('id')
                ->map(fn ($o) => [
                    'option_name' => $o->option_name,
                    'option_name_cn' => $o->option_name_cn ?? $o->option_name,
                ])
                ->all();
            
            $preferenceDetails = $this->itemPreferences->getAll()
                ->keyBy('id')
                ->map(fn ($p) => [
                    'name' => $p->pname,
                    'name_cn' => $p->pname_cn ?? $p->pname,
                ])
                ->all();

            $categoryData = $this->itemDetails
                ->findByIdsAndParentFlag($items, true);

            foreach ($categoryData as $c) {
                if (!isset($catArray[$c->cat_id])) {
                    $catArray[$c->cat_id] = array(
                        "cat_id" => $c->cat_id,
                        "cat_name" => $c->category?->cat_name ?? "",
                        "chinese_name" => $c->category?->category_chinese_name ?? "",
                        "items" => array(),
                        "type" => $c->category?->type ?? ""
                    );
                }

                $options = array();
                $preference = array();

                if ($roomId != 0) {
                    $orderData = $this->orderDetails->getAll(
                        filters: [
                            'room_id' => $roomId,
                            'date' => $date,
                            'item_id' => $c->id,
                            'is_for_guest' => 1
                        ]
                    )->first();

                    if ($c->options != "") {
                        $cOptions = json_decode($c->options, true);
                        foreach ($cOptions as $co) {
                            $co = intval($co);
                            if ($optionDetails[$co]) {
                                $options[$co] = array(
                                    "id" => $co,
                                    "name" => $optionDetails[$co]['option_name'],
                                    "c_name" => $optionDetails[$co]['option_name_cn'],
                                    "is_selected" => (
                                        $orderData && $orderData->item_options != null
                                        ? ($co == $orderData->item_options ? 1 : 0)
                                        : 0
                                    )
                                );
                            }
                        }
                    }

                    if ($c->preference != "") {
                        $cPreferences = json_decode($c->preference, true);
                        foreach ($cPreferences as $cp) {
                            $cp = intval($cp);
                            if ($preferenceDetails[$cp]) {
                                $isSelected = (
                                    $orderData && $orderData->preference != null
                                    ? in_array($cp, explode(",", $orderData->preference))
                                    : false
                                );

                                $preference[$cp] = array(
                                    "id" => $cp,
                                    "name" => $preferenceDetails[$cp]['name'],
                                    "c_name" => $preferenceDetails[$cp]['name_cn'],
                                    "is_selected" => $isSelected ? 1 : 0
                                );
                            }
                        }
                    }

                    array_push(
                        $catArray[$c->cat_id]["items"],
                        array(
                            "type" => "item",
                            'parent_id' => $c->category?->parent_id ?? 0,
                            "item_id" => $c->id,
                            "item_name" => $c->item_name,
                            "chinese_name" => $c->item_chinese_name,
                            "options" => array_values($options),
                            "preference" => array_values($preference),
                            "item_image" => !empty($c->item_image)
                                ? Storage::url($c->item_image) : null,
                            "qty" => (
                                $orderData
                                ? ($orderData->is_for_guest ? $orderData->quantity : 0)
                                : 0
                            ),
                            "comment" => "",
                            "order_id" => ($orderData ? $orderData->id : 0)
                        )
                    );
                } else {
                    $sum = $this->orderDetails->sumQuantityByDateAndItem($date, $c->id);

                    if ($c->options != "") {
                        $cOptions = json_decode($c->options, true);
                        foreach ($cOptions as $co) {
                            $co = intval($co);
                            if ($optionDetails[$co]) {
                                $itemCount = $this->orderDetails->getAll(
                                    filters: [
                                        'date' => $date,
                                        'item_id' => $c->id,
                                        'item_options' => $co,
                                    ]
                                )->count();

                                $options[$co] = array(
                                    "id" => $co,
                                    "name" => $optionDetails[$co]['option_name'],
                                    "c_name" => $optionDetails[$co]['option_name_cn'],
                                    "is_selected" => 0,
                                    "item_count" => $itemCount
                                );
                            }
                        }
                    }

                    array_push(
                        $catArray[$c->cat_id]["items"],
                        array(
                            "type" => "item",
                            'parent_id' => $c->category?->parent_id ?? 0,
                            "item_id" => $c->id,
                            "item_name" => $c->item_name,
                            "chinese_name" => $c->item_chinese_name,
                            "is_expanded" => (count(array_values($options)) > 0 ? 1 : 0),
                            "options" => array_values($options),
                            "preference" => array_values($preference),
                            "item_image" => !empty($c->item_image)
                                ? Storage::url($c->item_image) : null,
                            "qty" => $sum,
                            "comment" => "",
                            "order_id" => 0
                        )
                    );
                }
            }

            $subCategoryData = $this->itemDetails
                ->findByIdsAndParentFlag($items, false);

            foreach ($subCategoryData as $sc) {
                if (!isset($subCatDetails[$sc->cat_id])) {
                    $subCatDetails[$sc->cat_id] = array(
                        "cat_id" => $sc->cat_id,
                        "cat_name" => $sc->category?->cat_name ?? "",
                        "chinese_name" => $sc->category?->category_chinese_name ?? "",
                        "parent_id" => $sc->category?->parent_id ?? 0,
                        "items" => array()
                    );
                }

                $parentId = $sc->category?->parent_id ?? 0;

                if (!isset($catArray[$parentId])) {
                    $parentCategory = $this->categoryDetails->findById($parentId);

                    $catArray[$parentId] = array(
                        "cat_id" => $parentCategory?->id ?? $parentId,
                        "cat_name" => $parentCategory?->cat_name ?? "",
                        "chinese_name" => $parentCategory?->category_chinese_name ?? "",
                        "items" => array(),
                        "type" => $parentCategory?->type ?? ""
                    );
                }

                $options = array();
                $preference = array();

                if ($roomId != 0) {
                    $orderData = $this->orderDetails->getAll(
                        filters: [
                            'room_id' => $roomId,
                            'date' => $date,
                            'item_id' => $sc->id,
                            'is_for_guest' => 1
                        ]
                    )->first();

                    if ($sc->options != "") {
                        $scOptions = json_decode($sc->options, true);
                        foreach ($scOptions as $sco) {
                            $sco = intval($sco);
                            if ($optionDetails[$sco]) {
                                $options[$sco] = array(
                                    "id" => $sco,
                                    "name" => $optionDetails[$sco]['option_name'],
                                    "c_name" => $optionDetails[$sco]['option_name_cn'],
                                    "is_selected" => (
                                        $orderData && $orderData->item_options != null
                                        ? ($sco == $orderData->item_options ? 1 : 0)
                                        : 0
                                    )
                                );
                            }
                        }
                    }

                    if ($sc->preference != "") {
                        $scPreferences = json_decode($sc->preference, true);
                        foreach ($scPreferences as $scp) {
                            $scp = intval($scp);
                            if ($preferenceDetails[$scp]) {
                                $isSelected = (
                                    $orderData && $orderData->preference != null
                                    ? in_array($scp, explode(",", $orderData->preference))
                                    : false
                                );

                                $preference[$scp] = array(
                                    "id" => $scp,
                                    "name" => $preferenceDetails[$scp]['name'],
                                    "c_name" => $preferenceDetails[$scp]['name_cn'],
                                    "is_selected" => $isSelected ? 1 : 0
                                );
                            }
                        }
                    }

                    array_push(
                        $subCatDetails[$sc->cat_id]["items"],
                        array(
                            "item_id" => $sc->id,
                            'parent_id' => $parentId,
                            "item_name" => $sc->item_name,
                            "chinese_name" => $sc->item_chinese_name,
                            "item_image" => !empty($sc->item_image)
                                ? Storage::url($sc->item_image) : null,
                            "options" => array_values($options),
                            "preference" => array_values($preference),
                            "qty" => (
                                $orderData
                                ? ($orderData->is_for_guest ? $orderData->quantity : 0)
                                : 0
                            ),
                            "comment" => "",
                            "order_id" => ($orderData ? $orderData->id : 0)
                        )
                    );
                } else {
                    $sum = $this->orderDetails->sumQuantityByDateAndItem($date, $sc->id);

                    if ($sc->options != "") {
                        $scOptions = json_decode($sc->options, true);
                        foreach ($scOptions as $sco) {
                            $sco = intval($sco);
                            if ($optionDetails[$sco]) {
                                $itemCount = $this->orderDetails->getAll(
                                    filters: [
                                        'date' => $date,
                                        'item_id' => $sc->id,
                                        'item_options' => $sco,
                                    ]
                                )->count();

                                $options[$sco] = array(
                                    "id" => $sco,
                                    "name" => $optionDetails[$sco]['option_name'],
                                    "c_name" => $optionDetails[$sco]['option_name_cn'],
                                    "is_selected" => 0,
                                    "item_count" => $itemCount
                                );
                            }
                        }
                    }

                    array_push(
                        $subCatDetails[$sc->cat_id]["items"],
                        array(
                            "item_id" => $sc->id,
                            'parent_id' => $parentId,
                            "item_name" => $sc->item_name,
                            "chinese_name" => $sc->item_chinese_name,
                            "item_image" => !empty($sc->item_image)
                                ? Storage::url($sc->item_image) : null,
                            "is_expanded" => (count(array_values($options)) > 0 ? 1 : 0),
                            "options" => array_values($options),
                            "preference" => array_values($preference),
                            "qty" => $sum,
                            "comment" => "",
                            "order_id" => 0
                        )
                    );
                }
            }

            foreach ($subCatDetails as $sc) {
                if (isset($catArray[$sc['parent_id']])) {
                    array_push(
                        $catArray[$sc['parent_id']]["items"],
                        array(
                            "type" => "sub_cat",
                            "item_id" => null,
                            "cat_id" => $sc["cat_id"],
                            "parent_id" => $sc["parent_id"],
                            "item_name" => $sc["cat_name"],
                            "chinese_name" => $sc["chinese_name"],
                            "options" => [],
                            "preference" => [],
                            "item_image" => "",
                            "qty" => 0,
                            "comment" => "",
                            "order_id" => 0
                        )
                    );

                    foreach ($sc["items"] as $sci) {
                        array_push(
                            $catArray[$sc['parent_id']]["items"],
                            array_merge($sci, ["type" => "sub_cat_item"])
                        );
                    }
                }
            }
        }

        foreach ($catArray as $cat) {
            $type = intval($cat['type']);
            unset($cat['type']);

            if ($type == 1) {
                array_push($breakfast, $cat);
            } else if ($type == 2) {
                array_push($lunch, $cat);
            } else if ($type == 3) {
                array_push($dinner, $cat);
            }
        }

        $trayServiceData = $this->orderDetails->getAll(
            filters: [
                'room_id' => $roomId,
                'date' => $date,
                'is_for_guest' => 1
            ]
        )->first();

        $occupancy = $this->dateWiseOccupancies->getAll(
            filters: [
                'room_id' => $roomId,
                'date' => $date
            ]
        )->first();

        return ApiResponse::format(
            status: '1',
            message: '',
            data: [
                'breakfast' => $breakfast,
                'lunch' => $lunch,
                'dinner' => $dinner,
                'occupancy' => $occupancy?->occupancy ?? 0,
                'is_brk_tray_service' => $trayServiceData?->is_brk_tray_service ?? 0,
                'is_lunch_tray_service' => $trayServiceData?->is_lunch_tray_service ?? 0,
                'is_dinner_tray_service' => $trayServiceData?->is_dinner_tray_service ?? 0
            ]
        );
    }

    /**
     * Produce a print-ready order summary for a given date and meal type.
     *
     * $mealType is a string: "breakfast", "lunch", or "dinner".
     *
     * Flow:
     *   1. Load all orders for the date.
     *   2. For each order, resolve the item's category and determine its meal type.
     *   3. Filter to the requested $mealType only.
     *   4. Exclude specific hardcoded category IDs (see gotcha below).
     *   5. Group by room, de-duplicate, and attach service flags and room metadata.
     *
     * GOTCHA — hardcoded category IDs:
     *   Category IDs [2, 7, 10, 13] are excluded from the print output. These correspond
     *   to specific soup/dessert sub-categories that were excluded by design, plus ID 13
     *   which is a deleted category. If categories are ever restructured in the DB, this
     *   list must be updated manually. Consider moving these to the settings table.
     *
     * GOTCHA — $roomIds filter:
     *   Despite the parameter name being $roomId (singular int), this function queries
     *   room_details filtered by room_name == $roomId. If $roomId is 0 (admin view),
     *   the filter may return unexpected results depending on room naming. The result
     *   is only used to skip orders not belonging to the matched room names.
     */
    public function printOrderData(
        int $roomId,
        string $date,
        string $mealType
    ) {
        $instruction = "";
        $food_texture = "";
        $resident_name = "";

        if ($date == "" || $mealType == "") {
            return ApiResponse::format(
                status: '1',
                message: '',
                data: []
            );
        }

        $preferences = $this->itemPreferences->getAll();
        $preferences_cn = $preferences->pluck('pname_cn', 'id');
        $preferences_en = $preferences->pluck('pname', 'id')
            ->mapWithKeys(function ($name, $id) use ($preferences_cn) {
            return [
                $id => [
                    'name' => $name,
                    'name_cn' => $preferences_cn->get($id) ?? $name,
                ]
            ];
        })->all();

    $categoryDetails = $this->categoryDetails->getAll();
        $categoryTypeMap = [
            1 => 'breakfast',
            2 => 'lunch',
            3 => 'dinner',
        ];
        $categoryGroups = $categoryDetails
            ->groupBy('type')
            ->map(fn ($items) => $items->pluck('id')->values()->all());
        $categoryDetails = [];
        foreach ($categoryTypeMap as $type => $label) {
            $categoryDetails[$label] = $categoryGroups->get($type, []);
        }

        $roomIds = $this->roomDetails
            ->getAll(
                filters: ['room_name' => $roomId],
                columns: ['id', 'room_name']
            )
            ->pluck('room_name', 'id')
            ->all();

        $combinedItemsData = [];
        $itemsByMealType = [
            'breakfast' => [],
            'lunch' => [],
            'dinner' => []
        ];

        $orderData = $this->orderDetails->getAll(
            filters: [
                'date' => $date,
                'order_by' => 'id'
            ]
        );

        foreach ($orderData as $order)
        {
            if (!array_key_exists($order->room_id, $roomIds)) continue;
            
            if (array_key_exists($mealType, $categoryDetails) && $order->itemData) {
                $itemData = $order->itemData;
                if (!in_array($itemData->category->type, $categoryDetails[$mealType])) {
                    continue;
                }
            }

            $preferenceArray = array();
            $optionDetails = "";

            $room_id = $order->room_id;

            $catData = $order->itemData?->category;
            if ($catData) {
                $type = intval($catData->type);
                if ($order->item_options != "") {
                    $optionData = $this->itemOptions->findById($order->item_options);
                    $optionDetails = $optionData?->option_name ?? "";
                }

                if ($order->preference != "") {
                    $preferenceIds = explode(',', $order->preference);
                    foreach ($preferenceIds as $preferenceId) {
                        if (isset($preferences_en[$preferenceId])) {
                            $preferenceArray[] = $preferences_en[$preferenceId];
                        }
                    }
                }

                $order->cat_id = intval($catData->id);
                $data = array(
                    "category" => (
                        intval($catData->parent_id) == 0
                        ? $catData->cat_name
                        : $catData->catParentId?->cat_name ?? ""
                    ),
                    "sub_cat" => (
                        intval($catData->parent_id) == 0
                        ? ""
                        : $catData->cat_name
                    ),
                    "item_name" => $order->itemData?->item_name ?? "",
                    "quantity" => intval($order->quantity),
                    "options" => $optionDetails,
                    "preference" => $preferenceArray,
                    "order_id" => $order->id
                );

                // Exclude soup/dessert sub-categories and deleted category 13.
                // These IDs are hardcoded — see docblock for the maintenance note.
                if (!in_array(intval($catData->id), [2,7,10,13])) { // LUNCH SOUP, LUNCH DESSERT, DINNER DESSERT, 13 is deleted
                    $meal = $categoryTypeMap[intval($catData->type)];
                    $itemsByMealType[$meal][] = $data;
                    $combinedItemsData[$order->room_id][$meal][$order->is_for_guest][] = $data;
                }
            }

            $spiData = $this->roomDetails->getAll(
                filters: ['id' => $order->room_id],
                columns: [
                    "special_instrucations",
                    "food_texture",
                    "resident_name",
                    "room_name"
                ]
             )->first();

            $instruction = $spiData?->special_instrucations ?? "";
            $food_texture = $spiData?->food_texture ?? "";
            $resident_name = $spiData?->resident_name ?? "NA";

            $lastOrderFilters = [
                'room_id' => $order->room_id,
                'date' => $date,
            ];
            if ($order->is_for_guest) {
                $lastOrderFilters['is_for_guest'] = 1;
            }

            $lastOrder = $this->orderDetails->getAll(
                filters: $lastOrderFilters,
                order_by: 'id',
                order_direction: 'desc'
            )->first();

            $items = $itemsByMealType[$mealType] ?? [];

            $finalData[] = array(
                "special_instruction" => $instruction,
                "food_texture" => $food_texture,
                "resident_name" => $resident_name,
                "is_brk_tray_service" => $lastOrder?->is_brk_tray_service ?? 0,
                "is_lunch_tray_service" => $lastOrder?->is_lunch_tray_service ?? 0,
                "is_dinner_tray_service" => $lastOrder?->is_dinner_tray_service ?? 0,
                "is_brk_escort_service" => $lastOrder?->is_brk_escort_service ?? 0,
                "is_lunch_escort_service" => $lastOrder?->is_lunch_escort_service ?? 0,
                "is_dinner_escort_service" => $lastOrder?->is_dinner_escort_service ?? 0,
                "is_brk_takeout_service" => $lastOrder?->is_brk_takeout_service ?? 0,
                "is_lunch_takeout_service" => $lastOrder?->is_lunch_takeout_service ?? 0,
                "is_dinner_takeout_service" => $lastOrder?->is_dinner_takeout_service ?? 0,
                "room_id" => $order->room_id,
                "room_name" => $spiData?->room_name . ($order->is_for_guest ? " Guest" : ""),
                "is_guest" => $order->is_for_guest
            );
        }

        $payload = [];
        $encounteredRoomIds = [];

        foreach ($finalData as $data) {
            if (!in_array($data['room_name'], $encounteredRoomIds)) {
                $encounteredRoomIds[] = $data['room_name'];

                if (array_key_exists($data['room_id'], $combinedItemsData)) {
                    $data['Items'] = $combinedItemsData[$data['room_id']][$mealType][$data['is_guest']] ?? [];
                    $payload[] = $data;
                }
            }
        }

        return ApiResponse::format(
            status: '1',
            message: '',
            data: ['Data' => $payload]
        );
    }
}