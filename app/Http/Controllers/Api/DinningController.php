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

class DinningController extends Controller
{
    public function __construct()
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
                return $this->sendResultJSON("2", $validator->errors()->first());
            }
            $room_no = $request->input("room_no");
            $password = $request->input("password");
            $user = RoomDetail::where("room_name", $room_no)->where("password", $password)->first();

            $last_date = "";
            $menu_data = MenuDetail::select("date")->orderBy("date", "desc")->first();
            if ($menu_data) {
                $last_date = Carbon::parse($menu_data->date)->format('Y-m-d');
            }

            $rooms = RoomDetail::where("is_active", 1)->get();
            $rooms_array = array();
            foreach (count($rooms) > 0 ? $rooms : array() as $r) {
                array_push($rooms_array, array("id" => $r->id, "name" => $r->room_name, "occupancy" => $r->occupancy, "resident_name" => $r->resident_name));
            }

            $settings = DB::table('settings')->get();

            $settingsArray = [];

            foreach ($settings as $setting) {
                $settingsArray[$setting->key] = $setting->value;
            }

            if (!$user) {
                $user = User::where("user_name", $room_no)->first();

                if ($user) {
                    if (!Hash::check($password, $user->password)) {
                        return $this->sendResultJSON("2", "User not Found");
                    } else {
                        $role = intval($user->role_id) == 1 ? "admin" : "kitchen";
                        $roleName = Role::select('name')->where('id', $user->role_id)->get();
                        $user_token = $this->generate_access_token($user->id, $role);
                        $formTypes = "";
                        // if ($roleName[0]['name'] == "admin" || $roleName[0]['name'] == "concierge"){

                        $formTypes = FormType::all();
                        // }

                        $userQuery = "select u.id , r.name as roleName,u.name as userName , u.email from users u left join roles r on u.role_id = r.id where u.role_id IN (1,4,7)";

                        $userResults = DB::select($userQuery);

                        $userData = [];

                        foreach ($userResults as $userResult) {

                            $userData[] = [
                                'id' => $userResult->id,
                                'role_name' => $userResult->roleName,
                                'name' => $userResult->userName,
                                'email' => $userResult->email,
                            ];
                        }




                        return $this->sendResultJSON("1", "Successfully Login", array("room_id" => 0, 'rooms' => $rooms_array, 'guideline' => $settingsArray['site.app_msg'], 'guideline_cn' => $settingsArray['site.app_msg_cn'] != "" ? $settingsArray['site.app_msg_cn'] : $settingsArray['site.app_msg'], "room_number" => "", "occupancy" => 0, "resident_name" => "", "language" => 0, "last_menu_date" => $last_date, "authentication_token" => $user_token, "role" => $roleName[0]['name'], "form_types" => $formTypes, 'user_list' => $userData, 'user_id' => $user->id));
                    }
                } else {

                    $message = "User not Found";

                    $roomIds = RoomDetail::select('room_name')->where("room_name", 'like', '%' . $room_no . '%')->get();

                    $customMessage = "";
                    $customMessageRoomPhrase = "";
                    $similarRoomFound = [];

                    foreach ($roomIds as $roomId) {
                        $similarRoomFound[] = $roomId->room_name;
                    }

                    if (count($similarRoomFound) > 1) {

                        $customMessage = "There are " . count($similarRoomFound) . " residents in room " . $room_no . ". Please enter " . implode(" OR ", $similarRoomFound);
                    }


                    return $this->sendResultJSON("2", !empty($customMessage) ? $customMessage : $message);
                }
            }


            if ($user->is_active == 1) {
                $user_token = $this->generate_access_token($user->id, "user");
                return $this->sendResultJSON("1", "Successfully Login", array("room_id" => $user->id, 'rooms' => $rooms_array, 'guideline' => $settingsArray['site.app_msg'], 'guideline_cn' => $settingsArray['site.app_msg_cn'] != "" ? $settingsArray['site.app_msg_cn'] : $settingsArray['site.app_msg'], "room_number" => $user->room_name, "occupancy" => $user->occupancy, "resident_name" => $user->resident_name, "language" => intval($user->language), "last_menu_date" => $last_date, "authentication_token" => $user_token, "role" => "user"));
            } else {
                return $this->sendResultJSON("3", "User not active");
            }
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
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
        $rooms = RoomDetail::where("is_active", 1)->get();
        $rooms_array = array();
        foreach (count($rooms) > 0 ? $rooms : array() as $r) {
            array_push($rooms_array, array("id" => $r->id, "name" => $r->room_name, "occupancy" => $r->occupancy));
        }
        $last_date = "";
        $menu_data = MenuDetail::select("date")->orderBy("date", "desc")->first();
        if ($menu_data) {
            $last_date = $menu_data->date;
        }
        return $this->sendResultJSON('1', '', array('rooms' => $rooms_array, 'last_menu_date' => $last_date));
    }

    public function getOrderList(Request $request)
    {
        if (!session("user_details")) {
            return $this->sendResultJSON("11", "Unauthorised");
        }

        $room_id = intval($request->input('room_id'));

        $date = $request->input('date');
        $sub_cat_details = array();
        $cat_array = array();
        $breakfast = $lunch = $dinner = array();

        // $items = "";
        // $day = Carbon::parse($date)->format("l");
        // if ($day == "Sunday") {
        //     $items = "1,4,5,6,7,20,28,38,15,18,3,52,17,16";
        // } elseif ($day == "Monday") {
        //     $items = "9,4,5,6,7,21,29,39,15,17,18,46,53,16";
        // } elseif ($day == "Tuesday") {
        //     $items = "4,5,6,7,15,17,18,16,10,22,31,41,47,54";
        // } elseif ($day == "Wednesday") {
        //     $items = "4,5,6,7,15,17,18,16,11,23,32,42,48,55";
        // } elseif ($day == "Thursday") {
        //     $items = "4, 5, 6, 7, 15, 17, 18, 16, 12, 24, 34, 43, 49, 56";
        // } elseif ($day == "Friday") {
        //     $items = "4, 5, 6, 7, 15, 17, 18, 16, 13, 25, 36, 44, 50, 57";
        // } elseif ($day == "Saturday") {
        //     $items = "4, 5, 6, 7, 15, 17, 18, 16, 14, 27, 37, 45, 51, 58";
        // }

        $items = array();

        // if menu is not present then return empry menu

        // $menu_data = MenuDetail::selectRaw("items")->whereRaw("date = '" . $date . "' OR is_allDay = 1")->get();
        $menu_data = MenuDetail::selectRaw("items")->whereRaw("date = '" . $date . "'")->get();
        // $menu_data = MenuDetail::selectRaw("items")->whereRaw("date = '" . $date . "' ")->get();
        foreach (count($menu_data) > 0 ? $menu_data : array() as $m) {
            $menu_items = json_decode($m->items, true);
            foreach (count($menu_items) > 0 ? $menu_items : array() as $mi) {
                if (count($mi) > 0)
                    array_push($items, implode(",", $mi));
            }
        }
        $option_details = $preference_details = array();
        $items = implode(",", $items);
        if ($items != "") {
            $options = ItemOption::all();
            foreach (count($options) > 0 ? $options : array() as $o) {
                $option_details[intval($o->id)] = array("option_name" => $o->option_name, "option_name_cn" => ($o->option_name_cn != null ? $o->option_name_cn : $o->option_name));
            }
            $preferences = ItemPreference::all();
            foreach (count($preferences) > 0 ? $preferences : array() as $p) {
                $preference_details[$p->id] = array("name" => $p->pname, "name_cn" => ($p->pname_cn != null ? $p->pname_cn : $p->pname));
            }


            $category_data = CategoryDetail::join("item_details", "item_details.cat_id", "=", "category_details.id")->selectRaw("category_details.*,item_details.id as item_id,item_details.item_name,item_details.item_image,item_details.item_chinese_name,item_details.options,item_details.preference")->where("category_details.parent_id", 0)->whereRaw("item_details.id IN (" . $items . ")")->whereRaw("item_details.deleted_at IS NULL")->orderBy("category_details.id", "asc")->orderBy("item_details.id", "asc")->get();

            foreach (count($category_data) > 0 ? $category_data : array() as $c) {
                if (!isset($cat_array[$c->id])) {
                    $cat_array[$c->id] = array("cat_id" => $c->id, "cat_name" => $c->cat_name, "chinese_name" => $c->category_chinese_name, "items" => array(), "type" => $c->type);
                }
                $options = array();

                $preference = array();

                if ($room_id != 0) {
                    $order_data = OrderDetail::selectRaw("id,quantity,item_options,preference,is_for_guest,is_brk_tray_service,is_lunch_tray_service,is_dinner_tray_service,is_brk_escort_service,is_lunch_escort_service,is_dinner_escort_service")->where("room_id", $room_id)->where("date", $date)->where("item_id", $c->item_id)->where("is_for_guest", 0)->first();

                    if ($c->options != "") {
                        $c_options = json_decode($c->options);
                        foreach (count($c_options) > 0 ? $c_options : array() as $co) {
                            $co = intval($co);
                            if ($option_details[$co]) {
                                $options[$co] = array("id" => $co, "name" => $option_details[$co]['option_name'], "c_name" => $option_details[$co]['option_name_cn'], "is_selected" => ($order_data && $order_data->item_options != null ? ($co == $order_data->item_options ? 1 : 0) : 0));
                            }
                        }
                    }

                    if ($c->preference != "") {
                        $c_preferences = json_decode($c->preference);
                        foreach (count($c_preferences) > 0 ? $c_preferences : array() as $cp) {
                            $cp = intval($cp);
                            if ($preference_details[$cp]) {
                                $preference[$cp] = array("id" => $cp, "name" => $preference_details[$cp]['name'], "c_name" => $preference_details[$cp]['name_cn'], "is_selected" => ($order_data && $order_data->preference != null ? (in_array($cp, explode(",", $order_data->preference)) ? 1 : 0) : 0));
                            }
                        }
                    }
                    array_push($cat_array[$c->id]["items"], array("type" => "item", "item_id" => $c->item_id, "item_name" => $c->item_name, "chinese_name" => $c->item_chinese_name, "options" => array_values($options), "preference" => array_values($preference), "item_image" => !empty($c->item_image) ? Storage::url($c->item_image) : NULL, "qty" => ($order_data ? ($order_data->is_for_guest != 1 ? $order_data->quantity : 0) : 0), "comment" => "", "order_id" => ($order_data ? $order_data->id : 0)));
                } else {
                    $order_data = OrderDetail::selectRaw("sum(quantity) as quantity")->where("date", $date)->where("item_id", $c->item_id)->groupBy("item_id")->first();

                    if ($c->options != "") {
                        $c_options = json_decode($c->options);
                        foreach (count($c_options) > 0 ? $c_options : array() as $co) {
                            $co = intval($co);
                            if ($option_details[$co]) {
                                $options[$co] = array("id" => $co, "name" => $option_details[$co]['option_name'], "c_name" => $option_details[$co]['option_name_cn'], "is_selected" => 0, "item_count" => OrderDetail::where("date", $date)->where("item_id", $c->item_id)->where("item_options", $co)->count());
                            }
                        }
                    }

                    array_push($cat_array[$c->id]["items"], array("type" => "item", "item_id" => $c->item_id, "item_name" => $c->item_name, "chinese_name" => $c->item_chinese_name, "is_expanded" => count(array_values($options)) > 0 ? 1 : 0, "options" => array_values($options), "preference" => array_values($preference), "item_image" => !empty($c->item_image) ? Storage::url($c->item_image) : NULL, "qty" => ($order_data ? intval($order_data->quantity) : 0), "comment" => "", "order_id" => 0));
                }
            }
            $sub_category_data = CategoryDetail::join("item_details", "item_details.cat_id", "=", "category_details.id")->selectRaw("category_details.*,item_details.id as item_id,item_details.item_name,item_details.item_image,item_details.item_chinese_name,item_details.options,item_details.preference")->where("category_details.parent_id", "!=", 0)->whereRaw("item_details.id IN (" . $items . ")")->whereRaw("item_details.deleted_at IS NULL")->orderBy("category_details.id", "asc")->orderBy("item_details.id", "asc")->get();
            foreach (count($sub_category_data) > 0 ? $sub_category_data : array() as $sc) {
                if (!isset($sub_cat_details[$sc->id])) {
                    $sub_cat_details[$sc->id] = array("cat_id" => $sc->id, "cat_name" => $sc->cat_name, "chinese_name" => $sc->category_chinese_name, "parent_id" => $sc->parent_id, "items" => array());
                }
                if (!isset($cat_array[$sc->parent_id])) {
                    if ($sc->parentData) {
                        $cat_array[$sc->parent_id] = array("cat_id" => $sc->parentData->id, "cat_name" => $sc->parentData->cat_name, "chinese_name" => $sc->parentData->category_chinese_name, "items" => array(), "type" => $c->type);
                    }
                }
                $options = array();

                $preference = array();

                if ($room_id != 0) {
                    $order_data = OrderDetail::selectRaw("id,quantity,item_options,preference,is_for_guest,is_brk_tray_service,is_lunch_tray_service,is_dinner_tray_service,is_brk_escort_service,is_lunch_escort_service,is_dinner_escort_service")->where("room_id", $room_id)->where("date", $date)->where("item_id", $sc->item_id)->where("is_for_guest", 0)->first();

                    if ($sc->options != "") {
                        $c_options = json_decode($sc->options);
                        foreach (count($c_options) > 0 ? $c_options : array() as $co) {
                            $co = intval($co);
                            if ($option_details[$co]) {
                                $options[$co] = array("id" => $co, "name" => $option_details[$co]['option_name'], "c_name" => $option_details[$co]['option_name_cn'], "is_selected" => ($order_data && $order_data->item_options != null ? ($co == $order_data->item_options ? 1 : 0) : 0));
                            }
                        }
                    }

                    if ($sc->preference != "") {
                        $c_preferences = json_decode($sc->preference);
                        foreach (count($c_preferences) > 0 ? $c_preferences : array() as $cp) {
                            $cp = intval($cp);
                            if ($preference_details[$cp]) {
                                $preference[$cp] = array("id" => $cp, "name" => $preference_details[$cp]['name'], "c_name" => $preference_details[$cp]['name_cn'], "is_selected" => ($order_data && $order_data->preference != null ? (in_array($cp, explode(",", $order_data->preference)) ? 1 : 0) : 0));
                            }
                        }
                    }

                    array_push($sub_cat_details[$sc->id]["items"], array("item_id" => $sc->item_id, "item_name" => $sc->item_name, "chinese_name" => $sc->item_chinese_name, "item_image" => !empty($sc->item_image) ? Storage::url($sc->item_image) : NULL, "options" => array_values($options), "preference" => array_values($preference), "qty" => ($order_data ? ($order_data->is_for_guest != 1 ? $order_data->quantity : 0) : 0), "comment" => "", "order_id" => ($order_data ? $order_data->id : 0)));
                } else {
                    $order_data = OrderDetail::selectRaw("sum(quantity) as quantity")->where("date", $date)->where("item_id", $sc->item_id)->groupBy("item_id")->first();

                    if ($sc->options != "") {
                        $c_options = json_decode($sc->options);
                        foreach (count($c_options) > 0 ? $c_options : array() as $co) {
                            $co = intval($co);
                            if ($option_details[$co]) {
                                $options[$co] = array("id" => $co, "name" => $option_details[$co]['option_name'], "c_name" => $option_details[$co]['option_name_cn'], "is_selected" => 0, "item_count" => OrderDetail::where("date", $date)->where("item_id", $sc->item_id)->where("item_options", $co)->count());
                            }
                        }
                    }

                    array_push($sub_cat_details[$sc->id]["items"], array("item_id" => $sc->item_id, "item_name" => $sc->item_name, "chinese_name" => $sc->item_chinese_name, "item_image" => !empty($sc->item_image) ? Storage::url($sc->item_image) : NULL, "is_expanded" => count(array_values($options)) > 0 ? 1 : 0, "options" => array_values($options), "preference" => array_values($preference), "qty" => ($order_data ? intval($order_data->quantity) : 0), "comment" => "", "order_id" => ($order_data ? $order_data->id : 0)));
                }
            }
            foreach (count($sub_cat_details) > 0 ? $sub_cat_details : array() as $sc) {
                if (isset($cat_array[$sc['parent_id']])) {
                    array_push($cat_array[$sc['parent_id']]["items"], array("type" => "sub_cat", "item_id" => $sc["cat_id"], "item_name" => $sc["cat_name"], "chinese_name" => $sc["chinese_name"], "options" => [], "preference" => [], "item_image" => "", "qty" => 0, "comment" => "", "order_id" => 0));
                    foreach (count($sc["items"]) > 0 ? $sc["items"] : array() as $sci) {
                        $sc_item = array("type" => "sub_cat_item", "item_id" => $sci["item_id"], "item_name" => $sci["item_name"], "chinese_name" => $sci["chinese_name"], "item_image" => $sci["item_image"], "options" => $sci["options"], "preference" => $sci["preference"], "qty" => $sci["qty"], "comment" => $sci["comment"], "order_id" => $sci["order_id"]);
                        if (isset($sci["is_expanded"])) {
                            $sc_item["is_expanded"] = $sci["is_expanded"];
                        }
                        array_push($cat_array[$sc['parent_id']]["items"], $sc_item);
                    }
                    //, "items" => array_values($sc["items"]
                }
            }
        }
        foreach (count($cat_array) > 0 ? $cat_array : array() as $c) {
            $type = intval($c['type']);
            unset($c['type']);
            if ($type == 1) {
                array_push($breakfast, $c);
            } else if ($type == 2) {
                array_push($lunch, $c);
            } else if ($type == 3) {
                array_push($dinner, $c);
            }
        }

        $last_date = "";
        $menu_data = MenuDetail::select("date")->orderBy("date", "desc")->first();
        if ($menu_data) {
            $last_date = $menu_data->date;
        }

        $instruction = "";
        $spi_data = RoomDetail::select("special_instrucations")->where("id", $room_id)->first();

        $tray_service_data = OrderDetail::selectRaw("is_brk_tray_service,is_lunch_tray_service,is_dinner_tray_service,is_brk_escort_service,is_lunch_escort_service,is_dinner_escort_service")->where("room_id", $room_id)->where("date", $date)->where("is_for_guest", 0)->orderBy("id", "DESC")->first();
        // echo $tray_service_data->is_brk_tray_service;die;
        // print_r($tray_service_data);die;

        if ($spi_data)
            $instruction = $spi_data->special_instrucations;

        return $this->sendResultJSON('1', '', array('breakfast' => $breakfast, 'lunch' => $lunch, 'dinner' => $dinner, 'last_menu_date' => $last_date, 'special_instruction' => $instruction, 'is_brk_tray_service' => $tray_service_data ? $tray_service_data->is_brk_tray_service : 0, 'is_lunch_tray_service' => $tray_service_data ? $tray_service_data->is_lunch_tray_service : 0, 'is_dinner_tray_service' => $tray_service_data ? $tray_service_data->is_dinner_tray_service : 0, 'is_brk_escort_service' => $tray_service_data ? $tray_service_data->is_brk_escort_service : 0, 'is_lunch_escort_service' => $tray_service_data ? $tray_service_data->is_lunch_escort_service : 0, 'is_dinner_escort_service' => $tray_service_data ? $tray_service_data->is_dinner_escort_service : 0));
    }

    public function getItemList(Request $request)
    {
        $cat_id = $request->input('cat_id');
        $date = $request->input('date');
        if ($cat_id != "" && $date != "") {
            $date_query = "";
            if ($date == "all") {
                $date_query = "(day = 'all')";
            } else {
                $date_query = "(day = '" . strtolower(Carbon::parse($date)->format("l")) . "' OR day = 'all')";
            }
            $item_details = ItemDetail::where("cat_id", $cat_id)->whereRaw($date_query)->get();
            $item_data = array();
            foreach (count($item_details) > 0 ? $item_details : array() as $i) {
                array_push($item_data, array("item_id" => $i->id, "item_name" => $i->item_name, "item_image" => "http://itask.intelligrp.com/uploads/pexels-ella-olsson-1640777.jpg", "qty" => 0, "comment" => "", "order_id" => 0));
            }
            return $this->sendResultJSON('1', '', array('items' => $item_data));
        }
    }

    public function updateOrder(Request $request)
    {
        if (!session("user_details")) {
            return $this->sendResultJSON("11", "Unauthorised");
        }

        $room_id = $request->input('room_id');
        $date = $request->input('date');
        $special_instructions = $request->input('special_instructions');
        $remember = $request->input('remember_instruction');

        $is_for_guest = !empty($request->input('is_for_guest')) ? $request->input('is_for_guest') : 0;

        $is_brk_tray_service = !empty($request->input('is_brk_tray_service')) ? $request->input('is_brk_tray_service') : 0;
        $is_lunch_tray_service = !empty($request->input('is_lunch_tray_service')) ? $request->input('is_lunch_tray_service') : 0;
        $is_dinner_tray_service = !empty($request->input('is_dinner_tray_service')) ? $request->input('is_dinner_tray_service') : 0;

        $is_brk_escort_service = !empty($request->input('is_brk_escort_service')) ? $request->input('is_brk_escort_service') : 0;
        $is_lunch_escort_service = !empty($request->input('is_lunch_escort_service')) ? $request->input('is_lunch_escort_service') : 0;
        $is_dinner_escort_service = !empty($request->input('is_dinner_escort_service')) ? $request->input('is_dinner_escort_service') : 0;

        OrderDetail::where("is_for_guest", $is_for_guest)->where("date", $date)->where("room_id", $room_id)->update(['is_brk_tray_service' => $is_brk_tray_service, 'is_lunch_tray_service' => $is_lunch_tray_service, 'is_dinner_tray_service' => $is_dinner_tray_service, 'is_brk_escort_service' => $is_brk_escort_service, 'is_lunch_escort_service' => $is_lunch_escort_service, 'is_dinner_escort_service' => $is_dinner_escort_service]);



        $occupancy = $request->input('occupancy');

        $item_array = $order_array = array();
        if ($room_id != "" && $date != "") {
            if ($request->input('orders_to_change') && $request->input('orders_to_change') != "") {
                $new_data = json_decode($request->input('orders_to_change'));
                foreach (count($new_data) > 0 ? $new_data : array() as $n) {
                    $n->order_id = intval($n->order_id);
                    $n->qty = intval($n->qty);
                    if ($n->order_id == 0) {
                        if ($n->qty != 0) {

                            $order = new OrderDetail();

                            $order->room_id = $room_id;
                            $order->date = $date;
                            $order->item_id = $n->item_id;
                            $order->item_options = $n->item_options;
                            $order->preference = $n->preference;
                            $order->quantity = $n->qty;
                            $order->comment = "";
                            $order->status = 0;

                            $order->is_for_guest = $is_for_guest;
                            $order->is_brk_tray_service = $is_brk_tray_service;
                            $order->is_lunch_tray_service = $is_lunch_tray_service;
                            $order->is_dinner_tray_service = $is_dinner_tray_service;

                            $order->is_brk_escort_service = $is_brk_escort_service;
                            $order->is_lunch_escort_service = $is_lunch_escort_service;
                            $order->is_dinner_escort_service = $is_dinner_escort_service;

                            $order->save();

                            array_push($item_array, $n->item_id);
                            array_push($order_array, $order->id);
                        }
                    } else {
                        if ($n->qty == 0) {
                            OrderDetail::where("id", $n->order_id)->delete();
                            array_push($item_array, $n->item_id);
                            array_push($order_array, 0);
                        } else {
                            OrderDetail::where("id", $n->order_id)->update(['quantity' => $n->qty, 'item_options' => $n->item_options, 'preference' => $n->preference, 'comment' => ""]);
                        }
                    }
                }
            }

            if ($is_for_guest) {

                DateWiseOccupancy::updateOrCreate([
                    'date' => $date,
                    'room_id'   => $room_id,
                ], [
                    'occupancy' => $occupancy,
                ]);
            }


            return $this->sendResultJSON('1', 'success', array('item_id' => $item_array, 'order_id' => $order_array));
        }
    }

    public function updateOrderBulk(Request $request)
    {
        // if (!session("user_details")) {
        //     return $this->sendResultJSON("11", "Unauthorised");
        // }

        $room_id = $request->input('room_id');
        $date = $request->input('current_date');
        $fullRequestData = json_decode($request->input('orders_to_change'), true);

        $item_array = $order_array = array();

        if ($room_id != "" && $date != "") {

            foreach ($fullRequestData as $request) {

                $internalDate = $request['date'];

                $is_brk_tray_service = !empty($request['is_brk_tray_service']) ? $request['is_brk_tray_service'] : 0;
                $is_lunch_tray_service = !empty($request['is_lunch_tray_service']) ? $request['is_lunch_tray_service'] : 0;
                $is_dinner_tray_service = !empty($request['is_dinner_tray_service']) ? $request['is_dinner_tray_service'] : 0;

                $is_brk_escort_service = !empty($request['is_brk_escort_service']) ? $request['is_brk_escort_service'] : 0;
                $is_lunch_escort_service = !empty($request['is_lunch_escort_service']) ? $request['is_lunch_escort_service'] : 0;
                $is_dinner_escort_service = !empty($request['is_dinner_escort_service']) ? $request['is_dinner_escort_service'] : 0;

                OrderDetail::where("date", $internalDate)->where("room_id", $room_id)->update(['is_brk_tray_service' => $is_brk_tray_service, 'is_lunch_tray_service' => $is_lunch_tray_service, 'is_dinner_tray_service' => $is_dinner_tray_service, 'is_brk_escort_service' => $is_brk_escort_service, 'is_lunch_escort_service' => $is_lunch_escort_service, 'is_dinner_escort_service' => $is_dinner_escort_service]);

                if ($request['items'] && $request['items'] != "") {

                    $new_data = ($request['items']);

                    foreach (count($new_data) > 0 ? $new_data : array() as $n) {

                        $n['order_id'] = intval($n['order_id']);
                        $n['qty'] = intval($n['qty']);

                        if ($n['order_id'] == 0) {
                            if ($n['qty'] != 0) {

                                $order = new OrderDetail();

                                $order->room_id = $room_id;
                                $order->date = $internalDate;
                                $order->item_id = $n['item_id'];
                                $order->item_options = $n['item_options'];
                                $order->preference = $n['preference'];
                                $order->quantity = $n['qty'];
                                $order->comment = "";
                                $order->status = 0;


                                $order->is_brk_tray_service = $is_brk_tray_service;
                                $order->is_lunch_tray_service = $is_lunch_tray_service;
                                $order->is_dinner_tray_service = $is_dinner_tray_service;

                                $order->is_brk_escort_service = $is_brk_escort_service;
                                $order->is_lunch_escort_service = $is_lunch_escort_service;
                                $order->is_dinner_escort_service = $is_dinner_escort_service;

                                $order->save();

                                if ($internalDate == $date) {

                                    array_push($item_array, $n['item_id']);
                                    array_push($order_array, $order->id);
                                }
                            }
                        } else {
                            if ($n['qty'] == 0) {

                                OrderDetail::where("id", $n['order_id'])->delete();

                                if ($internalDate == $date) {
                                    array_push($item_array, $n['item_id']);
                                    array_push($order_array, 0);
                                }
                            } else {
                                OrderDetail::where("id", $n['order_id'])->update(['quantity' => $n['qty'], 'item_options' => $n['item_options'], 'preference' => $n['preference'], 'comment' => ""]);
                            }
                        }
                    }
                }
            }
        }

        return $this->sendResultJSON('1', 'success', array('item_id' => $item_array, 'order_id' => $order_array));
    }

    public function copyofUpdateOrder(Request $request)
    {
        if (!session("user_details")) {
            return $this->sendResultJSON("11", "Unauthorised");
        }

        $room_id = $request->input('room_id');
        $date = $request->input('date');
        $special_instructions = $request->input('special_instructions');
        $remember = $request->input('remember_instruction');

        $is_for_guest = $request->input('is_for_guest') ? $request->input('is_for_guest') : 0;

        $is_brk_tray_service = $request->input('is_brk_tray_service') ? $request->input('is_brk_tray_service') : 0;
        $is_lunch_tray_service = $request->input('is_lunch_tray_service') ? $request->input('is_lunch_tray_service') : 0;
        $is_dinner_tray_service = $request->input('is_dinner_tray_service') ? $request->input('is_dinner_tray_service') : 0;

        $is_brk_escort_service = $request->input('is_brk_escort_service') ? $request->input('is_brk_escort_service') : 0;
        $is_lunch_escort_service = $request->input('is_lunch_escort_service') ? $request->input('is_lunch_escort_service') : 0;
        $is_dinner_escort_service = $request->input('is_dinner_escort_service') ? $request->input('is_dinner_escort_service') : 0;


        $occupancy = $request->input('occupancy');

        $item_array = $order_array = array();
        if ($room_id != "" && $date != "") {
            if ($request->input('orders_to_change') && $request->input('orders_to_change') != "") {
                $new_data = json_decode($request->input('orders_to_change'));
                foreach (count($new_data) > 0 ? $new_data : array() as $n) {
                    $n->order_id = intval($n->order_id);
                    $n->qty = intval($n->qty);
                    if ($n->order_id == 0) {
                        if ($n->qty != 0) {

                            $order = new OrderDetail();

                            $order->room_id = $room_id;
                            $order->date = $date;
                            $order->item_id = $n->item_id;
                            $order->item_options = $n->item_options;
                            $order->preference = $n->preference;
                            $order->quantity = $n->qty;
                            $order->comment = "";
                            $order->status = 0;

                            $order->is_for_guest = $is_for_guest;
                            $order->is_brk_tray_service = $is_brk_tray_service;
                            $order->is_lunch_tray_service = $is_lunch_tray_service;
                            $order->is_dinner_tray_service = $is_dinner_tray_service;

                            $order->is_brk_escort_service = $is_brk_escort_service;
                            $order->is_lunch_escort_service = $is_lunch_escort_service;
                            $order->is_dinner_escort_service = $is_dinner_escort_service;

                            $order->save();

                            array_push($item_array, $n->item_id);
                            array_push($order_array, $order->id);
                        }
                    } else {
                        if ($n->qty == 0) {
                            OrderDetail::where("id", $n->order_id)->delete();
                            array_push($item_array, $n->item_id);
                            array_push($order_array, 0);
                        } else {
                            OrderDetail::where("id", $n->order_id)->update(['quantity' => $n->qty, 'item_options' => $n->item_options, 'preference' => $n->preference, 'comment' => ""]);
                        }
                    }
                }
            }

            if ($is_for_guest) {

                DateWiseOccupancy::updateOrCreate([
                    'date' => $date,
                    'room_id'   => $room_id,
                ], [
                    'occupancy' => $occupancy,
                ]);
            }


            return $this->sendResultJSON('1', 'success', array('item_id' => $item_array, 'order_id' => $order_array));
        }
    }

    public function getUserData()
    {
        if (!session("user_details")) {
            return $this->sendResultJSON("11", "Unauthorised");
        }
        $user = session("user_details");
        // $role = session("role");
        // print_r($user->role_id);die;
        $roleName = Role::select('name')->where('id', $user->role_id)->get();

        $role = $roleName[0]['name'];

        $last_date = "";
        $menu_data = MenuDetail::select("date")->orderBy("date", "desc")->first();

        $rooms = RoomDetail::where("is_active", 1)->get();
        $rooms_array = array();
        foreach (count($rooms) > 0 ? $rooms : array() as $r) {
            array_push($rooms_array, array("id" => $r->id, "name" => $r->room_name, "occupancy" => $r->occupancy, "resident_name" => $r->resident_name));
        }

        if ($menu_data) {
            $last_date = Carbon::parse($menu_data->date)->format('Y-m-d');
        }

        $settings = DB::table('settings')->get();

        $settingsArray = [];

        foreach ($settings as $setting) {
            $settingsArray[$setting->key] = $setting->value;
        }

        if ($role == "user") {

            $settings = DB::table('settings')->get();

            $settings_array = array();

            foreach (count($settings) > 0 ? $settings : array() as $s) {
                $settings_array[$s->name] = $s->value;
            }

            return $this->sendResultJSON('1', '', array("occupancy" => $user->occupancy, "language" => intval($user->language), "last_menu_date" => $last_date, "role" => $role, 'guideline' => $settings_array['site.app_msg'], 'guideline_cn' => $settings_array['site.app_msg_cn'] != "" ? $settings_array['site.app_msg_cn'] : $settings_array['site.app_msg'], 'rooms' => $rooms_array));
        } else {
            $formTypes = "";
            // if ($role == "admin" || $role == "concierge"){
            $formTypes = FormType::all();
            // }

            $userQuery = "select u.id , r.name as roleName,u.name as userName , u.email from users u left join roles r on u.role_id = r.id where u.role_id IN (1,4,7)";

            $userResults = DB::select($userQuery);

            $userData = [];

            foreach ($userResults as $userResult) {

                $userData[] = [
                    'id' => $userResult->id,
                    'role_name' => $userResult->roleName,
                    'name' => $userResult->userName,
                    'email' => $userResult->email,
                ];
            }

            return $this->sendResultJSON('1', '', array("occupancy" => 0, "language" => 0, "last_menu_date" => $last_date, "role" => $role, 'guideline' => $settingsArray['site.app_msg'], 'guideline_cn' => $settingsArray['site.app_msg_cn'] != "" ? $settingsArray['site.app_msg_cn'] : $settingsArray['site.app_msg'], 'form_types' => $formTypes, 'rooms' => $rooms_array, 'user_list' => $userData, 'user_id' => $user->id));
        }
    }

    public function getRoomData(Request $request)
    {
        $date = $request->input('date');
        $item_id = intval($request->input('item_id'));
        $order_details = array();
        $room_array = array();
        if ($date != "" && $item_id != "") {
            $rooms_data = RoomDetail::all();
            foreach ($rooms_data as $r) {
                $room_array[$r->room_id] = $r->room_name;
            }
            $order_data = OrderDetail::where("date", $date)->where("item_id", $item_id)->get();
            foreach (count($order_data) > 0 ? $order_data : array() as $o) {
                $order_details[$o->room_id] = array("room_id" => $o->room_id, "room_name" => $room_array[$o->room_id]);
            }
            return $this->sendResultJSON('1', '', array('rooms' => array_values($order_details)));
        }
    }

    public function printOrderData(Request $request)
    {

        $date = $request->input('date');
        $room_id = intval($request->input('room_id'));
        $is_for_guest = intval($request->input('is_for_guest'));

        $instruction = "";
        $food_texture = "";
        $resident_name = "";
        $breakfast = $lunch = $dinner = array();

        if ($date != "" && $room_id != "") {

            $order_data = OrderDetail::where("room_id", $room_id)->where("date", $date)->where("is_for_guest", 0)->orderBy("id", "asc")->get();

            if ($is_for_guest) {
                $order_data = OrderDetail::where("room_id", $room_id)->where("date", $date)->where("is_for_guest", 1)->orderBy("id", "asc")->get();
            }

            $preference_details = array();

            $preferences = ItemPreference::all();
            foreach (count($preferences) > 0 ? $preferences : array() as $p) {
                $preference_details[$p->id] = array("name" => $p->pname, "name_cn" => ($p->pname_cn != null ? $p->pname_cn : $p->pname));
            }

            foreach (count($order_data) > 0 ? $order_data : array() as $o) {
                $preference_array = array();
                $option_details = "";
                if (isset($o->itemData) && isset($o->itemData->categoryData)) {
                    $cat_data = $o->itemData->categoryData;
                    $type = intval($cat_data->type);
                    if ($o->item_options != "") {
                        $option_data = ItemOption::select("option_name")->where("id", $o->item_options)->first();
                        if ($option_data) {
                            $option_details = $option_data->option_name;
                        }
                    }


                    if ($o->preference != "") {
                        $c_preferences = explode(",", $o->preference);
                        foreach (count($c_preferences) > 0 ? $c_preferences : array() as $cp) {
                            $cp = intval($cp);
                            if ($preference_details[$cp]) {
                                array_push($preference_array, $preference_details[$cp]['name']);
                            }
                        }
                    }

                    $o->cat_id = intval($o->itemData->categoryData->id);
                    $data = array("category" => (intval($cat_data->parent_id) == 0 ? $cat_data->cat_name : ($cat_data->catParentId ? $cat_data->catParentId->cat_name : "")), "sub_cat" => (intval($cat_data->parent_id) == 0 ? "" : $cat_data->cat_name), "item_name" => $o->itemData->item_name, "quantity" => intval($o->quantity), "options" => $option_details, "preference" => $preference_array);
                    if (!in_array(intval($o->itemData->categoryData->id), [2, 7, 10, 13])) { // LUNCH SOUP , LUNCH DESSERT, DINNER DESSERT , 13 is deleted

                        if ($type == 1) {
                            array_push($breakfast, $data);
                        } else if ($type == 2) {
                            array_push($lunch, $data);
                        } else {
                            array_push($dinner, $data);
                        }
                    }
                }
            }

            if (!$is_for_guest) {
                $spi_data = RoomDetail::selectRaw("special_instrucations,food_texture,resident_name")->where("id", $room_id)->first();
                if ($spi_data)
                    $instruction = $spi_data->special_instrucations;
                // $food_texture = $spi_data->food_texture != null ? $spi_data->food_texture : "";
                $food_texture = $spi_data ? $spi_data->food_texture : "";

                $resident_name = "NA";
                if ($spi_data) {
                    $resident_name = $spi_data->resident_name != null ? $spi_data->resident_name : "NA";
                }
            } else {
                $room_details = RoomDetail::selectRaw("room_name")->where("id", $room_id)->first();
                $resident_name =  $room_details->room_name . " Guest";
            }
        }

        $lastOrder = OrderDetail::where("is_for_guest", $is_for_guest)->where("date", $date)->where("room_id", $room_id)->orderBy('id', 'DESC')->first();

        return $this->sendResultJSON('1', '', array('breakfast' => $breakfast, 'lunch' => $lunch, 'dinner' => $dinner, 'special_instruction' => $instruction, 'food_texture' => $food_texture, 'resident_name' => $resident_name, 'is_brk_tray_service' => $lastOrder ? $lastOrder->is_brk_tray_service : 0, 'is_lunch_tray_service' => $lastOrder ? $lastOrder->is_lunch_tray_service : 0, 'is_dinner_tray_service' => $lastOrder ? $lastOrder->is_dinner_tray_service : 0, 'is_brk_escort_service' => $lastOrder ? $lastOrder->is_brk_escort_service : 0, 'is_lunch_escort_service' => $lastOrder ? $lastOrder->is_lunch_escort_service : 0, 'is_dinner_escort_service' => $lastOrder ? $lastOrder->is_dinner_escort_service : 0));
    }

    public function saveForm(Request $request)
    {
        $userId = null;
        $files = $_FILES;

        try {



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
                            return response()->json(['ResponseCode' => "11", 'ResponseText' => "Unauthorised"], 200);
                        }
                    }
                }
            } else {

                return response()->json(['ResponseCode' => "11", 'ResponseText' => "Unauthorised"], 200);
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
                return $this->sendResultJSON("2", $validator->errors()->first());
            }

            $form_type = $request->input('form_type');
            $form_data = $request->input('data');
            $room_id = $request->input('room_id');

            $uniqueFileName = uniqid() . time() . '.pdf';

            $form = FormResponse::create([
                'form_type_id' => $form_type,
                'form_response' => json_decode($form_data, true),
                'created_by' => $userId,
                // 'created_by' => "1",
                'file_name' => $uniqueFileName,
                'room_id' => $room_id
            ]);

            $imageOnlyAttachments = [];
            $mediaLinks = [];

            foreach ($files as $key => $file) {

                $thumbnailFileName = null;

                if (substr($key, 0, -1) != 'thumbnail') { // remove the trailing 1,2 .....

                    $fileExtension = explode("/", $file['type']);
                    $mediaFileName = uniqid() . time() . '.' . end($fileExtension);
                    Storage::put('public/FormResponses/media/' . $mediaFileName, file_get_contents($file['tmp_name']));
                    $mediaLinks[] = Storage::url('public/FormResponses/media/' . $mediaFileName);

                    if ($fileExtension[0] == 'image') {
                        $imageOnlyAttachments[] = Storage::url('public/FormResponses/media/' . $mediaFileName);
                    }

                    if (array_key_exists("thumbnail" . substr($key, -1), $files) && $fileExtension[0] == 'video') {

                        $originalThumbnailFile = $files["thumbnail" . substr($key, -1)];

                        $thumbnailExtension = explode("/", $originalThumbnailFile['type']);
                        $thumbnailFileName = uniqid() . time() . '.' . end($thumbnailExtension);
                        Storage::put('public/FormResponses/media/thumbnail/' . $thumbnailFileName, file_get_contents($originalThumbnailFile['tmp_name']));
                    }

                    $attachmentCreated = FormMediaAttachments::create([
                        'name' => $mediaFileName,
                        'form_response_id' => $form->id,
                        'type' => $fileExtension[0],
                        'file_extension' => end($fileExtension),
                        'size_in_kb' => ceil($file['size'] / 1024),
                        'thumbnail' => $thumbnailFileName
                    ]);
                }
            }


            $data = [];
            $data['formType'] = FormType::find($form_type)->name;
            $data['data'] =  json_decode($form_data, true);
            $data['images'] = $imageOnlyAttachments;



            $pdf = PDF::loadView('form-template', $data);
            $content = $pdf->download()->getOriginalContent();

            Storage::put('public/FormResponses/' . $uniqueFileName, $content);

            $formData = json_decode($form_data, true);

            if (
                array_key_exists("followUp_issue", $formData)
                || array_key_exists("followUp_findings", $formData)
                || array_key_exists("followUp_action_plan", $formData)
                || array_key_exists("followUp_possible_solutions", $formData)
                || array_key_exists("followUp_examine_result", $formData)
            ) {
                if (
                    $formData["followUp_issue"] ||
                    $formData["followUp_findings"] ||
                    $formData["followUp_action_plan"] ||
                    $formData["followUp_possible_solutions"] ||
                    $formData["followUp_examine_result"]
                ) {
                    $form->is_follow_up_incomplete = 0;
                } else {
                    $form->is_follow_up_incomplete = 1;
                }
            } else {
                $form->is_follow_up_incomplete = 1;
            }

            $form->save();


            return $this->sendResultJSON("1", "Successfully Submitted", array("submitted_form_id" => $form->id, 'form_link' => Storage::url('public/FormResponses/' . $uniqueFileName), 'media_links' => $mediaLinks, 'isFollowUpIncomplete' => $form->is_follow_up_incomplete));
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function sendEmail(Request $request)
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

            $submittedForm = FormResponse::find($generatedFormId);
            $fileName = $submittedForm->file_name;
            $userId = $submittedForm->created_by;
            $formTypeId = $submittedForm->form_type_id;

            $userName = User::find($userId)->name;
            $formType = FormType::find($formTypeId)->name;

            $data["email"] =  $toEmail;
            $data["title"] = $formType . " Submitted By " . $userName;

            $data["body"] = "The User Response can be seen in the Attachment";

            Mail::send('emails.form-response', $data, function ($message) use ($data, $fileName) {

                $message->to($data["email"], $data["email"])
                    ->subject($data["title"])
                    ->attach(public_path() . '/uploads/public/FormResponses/' . $fileName);
            });

            return $this->sendResultJSON("1", "Mailed Successfully");
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function getFormDetails(Request $request)
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

            $submittedForm = FormResponse::find($generatedFormId);

            $attachments = [];
            $data = null;
            $userData = null;

            if ($submittedForm) {
                $data = $submittedForm->form_response;

                if (!empty($submittedForm->follow_up_assigned_to)) {

                    $userQuery = "select u.id , r.name as roleName,u.name as userName , u.email from users u left join roles r on u.role_id = r.id where u.id = " . $submittedForm->follow_up_assigned_to;

                    $userResults = DB::select($userQuery);

                    $userData = null;

                    foreach ($userResults as $userResult) {

                        $userData = [
                            'id' => $userResult->id,
                            'role_name' => $userResult->roleName,
                            'name' => $userResult->userName,
                            'email' => $userResult->email,
                        ];
                    }
                }


                $attachments = FormMediaAttachments::where('form_response_id', $generatedFormId)->orderBy('id', 'DESC')->get();


                return $this->sendResultJSON("1", "Fetched Form Data Successfully", ['form_data' => $data, 'attachments' => $attachments, 'follow_up_user' => $userData]);
            }

            return $this->sendResultJSON("1", "No Form Details Found", ['form_data' => $data, 'attachments' => $attachments, 'follow_up_user' => $userData]);
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
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

    public function editGeneratedFormResponse(Request $request)
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

            $existingFormResponse = FormResponse::find($form_id);

            if (!$existingFormResponse) {
                return $this->sendResultJSON("0", "Form with This Id is not exist");
            }

            if ($existingFormResponse->file_name) {

                Storage::delete('public/FormResponses/' . $existingFormResponse->file_name);
            }



            $existingFormResponse->form_response = json_decode($form_data, true);
            // $existingFormResponse->file_name = $uniqueFileName;

            $formData = json_decode($form_data, true);

            if (
                array_key_exists("followUp_issue", $formData)
                || array_key_exists("followUp_findings", $formData)
                || array_key_exists("followUp_action_plan", $formData)
                || array_key_exists("followUp_possible_solutions", $formData)
                || array_key_exists("followUp_examine_result", $formData)
            ) {
                if (
                    $formData["followUp_issue"] ||
                    $formData["followUp_findings"] ||
                    $formData["followUp_action_plan"] ||
                    $formData["followUp_possible_solutions"] ||
                    $formData["followUp_examine_result"]
                ) {
                    $existingFormResponse->is_follow_up_incomplete = 0;
                } else {
                    $existingFormResponse->is_follow_up_incomplete = 1;
                }
            } else {
                $existingFormResponse->is_follow_up_incomplete = 1;
            }

            $existingFormResponse->save();

            // $formData =  (array)json_decode($form_data,true); 
            // $formDataArray = json_decode($formData[0],true);

            // $data = [];
            // $data['formType'] = FormType::find($existingFormResponse->form_type_id)->name;
            // $data['data'] =json_decode($form_data,true);
            // $data['images'] = [];

            // $pdf = PDF::loadView('form-template', $data);
            // $content = $pdf->download()->getOriginalContent();

            // Storage::put('public/FormResponses/'.$uniqueFileName,$content);

            $newLink = $this->regenerateFormResponse($form_id);




            return $this->sendResultJSON("1", "Successfully Submitted", array('new_form_link' => $newLink, 'isFollowUpIncomplete' => $existingFormResponse->is_follow_up_incomplete));
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function getGeneratedForms(Request $request)
    {

        try {

            $validator = Validator::make($request->all(), [
                "form_type" => "required"
            ], [
                "form_type.required" => "Please enter Form type"
            ]);
            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }

            $criteria = [];

            if ($request->form_type) {
                $criteria['form_type_id'] = $request->form_type;
            }


            $list = FormResponse::where($criteria)->orderBy('created_at', 'DESC')->with('formType')->get();

            return $this->sendResultJSON("1", "List Retrieved Successfully", ['list' => $list]);
        } catch (\Exception $e) {

            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function deleteFormResponse(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "form_id" => "required"
            ], [
                "form_id.required" => "Please enter Form Id"
            ]);
            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }

            FormResponse::where("id", $request->form_id)->delete();
            FormMediaAttachments::where('form_response_id', $request->form_id)->delete();

            return $this->sendResultJSON("1", "Form Response Deleted Successfully");
        } catch (\Exception $e) {

            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function demo(Request $request)
    {
        try {
            $form_data = $request->input('data');
            $orderData = $request->input('orders_to_change');


            $formData =  json_decode($form_data, true);
            // $formDataArray = json_decode($formData,true);

            //  print_r($formData);
            //  die;

            return $this->sendResultJSON("1", "Data Converted Successfully", ['data' =>  $formData]);
        } catch (\Exception $e) {

            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function example()
    {
        return $this->sendResultJSON("1", "Data Converted Successfully");
    }

    public function completeFormLog(Request $request)
    {

        try {

            $validator = Validator::make($request->all(), [
                "form_id" => "required",
                "completed_by" => "required"
            ], [
                "form_id.required" => "Please enter Form Id",
                "completed_by.required" => "Please Provide Completed By Id",
            ]);

            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }

            $formId = $request->get('form_id');
            $completedBy = $request->get('completed_by');


            $formResponse = FormResponse::find($formId);

            $jsonData = $formResponse->form_response;



            if (is_array($jsonData) && $formResponse->form_type_id == 2) {

                if ($jsonData['is_completed'] != 1) {
                    $jsonData['completed_by'] = $completedBy;
                    $jsonData['is_completed'] = 1;
                    $jsonData['completed_at'] = date("Y-m-d H:i:s");

                    $formResponse->form_response = ($jsonData);


                    $uniqueFileName = uniqid() . time() . '.pdf';


                    if ($formResponse->file_name) {

                        Storage::delete('public/FormResponses/' . $formResponse->file_name);
                    }



                    $formResponse->file_name = $uniqueFileName;

                    $formResponse->save();

                    $data = [];
                    // $data['formType'] = FormType::find($formResponse->form_type_id)->name;
                    $data = $jsonData;

                    // $pdf = PDF::loadView('form-template', $data);
                    $pdf = PDF::loadView('temp-form-template', $data);
                    $content = $pdf->download()->getOriginalContent();

                    Storage::put('public/FormResponses/' . $uniqueFileName, $content);

                    return $this->sendResultJSON("1", "Form Complete Logged Successfully", ['jsonData' => $jsonData, 'formLink' =>  Storage::url('public/FormResponses/' . $uniqueFileName)]);
                }
            }

            if (!is_array($jsonData)) {
                $jsonResponse = json_decode($jsonData, true);

                if ($jsonResponse['is_completed'] == 1) {
                    return $this->sendResultJSON("0", "Form Is Already Completed !");
                }
            }
        } catch (\Exception $e) {

            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function getCategoryWiseDataDemo(Request $request)
    {

        // quantity and items from this

        $date = $request->input('date');
        $menu_details = MenuDetail::where("date", $date)->first(); // merge the is_allday data with this also

        $breakfast = $lunch = $dinner = array();
        $breakfast_rooms_array = $lunch_rooms_array = $dinner_rooms_array = array();
        $rooms_array = array();
        $cat_id = array(
            1 => 'BA',
            2 => 'LS',
            7 => 'LD',
            13 => 'DD',
        );
        $alternative = array(4, 8, 11);
        $ab_alternative = array(5, 3);

        if ($menu_details) {

            $menu_items = json_decode($menu_details->items, true);
            $all_rooms = RoomDetail::where("is_active", 1)->get();
            $is_first = true;

            foreach (count($all_rooms) > 0 ? $all_rooms : array() as $r) {

                $wereGuestAvailable = false;

                $isOccupiedByGuest = DateWiseOccupancy::select('occupancy')->where('room_id',  $r->id)->where('date', $date)->first();

                if ($isOccupiedByGuest) {
                    if ($isOccupiedByGuest->occupancy) {
                        $wereGuestAvailable = true;
                    }
                }

                if ($menu_items["breakfast"]) {

                    $all_items = ItemDetail::selectRaw("id,item_name,cat_id")->whereRaw("id IN (" . implode(",", $menu_items["breakfast"]) . ")")->orderBy("cat_id")->get();
                    $count = 1;
                    $items = array();
                    $guestItems = array();

                    if (!isset($breakfast_rooms_array[$r->id]))
                        $breakfast_rooms_array[$r->id] = array("room_no" => $r->room_name, "quantity" => array());

                    foreach (count($all_items) > 0 ? $all_items : array() as $a) {

                        $title = (in_array($a->cat_id, $alternative) ? "B" . $count : $cat_id[$a->cat_id]);
                        if (!isset($breakfast[$a->id]))
                            $breakfast[$a->id] = array();

                        if ($is_first) {
                            $breakfast[$a->id] = array("item_name" => $title, "real_item_name" => $a->item_name, "total_count" => 0);
                        }

                        $order_data = OrderDetail::select("quantity")->where("date", $date)->where("room_id", $r->id)->where("item_id", $a->id)->where("is_for_guest", 0)->first();

                        if ($order_data) {

                            $breakfast[$a->id]["total_count"] += intval($order_data->quantity);
                            array_push($items, intval($order_data->quantity));
                        } else {
                            array_push($items, 0);
                        }

                        if ($wereGuestAvailable) {

                            $guest_order_data = OrderDetail::select("quantity")->where("date", $date)->where("room_id", $r->id)->where("item_id", $a->id)->where("is_for_guest", 1)->first();

                            if ($guest_order_data) {
                                array_push($guestItems, intval($guest_order_data->quantity));
                                $breakfast[$a->id]["total_count"] += intval($guest_order_data->quantity);
                            } else {
                                array_push($guestItems, 0);
                            }
                        } else {
                            array_push($guestItems, 0);
                        }

                        if (in_array($a->cat_id, $alternative)) $count++;
                    }



                    $breakfast_rooms_array[$r->id]["quantity"] = $items;

                    if ($wereGuestAvailable) {
                        $guestRoomName = $r->room_name . " G";

                        $breakfast_rooms_array[$guestRoomName] = [
                            "room_no" => $guestRoomName,
                            "quantity" => $guestItems
                        ];
                    }
                }

                if ($menu_items["lunch"]) {

                    $all_items = ItemDetail::selectRaw("id,item_name,cat_id")->whereRaw("id IN (" . implode(",", $menu_items["lunch"]) . ")")->orderBy("cat_id")->get();
                    $ab_count = 'A';
                    $count = 1;
                    $items = array();
                    $guestItems = array();

                    if (!isset($lunch_rooms_array[$r->id]))
                        $lunch_rooms_array[$r->id] = array("room_no" => $r->room_name, "quantity" => array());

                    foreach (count($all_items) > 0 ? $all_items : array() as $a) {

                        $title = (in_array($a->cat_id, $alternative) ? "L" . $count : (in_array($a->cat_id, $ab_alternative) ? "L" . $ab_count : $cat_id[$a->cat_id]));
                        if (!isset($lunch[$a->id]))
                            $lunch[$a->id] = array();

                        if ($is_first) {
                            $lunch[$a->id] = array("item_name" => $title, "real_item_name" => $a->item_name, "total_count" => 0);
                        }

                        $order_data = OrderDetail::select("quantity")->where("date", $date)->where("room_id", $r->id)->where("item_id", $a->id)->where("is_for_guest", 0)->first();

                        if ($order_data) {
                            $lunch[$a->id]["total_count"] += intval($order_data->quantity);
                            array_push($items, intval($order_data->quantity));
                        } else {
                            array_push($items, 0);
                        }

                        if ($wereGuestAvailable) {

                            $guest_order_data = OrderDetail::select("quantity")->where("date", $date)->where("room_id", $r->id)->where("item_id", $a->id)->where("is_for_guest", 1)->first();

                            if ($guest_order_data) {
                                array_push($guestItems, intval($guest_order_data->quantity));
                                $lunch[$a->id]["total_count"] += intval($guest_order_data->quantity);
                            } else {
                                array_push($guestItems, 0);
                            }
                        } else {
                            array_push($guestItems, 0);
                        }

                        if (in_array($a->cat_id, $alternative)) $count++;
                        if (in_array($a->cat_id, $ab_alternative)) $ab_count = 'B';
                    }
                    $lunch_rooms_array[$r->id]["quantity"] = $items;

                    if ($wereGuestAvailable) {
                        $guestRoomName = $r->room_name . " G";

                        $lunch_rooms_array[$guestRoomName] = [
                            "room_no" => $guestRoomName,
                            "quantity" => $guestItems
                        ];
                    }
                }

                if ($menu_items["dinner"]) {

                    $all_items = ItemDetail::selectRaw("id,item_name,cat_id")->whereRaw("id IN (" . implode(",", $menu_items["dinner"]) . ")")->orderBy("cat_id")->get();
                    $count = 1;
                    $ab_count = 'A';
                    $items = array();
                    $guestItems = array();

                    if (!isset($dinner_rooms_array[$r->id]))
                        $dinner_rooms_array[$r->id] = array("room_no" => $r->room_name, "quantity" => array());
                    foreach (count($all_items) > 0 ? $all_items : array() as $a) {
                        $title = (in_array($a->cat_id, $alternative) ? "D" . $count : (in_array($a->cat_id, $ab_alternative) ? "D" . $ab_count : $cat_id[$a->cat_id]));
                        if (!isset($dinner[$a->id]))
                            $dinner[$a->id] = array();

                        if ($is_first) {
                            $dinner[$a->id] = array("item_name" => $title, "real_item_name" => $a->item_name, "total_count" => 0);
                        }
                        $order_data = OrderDetail::select("quantity")->where("date", $date)->where("room_id", $r->id)->where("item_id", $a->id)->where("is_for_guest", 0)->first();

                        if ($order_data) {
                            $dinner[$a->id]["total_count"] += intval($order_data->quantity);
                            array_push($items, intval($order_data->quantity));
                        } else {
                            array_push($items, 0);
                        }

                        if ($wereGuestAvailable) {

                            $guest_order_data = OrderDetail::select("quantity")->where("date", $date)->where("room_id", $r->id)->where("item_id", $a->id)->where("is_for_guest", 1)->first();

                            if ($guest_order_data) {
                                array_push($guestItems, intval($guest_order_data->quantity));
                                $dinner[$a->id]["total_count"] += intval($guest_order_data->quantity);
                            } else {
                                array_push($guestItems, 0);
                            }
                        } else {
                            array_push($guestItems, 0);
                        }

                        if (in_array($a->cat_id, $alternative)) $count++;
                        if (in_array($a->cat_id, $ab_alternative)) $ab_count = 'B';
                    }
                    $dinner_rooms_array[$r->id]["quantity"] = $items;

                    if ($wereGuestAvailable) {
                        $guestRoomName = $r->room_name . " G";

                        $dinner_rooms_array[$guestRoomName] = [
                            "room_no" => $guestRoomName,
                            "quantity" => $guestItems
                        ];
                    }
                }

                $is_first = false;

                array_push($rooms_array, array("room_id" => $r->id, "room_name" => $r->room_name, "has_special_ins" => ($r->special_instrucations != null ? 1 : 0), "has_breakfast_order" => (count($breakfast_rooms_array) ? (array_sum($breakfast_rooms_array[$r->id]["quantity"]) > 0 ? 1 : 0) : 0), "has_lunch_order" => (count($lunch_rooms_array) ?  (array_sum($lunch_rooms_array[$r->id]["quantity"]) > 0 ? 1 : 0) : 0), "has_dinner_order" => (count($lunch_rooms_array) ? (array_sum($dinner_rooms_array[$r->id]["quantity"]) > 0 ? 1 : 0) : 0), "is_for_guest" => 0));

                if ($isOccupiedByGuest) {
                    if ($isOccupiedByGuest->occupancy) {

                        $roomName = $r->room_name . " G";

                        array_push($rooms_array, array("room_id" => $r->id, "room_name" => $roomName, "has_special_ins" => 0, "has_breakfast_order" => (count($breakfast_rooms_array) ? (array_sum($breakfast_rooms_array[$roomName]["quantity"]) > 0 ? 1 : 0) : 0), "has_lunch_order" => (count($lunch_rooms_array) ?  (array_sum($lunch_rooms_array[$roomName]["quantity"]) > 0 ? 1 : 0) : 0), "has_dinner_order" => (count($lunch_rooms_array) ? (array_sum($dinner_rooms_array[$roomName]["quantity"]) > 0 ? 1 : 0) : 0), "is_for_guest" => 1));
                    }
                }
            }
        }

        $last_date = "";
        $menu_data = MenuDetail::select("date")->orderBy("date", "desc")->first();
        if ($menu_data) {
            $last_date = $menu_data->date;
        }

        if (!$menu_details) {
            return $this->sendResultJSON('1', 'Menu Details not Found!!', array('breakfast_item_list' => array_values($breakfast), 'lunch_item_list' => array_values($lunch), 'dinner_item_list' => array_values($dinner), 'report_breakfast_list' => array_values($breakfast_rooms_array), 'report_lunch_list' => array_values($lunch_rooms_array), 'report_dinner_list' => array_values($dinner_rooms_array), 'rooms_list' => $rooms_array, "last_menu_date" => $last_date));
        }

        return $this->sendResultJSON('1', '', array('breakfast_item_list' => array_values($breakfast), 'lunch_item_list' => array_values($lunch), 'dinner_item_list' => array_values($dinner), 'report_breakfast_list' => array_values($breakfast_rooms_array), 'report_lunch_list' => array_values($lunch_rooms_array), 'report_dinner_list' => array_values($dinner_rooms_array), 'rooms_list' => $rooms_array, "last_menu_date" => $last_date));
    }

    public function getDemoOrderList(Request $request)
    {
        if (!session("user_details")) {
            return $this->sendResultJSON("11", "Unauthorised");
        }

        $room_id = intval($request->input('room_id'));

        $date = $request->input('date');
        $sub_cat_details = array();
        $cat_array = array();
        $breakfast = $lunch = $dinner = array();

        // $items = "";
        // $day = Carbon::parse($date)->format("l");
        // if ($day == "Sunday") {
        //     $items = "1,4,5,6,7,20,28,38,15,18,3,52,17,16";
        // } elseif ($day == "Monday") {
        //     $items = "9,4,5,6,7,21,29,39,15,17,18,46,53,16";
        // } elseif ($day == "Tuesday") {
        //     $items = "4,5,6,7,15,17,18,16,10,22,31,41,47,54";
        // } elseif ($day == "Wednesday") {
        //     $items = "4,5,6,7,15,17,18,16,11,23,32,42,48,55";
        // } elseif ($day == "Thursday") {
        //     $items = "4, 5, 6, 7, 15, 17, 18, 16, 12, 24, 34, 43, 49, 56";
        // } elseif ($day == "Friday") {
        //     $items = "4, 5, 6, 7, 15, 17, 18, 16, 13, 25, 36, 44, 50, 57";
        // } elseif ($day == "Saturday") {
        //     $items = "4, 5, 6, 7, 15, 17, 18, 16, 14, 27, 37, 45, 51, 58";
        // }

        $items = array();

        // if menu is not present then return empry menu

        // $menu_data = MenuDetail::selectRaw("items")->whereRaw("date = '" . $date . "' OR is_allDay = 1")->get();
        $menu_data = MenuDetail::selectRaw("items")->whereRaw("date = '" . $date . "' ")->get();
        foreach (count($menu_data) > 0 ? $menu_data : array() as $m) {
            $menu_items = json_decode($m->items, true);
            foreach (count($menu_items) > 0 ? $menu_items : array() as $mi) {
                if (count($mi) > 0)
                    array_push($items, implode(",", $mi));
            }
        }
        $option_details = $preference_details = array();
        $items = implode(",", $items);
        if ($items != "") {
            $options = ItemOption::all();
            foreach (count($options) > 0 ? $options : array() as $o) {
                $option_details[intval($o->id)] = array("option_name" => $o->option_name, "option_name_cn" => ($o->option_name_cn != null ? $o->option_name_cn : $o->option_name));
            }
            $preferences = ItemPreference::all();
            foreach (count($preferences) > 0 ? $preferences : array() as $p) {
                $preference_details[$p->id] = array("name" => $p->pname, "name_cn" => ($p->pname_cn != null ? $p->pname_cn : $p->pname));
            }


            $category_data = CategoryDetail::join("item_details", "item_details.cat_id", "=", "category_details.id")->selectRaw("category_details.*,item_details.id as item_id,item_details.item_name,item_details.item_image,item_details.item_chinese_name,item_details.options,item_details.preference")->where("category_details.parent_id", 0)->whereRaw("item_details.id IN (" . $items . ")")->whereRaw("item_details.deleted_at IS NULL")->orderBy("category_details.id", "asc")->orderBy("item_details.id", "asc")->get();

            foreach (count($category_data) > 0 ? $category_data : array() as $c) {
                if (!isset($cat_array[$c->id])) {
                    $cat_array[$c->id] = array("cat_id" => $c->id, "cat_name" => $c->cat_name, "chinese_name" => $c->category_chinese_name, "items" => array(), "type" => $c->type);
                }
                $options = array();

                $preference = array();

                if ($room_id != 0) {
                    $order_data = OrderDetail::selectRaw("id,quantity,item_options,preference")->where("room_id", $room_id)->where("date", $date)->where("item_id", $c->item_id)->first();

                    if ($c->options != "") {
                        $c_options = json_decode($c->options);
                        foreach (count($c_options) > 0 ? $c_options : array() as $co) {
                            $co = intval($co);
                            if ($option_details[$co]) {
                                $options[$co] = array("id" => $co, "name" => $option_details[$co]['option_name'], "c_name" => $option_details[$co]['option_name_cn'], "is_selected" => ($order_data && $order_data->item_options != null ? ($co == $order_data->item_options ? 1 : 0) : 0));
                            }
                        }
                    }

                    if ($c->preference != "") {
                        $c_preferences = json_decode($c->preference);
                        foreach (count($c_preferences) > 0 ? $c_preferences : array() as $cp) {
                            $cp = intval($cp);
                            if ($preference_details[$cp]) {
                                $preference[$cp] = array("id" => $cp, "name" => $preference_details[$cp]['name'], "c_name" => $preference_details[$cp]['name_cn'], "is_selected" => ($order_data && $order_data->preference != null ? (in_array($cp, explode(",", $order_data->preference)) ? 1 : 0) : 0));
                            }
                        }
                    }
                    array_push($cat_array[$c->id]["items"], array("type" => "item", "item_id" => $c->item_id, "item_name" => $c->item_name, "chinese_name" => $c->item_chinese_name, "options" => array_values($options), "preference" => array_values($preference), "item_image" => !empty($c->item_image) ? Storage::url($c->item_image) : NULL, "qty" => ($order_data ? $order_data->quantity : 0), "comment" => "", "order_id" => ($order_data ? $order_data->id : 0)));
                } else {
                    $order_data = OrderDetail::selectRaw("sum(quantity) as quantity")->where("date", $date)->where("item_id", $c->item_id)->groupBy("item_id")->first();

                    if ($c->options != "") {
                        $c_options = json_decode($c->options);
                        foreach (count($c_options) > 0 ? $c_options : array() as $co) {
                            $co = intval($co);
                            if ($option_details[$co]) {
                                $options[$co] = array("id" => $co, "name" => $option_details[$co]['option_name'], "c_name" => $option_details[$co]['option_name_cn'], "is_selected" => 0, "item_count" => OrderDetail::where("date", $date)->where("item_id", $c->item_id)->where("item_options", $co)->count());
                            }
                        }
                    }

                    array_push($cat_array[$c->id]["items"], array("type" => "item", "item_id" => $c->item_id, "item_name" => $c->item_name, "chinese_name" => $c->item_chinese_name, "is_expanded" => count(array_values($options)) > 0 ? 1 : 0, "options" => array_values($options), "preference" => array_values($preference), "item_image" => !empty($c->item_image) ? Storage::url($c->item_image) : NULL, "qty" => ($order_data ? intval($order_data->quantity) : 0), "comment" => "", "order_id" => 0));
                }
            }
            $sub_category_data = CategoryDetail::join("item_details", "item_details.cat_id", "=", "category_details.id")->selectRaw("category_details.*,item_details.id as item_id,item_details.item_name,item_details.item_image,item_details.item_chinese_name,item_details.options,item_details.preference")->where("category_details.parent_id", "!=", 0)->whereRaw("item_details.id IN (" . $items . ")")->whereRaw("item_details.deleted_at IS NULL")->orderBy("category_details.id", "asc")->orderBy("item_details.id", "asc")->get();
            foreach (count($sub_category_data) > 0 ? $sub_category_data : array() as $sc) {
                if (!isset($sub_cat_details[$sc->id])) {
                    $sub_cat_details[$sc->id] = array("cat_id" => $sc->id, "cat_name" => $sc->cat_name, "chinese_name" => $sc->category_chinese_name, "parent_id" => $sc->parent_id, "items" => array());
                }
                if (!isset($cat_array[$sc->parent_id])) {
                    if ($sc->parentData) {
                        $cat_array[$sc->parent_id] = array("cat_id" => $sc->parentData->id, "cat_name" => $sc->parentData->cat_name, "chinese_name" => $sc->parentData->category_chinese_name, "items" => array(), "type" => $c->type);
                    }
                }
                $options = array();

                $preference = array();

                if ($room_id != 0) {
                    $order_data = OrderDetail::selectRaw("id,quantity,item_options,preference")->where("room_id", $room_id)->where("date", $date)->where("item_id", $sc->item_id)->first();

                    if ($sc->options != "") {
                        $c_options = json_decode($sc->options);
                        foreach (count($c_options) > 0 ? $c_options : array() as $co) {
                            $co = intval($co);
                            if ($option_details[$co]) {
                                $options[$co] = array("id" => $co, "name" => $option_details[$co]['option_name'], "c_name" => $option_details[$co]['option_name_cn'], "is_selected" => ($order_data && $order_data->item_options != null ? ($co == $order_data->item_options ? 1 : 0) : 0));
                            }
                        }
                    }

                    if ($sc->preference != "") {
                        $c_preferences = json_decode($sc->preference);
                        foreach (count($c_preferences) > 0 ? $c_preferences : array() as $cp) {
                            $cp = intval($cp);
                            if ($preference_details[$cp]) {
                                $preference[$cp] = array("id" => $cp, "name" => $preference_details[$cp]['name'], "c_name" => $preference_details[$cp]['name_cn'], "is_selected" => ($order_data && $order_data->preference != null ? (in_array($cp, explode(",", $order_data->preference)) ? 1 : 0) : 0));
                            }
                        }
                    }

                    array_push($sub_cat_details[$sc->id]["items"], array("item_id" => $sc->item_id, "item_name" => $sc->item_name, "chinese_name" => $sc->item_chinese_name, "item_image" => !empty($sc->item_image) ? Storage::url($sc->item_image) : NULL, "options" => array_values($options), "preference" => array_values($preference), "qty" => ($order_data ? $order_data->quantity : 0), "comment" => "", "order_id" => ($order_data ? $order_data->id : 0)));
                } else {
                    $order_data = OrderDetail::selectRaw("sum(quantity) as quantity")->where("date", $date)->where("item_id", $sc->item_id)->groupBy("item_id")->first();

                    if ($sc->options != "") {
                        $c_options = json_decode($sc->options);
                        foreach (count($c_options) > 0 ? $c_options : array() as $co) {
                            $co = intval($co);
                            if ($option_details[$co]) {
                                $options[$co] = array("id" => $co, "name" => $option_details[$co]['option_name'], "c_name" => $option_details[$co]['option_name_cn'], "is_selected" => 0, "item_count" => OrderDetail::where("date", $date)->where("item_id", $sc->item_id)->where("item_options", $co)->count());
                            }
                        }
                    }

                    array_push($sub_cat_details[$sc->id]["items"], array("item_id" => $sc->item_id, "item_name" => $sc->item_name, "chinese_name" => $sc->item_chinese_name, "item_image" => !empty($sc->item_image) ? Storage::url($sc->item_image) : NULL, "is_expanded" => count(array_values($options)) > 0 ? 1 : 0, "options" => array_values($options), "preference" => array_values($preference), "qty" => ($order_data ? intval($order_data->quantity) : 0), "comment" => "", "order_id" => ($order_data ? $order_data->id : 0)));
                }
            }
            foreach (count($sub_cat_details) > 0 ? $sub_cat_details : array() as $sc) {
                if (isset($cat_array[$sc['parent_id']])) {
                    array_push($cat_array[$sc['parent_id']]["items"], array("type" => "sub_cat", "item_id" => $sc["cat_id"], "item_name" => $sc["cat_name"], "chinese_name" => $sc["chinese_name"], "options" => [], "preference" => [], "item_image" => "", "qty" => 0, "comment" => "", "order_id" => 0));
                    foreach (count($sc["items"]) > 0 ? $sc["items"] : array() as $sci) {
                        $sc_item = array("type" => "sub_cat_item", "item_id" => $sci["item_id"], "item_name" => $sci["item_name"], "chinese_name" => $sci["chinese_name"], "item_image" => $sci["item_image"], "options" => $sci["options"], "preference" => $sci["preference"], "qty" => $sci["qty"], "comment" => $sci["comment"], "order_id" => $sci["order_id"]);
                        if (isset($sci["is_expanded"])) {
                            $sc_item["is_expanded"] = $sci["is_expanded"];
                        }
                        array_push($cat_array[$sc['parent_id']]["items"], $sc_item);
                    }
                    //, "items" => array_values($sc["items"]
                }
            }
        }
        foreach (count($cat_array) > 0 ? $cat_array : array() as $c) {
            $type = intval($c['type']);
            unset($c['type']);
            if ($type == 1) {
                array_push($breakfast, $c);
            } else if ($type == 2) {
                array_push($lunch, $c);
            } else if ($type == 3) {
                array_push($dinner, $c);
            }
        }

        $last_date = "";
        $menu_data = MenuDetail::select("date")->orderBy("date", "desc")->first();
        if ($menu_data) {
            $last_date = $menu_data->date;
        }

        $instruction = "";
        $spi_data = RoomDetail::select("special_instrucations")->where("id", $room_id)->first();
        if ($spi_data)
            $instruction = $spi_data->special_instrucations;

        return $this->sendResultJSON('1', '', array('breakfast' => $breakfast, 'lunch' => $lunch, 'dinner' => $dinner, 'last_menu_date' => $last_date, 'special_instruction' => $instruction));
    }

    public function saveForm1(Request $request)
    {
        $userId = null;
        $files = $_FILES;

        print_r($files);
        die;
        try {

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
                            return response()->json(['ResponseCode' => "11", 'ResponseText' => "Unauthorised"], 200);
                        }
                    }
                }
            } else {

                return response()->json(['ResponseCode' => "11", 'ResponseText' => "Unauthorised"], 200);
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
                return $this->sendResultJSON("2", $validator->errors()->first());
            }

            $form_type = $request->input('form_type');
            $form_data = $request->input('data');
            $room_id = $request->input('room_id');


            $uniqueFileName = uniqid() . time() . '.pdf';

            $form = FormResponse::create([
                'form_type_id' => $form_type,
                'form_response' => json_decode($form_data, true),
                'created_by' => $userId,
                'file_name' => $uniqueFileName,
                'room_id' => $room_id
            ]);

            $imageOnlyAttachments = [];
            $mediaLinks = [];

            foreach ($files as $file) {
                $fileExtension = explode("/", $file['type']);
                $mediaFileName = uniqid() . time() . '.' . end($fileExtension);
                Storage::put('public/FormResponses/media/' . $mediaFileName, file_get_contents($file['tmp_name']));
                $mediaLinks[] = Storage::url('public/FormResponses/media/' . $mediaFileName);

                if ($fileExtension[0] == 'image') {
                    $imageOnlyAttachments[] = Storage::url('public/FormResponses/media/' . $mediaFileName);
                }

                FormMediaAttachments::create([
                    'name' => $mediaFileName,
                    'form_response_id' => $form->id,
                    'type' => $$fileExtension[0]
                ]);
            }


            $data = [];
            $data['formType'] = FormType::find($form_type)->name;
            $data['data'] =  json_decode($form_data, true);
            $data['images'] = $imageOnlyAttachments;


            $pdf = PDF::loadView('form-template', $data);
            $content = $pdf->download()->getOriginalContent();

            Storage::put('public/FormResponses/' . $uniqueFileName, $content);


            return $this->sendResultJSON("1", "Successfully Submitted", array("submitted_form_id" => $form->id, 'form_link' => Storage::url('public/FormResponses/' . $uniqueFileName), 'media_links' => $mediaLinks));
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function deleteFormAttachment(Request $request)
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

            FormMediaAttachments::where(['id' => $attachmentId, 'form_response_id' => $formId])->delete();

            $attachments = FormMediaAttachments::where('form_response_id', $formId)->orderBy('id', 'DESC')->get();

            $newLink = $this->regenerateFormResponse($formId);

            return $this->sendResultJSON("1", "Attachment Deleted Successfully", array("newLink" => $newLink, "attachments" => $attachments));
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function addAttachmentsToExistingForm(Request $request)
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


            foreach ($files as $key => $file) {

                $thumbnailFileName = null;


                if (substr($key, 0, -1) != 'thumbnail') { // remove the trailing 1,2 .....

                    $fileExtension = explode("/", $file['type']);
                    $mediaFileName = uniqid() . time() . '.' . end($fileExtension);
                    Storage::put('public/FormResponses/media/' . $mediaFileName, file_get_contents($file['tmp_name']));
                    $mediaLinks[] = Storage::url('public/FormResponses/media/' . $mediaFileName);

                    if ($fileExtension[0] == 'image') {
                        $imageOnlyAttachments[] = Storage::url('public/FormResponses/media/' . $mediaFileName);
                    }

                    if (array_key_exists("thumbnail" . substr($key, -1), $files) && $fileExtension[0] == 'video') {

                        $originalThumbnailFile = $files["thumbnail" . substr($key, -1)];

                        $thumbnailExtension = explode("/", $originalThumbnailFile['type']);
                        $thumbnailFileName = uniqid() . time() . '.' . end($thumbnailExtension);
                        Storage::put('public/FormResponses/media/thumbnail/' . $thumbnailFileName, file_get_contents($originalThumbnailFile['tmp_name']));
                    }

                    $attachmentCreated = FormMediaAttachments::create([
                        'name' => $mediaFileName,
                        'form_response_id' => $formId,
                        'type' => $fileExtension[0],
                        'file_extension' => end($fileExtension),
                        'size_in_kb' => ceil($file['size'] / 1024),
                        'thumbnail' => $thumbnailFileName
                    ]);
                }
            }

            $results = FormMediaAttachments::where([
                'form_response_id' => $formId,
            ])->orderBy('id', 'DESC')->get();

            $attachments = [];

            foreach ($results as $attachment) {
                $attachments[] = $attachment;
            }

            $newLink = $this->regenerateFormResponse($formId);

            return $this->sendResultJSON("1", "Attachments Uploaded Successfully", array("new_form_link" => $newLink, "attachments" => $attachments));
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function regenerateFormResponse($formId)
    {

        $uniqueFileName = uniqid() . time() . '.pdf';

        $existingFormResponse = FormResponse::find($formId);

        if ($existingFormResponse->file_name) {

            Storage::delete('public/FormResponses/' . $existingFormResponse->file_name);
        }

        $existingFormResponse->file_name = $uniqueFileName;

        $existingFormResponse->save();

        // $formData =  (array)json_decode($form_data,true); 
        // $formDataArray = json_decode($formData[0],true);

        $results = FormMediaAttachments::where([
            'form_response_id' => $formId,
            'type' => 'image'
        ])->get();

        $images = [];

        foreach ($results as $attachment) {
            $images[] = Storage::url('public/FormResponses/media/' . $attachment['name']);
        }

        $data = [];

        $data['formType'] = FormType::find($existingFormResponse->form_type_id)->name;
        $data['data'] = $existingFormResponse->form_response;
        $data['images'] = $images;

        $pdf = PDF::loadView('form-template', $data);
        $content = $pdf->download()->getOriginalContent();

        Storage::put('public/FormResponses/' . $uniqueFileName, $content);

        return Storage::url('public/FormResponses/' . $uniqueFileName);
    }



    public function getGuestOrderList(Request $request) // same as getorderlist
    {
        if (!session("user_details")) {
            return $this->sendResultJSON("11", "Unauthorised");
        }

        $room_id = intval($request->input('room_id'));

        $date = $request->input('date');
        $sub_cat_details = array();
        $cat_array = array();
        $breakfast = $lunch = $dinner = array();

        $items = array();

        $menu_data = MenuDetail::selectRaw("items")->whereRaw("date = '" . $date . "'")->get();

        foreach (count($menu_data) > 0 ? $menu_data : array() as $m) {
            $menu_items = json_decode($m->items, true);
            foreach (count($menu_items) > 0 ? $menu_items : array() as $mi) {
                if (count($mi) > 0)
                    array_push($items, implode(",", $mi));
            }
        }
        $option_details = $preference_details = array();
        $items = implode(",", $items);
        if ($items != "") {
            $options = ItemOption::all();
            foreach (count($options) > 0 ? $options : array() as $o) {
                $option_details[intval($o->id)] = array("option_name" => $o->option_name, "option_name_cn" => ($o->option_name_cn != null ? $o->option_name_cn : $o->option_name));
            }
            $preferences = ItemPreference::all();
            foreach (count($preferences) > 0 ? $preferences : array() as $p) {
                $preference_details[$p->id] = array("name" => $p->pname, "name_cn" => ($p->pname_cn != null ? $p->pname_cn : $p->pname));
            }


            $category_data = CategoryDetail::join("item_details", "item_details.cat_id", "=", "category_details.id")->selectRaw("category_details.*,item_details.id as item_id,item_details.item_name,item_details.item_image,item_details.item_chinese_name,item_details.options,item_details.preference")->where("category_details.parent_id", 0)->whereRaw("item_details.id IN (" . $items . ")")->whereRaw("item_details.deleted_at IS NULL")->orderBy("category_details.id", "asc")->orderBy("item_details.id", "asc")->get();

            foreach (count($category_data) > 0 ? $category_data : array() as $c) {
                if (!isset($cat_array[$c->id])) {
                    $cat_array[$c->id] = array("cat_id" => $c->id, "cat_name" => $c->cat_name, "chinese_name" => $c->category_chinese_name, "items" => array(), "type" => $c->type);
                }
                $options = array();

                $preference = array();

                if ($room_id != 0) {
                    $order_data = OrderDetail::selectRaw("id,quantity,item_options,preference,is_for_guest")->where("room_id", $room_id)->where("date", $date)->where("item_id", $c->item_id)->where("is_for_guest", 1)->first();

                    if ($c->options != "") {
                        $c_options = json_decode($c->options);
                        foreach (count($c_options) > 0 ? $c_options : array() as $co) {
                            $co = intval($co);
                            if ($option_details[$co]) {
                                $options[$co] = array("id" => $co, "name" => $option_details[$co]['option_name'], "c_name" => $option_details[$co]['option_name_cn'], "is_selected" => ($order_data && $order_data->item_options != null ? ($co == $order_data->item_options ? 1 : 0) : 0));
                            }
                        }
                    }

                    if ($c->preference != "") {
                        $c_preferences = json_decode($c->preference);
                        foreach (count($c_preferences) > 0 ? $c_preferences : array() as $cp) {
                            $cp = intval($cp);
                            if ($preference_details[$cp]) {
                                $preference[$cp] = array("id" => $cp, "name" => $preference_details[$cp]['name'], "c_name" => $preference_details[$cp]['name_cn'], "is_selected" => ($order_data && $order_data->preference != null ? (in_array($cp, explode(",", $order_data->preference)) ? 1 : 0) : 0));
                            }
                        }
                    }
                    array_push($cat_array[$c->id]["items"], array("type" => "item", "item_id" => $c->item_id, "item_name" => $c->item_name, "chinese_name" => $c->item_chinese_name, "options" => array_values($options), "preference" => array_values($preference), "item_image" => !empty($c->item_image) ? Storage::url($c->item_image) : NULL, "qty" => ($order_data ? ($order_data->is_for_guest ? $order_data->quantity : 0) : 0), "comment" => "", "order_id" => ($order_data ? $order_data->id : 0)));
                } else {
                    $order_data = OrderDetail::selectRaw("sum(quantity) as quantity")->where("date", $date)->where("item_id", $c->item_id)->groupBy("item_id")->first();

                    if ($c->options != "") {
                        $c_options = json_decode($c->options);
                        foreach (count($c_options) > 0 ? $c_options : array() as $co) {
                            $co = intval($co);
                            if ($option_details[$co]) {
                                $options[$co] = array("id" => $co, "name" => $option_details[$co]['option_name'], "c_name" => $option_details[$co]['option_name_cn'], "is_selected" => 0, "item_count" => OrderDetail::where("date", $date)->where("item_id", $c->item_id)->where("item_options", $co)->count());
                            }
                        }
                    }

                    array_push($cat_array[$c->id]["items"], array("type" => "item", "item_id" => $c->item_id, "item_name" => $c->item_name, "chinese_name" => $c->item_chinese_name, "is_expanded" => count(array_values($options)) > 0 ? 1 : 0, "options" => array_values($options), "preference" => array_values($preference), "item_image" => !empty($c->item_image) ? Storage::url($c->item_image) : NULL, "qty" => ($order_data ? intval($order_data->quantity) : 0), "comment" => "", "order_id" => 0));
                }
            }
            $sub_category_data = CategoryDetail::join("item_details", "item_details.cat_id", "=", "category_details.id")->selectRaw("category_details.*,item_details.id as item_id,item_details.item_name,item_details.item_image,item_details.item_chinese_name,item_details.options,item_details.preference")->where("category_details.parent_id", "!=", 0)->whereRaw("item_details.id IN (" . $items . ")")->whereRaw("item_details.deleted_at IS NULL")->orderBy("category_details.id", "asc")->orderBy("item_details.id", "asc")->get();
            foreach (count($sub_category_data) > 0 ? $sub_category_data : array() as $sc) {
                if (!isset($sub_cat_details[$sc->id])) {
                    $sub_cat_details[$sc->id] = array("cat_id" => $sc->id, "cat_name" => $sc->cat_name, "chinese_name" => $sc->category_chinese_name, "parent_id" => $sc->parent_id, "items" => array());
                }
                if (!isset($cat_array[$sc->parent_id])) {
                    if ($sc->parentData) {
                        $cat_array[$sc->parent_id] = array("cat_id" => $sc->parentData->id, "cat_name" => $sc->parentData->cat_name, "chinese_name" => $sc->parentData->category_chinese_name, "items" => array(), "type" => $c->type);
                    }
                }
                $options = array();

                $preference = array();

                if ($room_id != 0) {
                    $order_data = OrderDetail::selectRaw("id,quantity,item_options,preference,is_for_guest")->where("room_id", $room_id)->where("date", $date)->where("item_id", $sc->item_id)->where("is_for_guest", 1)->first();

                    if ($sc->options != "") {
                        $c_options = json_decode($sc->options);
                        foreach (count($c_options) > 0 ? $c_options : array() as $co) {
                            $co = intval($co);
                            if ($option_details[$co]) {
                                $options[$co] = array("id" => $co, "name" => $option_details[$co]['option_name'], "c_name" => $option_details[$co]['option_name_cn'], "is_selected" => ($order_data && $order_data->item_options != null ? ($co == $order_data->item_options ? 1 : 0) : 0));
                            }
                        }
                    }

                    if ($sc->preference != "") {
                        $c_preferences = json_decode($sc->preference);
                        foreach (count($c_preferences) > 0 ? $c_preferences : array() as $cp) {
                            $cp = intval($cp);
                            if ($preference_details[$cp]) {
                                $preference[$cp] = array("id" => $cp, "name" => $preference_details[$cp]['name'], "c_name" => $preference_details[$cp]['name_cn'], "is_selected" => ($order_data && $order_data->preference != null ? (in_array($cp, explode(",", $order_data->preference)) ? 1 : 0) : 0));
                            }
                        }
                    }

                    array_push($sub_cat_details[$sc->id]["items"], array("item_id" => $sc->item_id, "item_name" => $sc->item_name, "chinese_name" => $sc->item_chinese_name, "item_image" => !empty($sc->item_image) ? Storage::url($sc->item_image) : NULL, "options" => array_values($options), "preference" => array_values($preference), "qty" => ($order_data ? ($order_data->is_for_guest ? $order_data->quantity : 0) : 0), "comment" => "", "order_id" => ($order_data ? $order_data->id : 0)));
                } else {
                    $order_data = OrderDetail::selectRaw("sum(quantity) as quantity")->where("date", $date)->where("item_id", $sc->item_id)->groupBy("item_id")->first();

                    if ($sc->options != "") {
                        $c_options = json_decode($sc->options);
                        foreach (count($c_options) > 0 ? $c_options : array() as $co) {
                            $co = intval($co);
                            if ($option_details[$co]) {
                                $options[$co] = array("id" => $co, "name" => $option_details[$co]['option_name'], "c_name" => $option_details[$co]['option_name_cn'], "is_selected" => 0, "item_count" => OrderDetail::where("date", $date)->where("item_id", $sc->item_id)->where("item_options", $co)->count());
                            }
                        }
                    }

                    array_push($sub_cat_details[$sc->id]["items"], array("item_id" => $sc->item_id, "item_name" => $sc->item_name, "chinese_name" => $sc->item_chinese_name, "item_image" => !empty($sc->item_image) ? Storage::url($sc->item_image) : NULL, "is_expanded" => count(array_values($options)) > 0 ? 1 : 0, "options" => array_values($options), "preference" => array_values($preference), "qty" => ($order_data ? intval($order_data->quantity) : 0), "comment" => "", "order_id" => ($order_data ? $order_data->id : 0)));
                }
            }
            foreach (count($sub_cat_details) > 0 ? $sub_cat_details : array() as $sc) {
                if (isset($cat_array[$sc['parent_id']])) {
                    array_push($cat_array[$sc['parent_id']]["items"], array("type" => "sub_cat", "item_id" => $sc["cat_id"], "item_name" => $sc["cat_name"], "chinese_name" => $sc["chinese_name"], "options" => [], "preference" => [], "item_image" => "", "qty" => 0, "comment" => "", "order_id" => 0));
                    foreach (count($sc["items"]) > 0 ? $sc["items"] : array() as $sci) {
                        $sc_item = array("type" => "sub_cat_item", "item_id" => $sci["item_id"], "item_name" => $sci["item_name"], "chinese_name" => $sci["chinese_name"], "item_image" => $sci["item_image"], "options" => $sci["options"], "preference" => $sci["preference"], "qty" => $sci["qty"], "comment" => $sci["comment"], "order_id" => $sci["order_id"]);
                        if (isset($sci["is_expanded"])) {
                            $sc_item["is_expanded"] = $sci["is_expanded"];
                        }
                        array_push($cat_array[$sc['parent_id']]["items"], $sc_item);
                    }
                    //, "items" => array_values($sc["items"]
                }
            }
        }
        foreach (count($cat_array) > 0 ? $cat_array : array() as $c) {
            $type = intval($c['type']);
            unset($c['type']);
            if ($type == 1) {
                array_push($breakfast, $c);
            } else if ($type == 2) {
                array_push($lunch, $c);
            } else if ($type == 3) {
                array_push($dinner, $c);
            }
        }

        $tray_service_data = OrderDetail::selectRaw("is_brk_tray_service,is_lunch_tray_service,is_dinner_tray_service,is_brk_escort_service,is_lunch_escort_service,is_dinner_escort_service")->where("room_id", $room_id)->where("date", $date)->where("is_for_guest", 1)->first();


        $occupancy = DateWiseOccupancy::select('occupancy')->where('room_id',  $room_id)->where('date', $date)->first();

        return $this->sendResultJSON('1', '', array('breakfast' => $breakfast, 'lunch' => $lunch, 'dinner' => $dinner, 'occupancy' => $occupancy ? $occupancy->occupancy : 0, 'is_brk_tray_service' => $tray_service_data ? $tray_service_data->is_brk_tray_service : 0, 'is_lunch_tray_service' => $tray_service_data ? $tray_service_data->is_lunch_tray_service : 0, 'is_dinner_tray_service' => $tray_service_data ? $tray_service_data->is_dinner_tray_service : 0));
    }

    // public function generateThumbnail(Request $request){
    //     $files = $_FILES;

    //     foreach ($files as $file){

    //         $fileExtension = explode("/",$file['type']);

    //         Storage::put('public/FormResponses/'.$uniqueFileName,$content);

    //         if ($fileExtension[0] == 'video'){

    //             $fileExtension = explode("/",$file['type']);
    //             $mediaFileName = uniqid() . time() . '.'.end($fileExtension);

    //             $this->generate_video_thumbnail(Storage::url('public/FormResponses/thumbnails/'.$mediaFileName),$file['tmp_name']);

    //             $imageOnlyAttachments[] = Storage::url('public/FormResponses/thumbnails/'.$mediaFileName);
    //         }




    //     }
    // }

    // temp routes for demo ios app



    public function getDynamicFormDemoData()
    {
        $body = '"[\n {\n  \"fieldLabel\" : \"First Name\",\n  \"fieldType\" : \"textfield\",\n   \"fieldVal\" : \"a\"\n  },\n {\n  \"fieldLabel\" : \"Surname\",\n  \"fieldType\" : \"textfield\",\n   \"fieldVal\" : \"a\"\n  }]"';
        // print_r;die;
        return $this->sendResultJSON("1", '', ['body' => (json_decode(json_decode($body, true), true))]);
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

    // public function saveTempForm(Request $request) {

    //     try{
    //         $form_data = $request->input('data');
    //         $form_type = $request->input('form_type');

    //         // $form = TempFormResponse::create([
    //         //     'form_type_id' => $form_type,
    //         //     'form_response' => json_decode($form_data,true)
    //         // ]);

    //          $form = TempFormResponse::create([
    //             'form_type_id' => $form_type,
    //             'form_response' => $form_data
    //         ]);

    //           return $this->sendResultJSON("1", "Successfully Submitted", array("submitted_form_id" => $form->id));
    //     } catch (\Exception $e) {
    //         return $this->sendResultJSON("0", $e->getMessage());
    //     }
    // }

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

    public function demoGetRequestFromBackend()
    {
        return $this->sendResultJson("1", "Hello World");
    }

    public function backendLogin()
    {
        try {

            $credentials = request(['email', 'password']);

            if (!$token = auth()->attempt($credentials)) {

                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $user = auth()->user();

            if (empty($user->is_admin)) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            return response()->json(['user' =>  $user, 'token' => $token], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function unauthorized()
    {

        return response()->json(['ResponseCode' => "11", 'ResponseText' => "Unauthorised"], 500);
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

            $newUser = User::with('permissionList', 'role')->where('id', $user->id)->get()->toArray();

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

            $newUser = User::with('permissionList', 'role')->where('id', $user->id)->get()->toArray();

            $data = [];

            foreach ($newUser as $result) {

                $data['user_id'] = $result['id'];
                $data['user_name'] = $result['name'];
                $data['role'] = !empty($result['role']) ? $result['role']['name'] : null;

                foreach ($result['permission_list'] as $permission) {

                    $allPermissions[$permission['name']] = 1;
                }

                $data['permission_list'] = $allPermissions;
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

    public function reportData(Request $request)
    {

        // get rooms list from this

        try {

            $queryDate = $request->get('date');
            $roomName = (int)$request->get('room_name');
            $chargedFor = $request->get('charged_for');

            $query = "select od.* , rd.*,id.* , io1.* from order_details od left join room_details rd on rd.id = od.room_id left join item_options io1 on io1.id = od.item_options left join item_details id on od.item_id = id.id where od.deleted_at IS NULL ";

            if (!empty($queryDate)) {
                $query .= " AND od.date = '$queryDate'";
            }

            if (!empty($roomName) && is_int($roomName)) {
                $query .= " AND rd.room_name = $roomName ";
            }

            $query .= " AND (io1.is_paid_item = 1 OR od.is_for_guest = 1 OR od.is_brk_tray_service = 1 OR od.is_lunch_tray_service = 1 OR od.is_dinner_tray_service = 1 OR od.is_brk_escort_service = 1 OR od.is_lunch_escort_service = 1 OR od.is_dinner_escort_service = 1) GROUP BY od.date,od.room_id,od.is_for_guest ORDER BY od.id DESC";

            // if (!empty($chargedFor)) {

            //     if ($chargedFor == 'Guest'){

            //         $query .= " AND (od.is_for_guest = 1) ";

            //     }

            //     else if ($chargedFor == 'Extra Item'){

            //         $query .= " AND (io.is_paid_item = 1) ";

            //     }

            //     else if ($chargedFor == 'Tray Service'){

            //         $query .= " AND (od.is_brk_tray_service = 1 OR od.is_lunch_tray_service OR od.is_dinner_tray_service = 1) ";

            //     }

            //     else if ($chargedFor == 'Escort Service'){

            //         $query .= " AND (od.is_brk_escort_service = 1 OR od.is_lunch_escort_service OR od.is_dinner_escort_service = 1) ";

            //     }
            // }

            // else{



            // }
            // echo $query;
            // die;
            $results = DB::select($query);

            $data = [];

            foreach ($results as $result) {

                $data[] = [
                    'room_number' => $result->room_name,
                    'resident_name' => $result->resident_name,
                    'order_date' => $result->date,
                    'is_for_guest' => $result->is_for_guest,
                    'is_brk_tray_service' => $result->is_brk_tray_service,
                    'is_lunch_tray_service' => $result->is_lunch_tray_service,
                    'is_dinner_tray_service' => $result->is_dinner_tray_service,
                    'is_brk_escort_service' => $result->is_brk_escort_service,
                    'is_lunch_escort_service' => $result->is_lunch_escort_service,
                    'is_dinner_escort_service' => $result->is_dinner_escort_service,
                    'order_date' => $result->date,
                    'is_extra_item' => empty($result->item_options) ? 0 : 1,
                    'room_id' => $result->room_id,
                    'item_name' => empty($result->item_options) ? "" : $result->item_name,
                    'item_options' => empty($result->item_options) ? "" : $result->option_name,
                    'item_quantity' => empty($result->is_for_guest) ? 0 : $result->quantity
                ];
            }

            return $this->sendResultJSON("1", "success", ["Data" => $data]);
        } catch (\Exception $e) {

            return response()->json(['ResponseCode' => "11", 'ResponseText' => $e->getMessage()], 200);
        }
    }

    public function reportDataTemp(Request $request)
    {

        // quantity and items from this

        $date = $request->input('date');
        $menu_details = MenuDetail::where("date", $date)->first(); // merge the is_allday data with this also

        $breakfast = $lunch = $dinner = array();
        $breakfast_rooms_array = $lunch_rooms_array = $dinner_rooms_array = array();
        $rooms_array = array();
        $cat_id = array(
            1 => 'BA',
            2 => 'LS',
            7 => 'LD',
            13 => 'DD',
        );
        $alternative = array(4, 8, 11);
        $ab_alternative = array(5, 3);

        $paid_item_options_query = ItemOptionModel::select('id')->where("is_paid_item", 1)->get();

        $paid_item_options = [];

        foreach ($paid_item_options_query as $paid_item_option) {
            $paid_item_options[] = $paid_item_option->id;
        }

        $selectedRoomIds = [];

        $selectedRoomsQuery = "select od.* , rd.*,id.* , io1.* from order_details od left join room_details rd on rd.id = od.room_id left join item_options io1 on io1.id = od.item_options left join item_details id on od.item_id = id.id where od.deleted_at IS NULL ";


        $selectedRoomsQuery .= " AND od.date = '$date'";


        $selectedRoomsQuery .= " AND (io1.is_paid_item = 1 OR od.is_for_guest = 1 OR od.is_brk_tray_service = 1 OR od.is_lunch_tray_service = 1 OR od.is_dinner_tray_service = 1 OR od.is_brk_escort_service = 1 OR od.is_lunch_escort_service = 1 OR od.is_dinner_escort_service = 1) GROUP BY od.date,od.room_id,od.is_for_guest ORDER BY od.id DESC";

        $selectedRoomsResults = DB::select($selectedRoomsQuery);


        foreach ($selectedRoomsResults as $selectedRoomsResult) {

            $selectedRoomIds[] = $selectedRoomsResult->room_id;
        }

        $selectedRoomIds = array_unique($selectedRoomIds);

        // print_r($selectedRoomIds);die;

        if ($menu_details) {

            $menu_items = json_decode($menu_details->items, true);
            $all_rooms = RoomDetail::where("is_active", 1)->get();
            $is_first = true;

            foreach ($selectedRoomIds as $selectedRoomId) {

                $r = RoomDetail::find($selectedRoomId);

                $wereGuestAvailable = false;

                $isOccupiedByGuest = DateWiseOccupancy::select('occupancy')->where('room_id',  $r->id)->where('date', $date)->first();

                if ($isOccupiedByGuest) {
                    if ($isOccupiedByGuest->occupancy) {
                        $wereGuestAvailable = true;
                    }
                }

                if ($menu_items["breakfast"]) {

                    $all_items = ItemDetail::selectRaw("id,item_name,cat_id")->whereRaw("id IN (" . implode(",", $menu_items["breakfast"]) . ")")->orderBy("cat_id")->get();
                    $count = 1;
                    $items = array();
                    $guestItems = array();

                    if (!isset($breakfast_rooms_array[$r->id]))
                        $breakfast_rooms_array[$r->id] = array("room_no" => $r->room_name, "quantity" => array());

                    foreach (count($all_items) > 0 ? $all_items : array() as $a) {

                        $title = (in_array($a->cat_id, $alternative) ? "B" . $count : $cat_id[$a->cat_id]);
                        if (!isset($breakfast[$a->id]))
                            $breakfast[$a->id] = array();

                        if ($is_first) {
                            $breakfast[$a->id] = array("item_name" => $title, "real_item_name" => $a->item_name);
                        }

                        $order_data = OrderDetail::select("quantity", "item_options", "is_brk_tray_service", "is_brk_escort_service")->where("date", $date)->where("room_id", $r->id)->where("item_id", $a->id)->where("is_for_guest", 0)->first();
                        $breakfast[$a->id]["total_count"] = 0;
                        if ($order_data) {
                            if (!empty($order_data->is_brk_tray_service) || !empty($order_data->is_brk_escort_service)) {

                                $breakfast[$a->id]["total_count"] += (in_array($order_data->item_options, $paid_item_options) ?  1 : 0);
                                array_push($items, (in_array($order_data->item_options, $paid_item_options) ?  1 : 0));
                            }
                        } else {
                            array_push($items, 0);
                        }

                        if ($wereGuestAvailable) {

                            $guest_order_data = OrderDetail::select("quantity", "item_options", "is_brk_tray_service", "is_brk_escort_service")->where("date", $date)->where("room_id", $r->id)->where("item_id", $a->id)->where("is_for_guest", 1)->first();

                            if ($guest_order_data) {
                                if (!empty($order_data->is_brk_tray_service) || !empty($order_data->is_brk_escort_service)) {

                                    array_push($guestItems, (in_array($guest_order_data->item_options, $paid_item_options) ?  1 : 0));
                                    $breakfast[$a->id]["total_count"] += (in_array($guest_order_data->item_options, $paid_item_options) ?  1 : 0);
                                }
                            } else {
                                array_push($guestItems, 0);
                            }
                        } else {
                            array_push($guestItems, 0);
                        }

                        if (in_array($a->cat_id, $alternative)) $count++;
                    }



                    $breakfast_rooms_array[$r->id]["quantity"] = $items;

                    if ($wereGuestAvailable) {
                        $guestRoomName = $r->room_name . " G";

                        $breakfast_rooms_array[$guestRoomName] = [
                            "room_no" => $guestRoomName,
                            "quantity" => $guestItems
                        ];
                    }
                }

                if ($menu_items["lunch"]) {

                    $all_items = ItemDetail::selectRaw("id,item_name,cat_id")->whereRaw("id IN (" . implode(",", $menu_items["lunch"]) . ")")->orderBy("cat_id")->get();
                    $ab_count = 'A';
                    $count = 1;
                    $items = array();
                    $guestItems = array();

                    if (!isset($lunch_rooms_array[$r->id]))
                        $lunch_rooms_array[$r->id] = array("room_no" => $r->room_name, "quantity" => array());

                    foreach (count($all_items) > 0 ? $all_items : array() as $a) {

                        $title = (in_array($a->cat_id, $alternative) ? "L" . $count : (in_array($a->cat_id, $ab_alternative) ? "L" . $ab_count : $cat_id[$a->cat_id]));
                        if (!isset($lunch[$a->id]))
                            $lunch[$a->id] = array();

                        if ($is_first) {
                            $lunch[$a->id] = array("item_name" => $title, "real_item_name" => $a->item_name);
                        }

                        $order_data = OrderDetail::select("quantity", "item_options", "is_lunch_tray_service", "is_lunch_escort_service")->where("date", $date)->where("room_id", $r->id)->where("item_id", $a->id)->where("is_for_guest", 0)->first();

                        $lunch[$a->id]["total_count"] = 0;

                        if ($order_data) {
                            if (!empty($order_data->is_brk_tray_service) || !empty($order_data->is_brk_escort_service)) {

                                $lunch[$a->id]["total_count"] += (in_array($order_data->item_options, $paid_item_options) ?  1 : 0);
                                array_push($items, (in_array($order_data->item_options, $paid_item_options) ?  1 : 0));
                            }
                        } else {
                            array_push($items, 0);
                        }

                        if ($wereGuestAvailable) {

                            $guest_order_data = OrderDetail::select("quantity", "item_options", "is_lunch_tray_service", "is_lunch_escort_service")->where("date", $date)->where("room_id", $r->id)->where("item_id", $a->id)->where("is_for_guest", 1)->first();

                            if ($guest_order_data) {
                                if (!empty($order_data->is_brk_tray_service) || !empty($order_data->is_brk_escort_service)) {

                                    array_push($guestItems, (in_array($guest_order_data->item_options, $paid_item_options) ?  1 : 0));
                                    $lunch[$a->id]["total_count"] += (in_array($guest_order_data->item_options, $paid_item_options) ?  1 : 0);
                                }
                            } else {
                                array_push($guestItems, 0);
                            }
                        } else {
                            array_push($guestItems, 0);
                        }

                        if (in_array($a->cat_id, $alternative)) $count++;
                        if (in_array($a->cat_id, $ab_alternative)) $ab_count = 'B';
                    }
                    $lunch_rooms_array[$r->id]["quantity"] = $items;

                    if ($wereGuestAvailable) {
                        $guestRoomName = $r->room_name . " G";

                        $lunch_rooms_array[$guestRoomName] = [
                            "room_no" => $guestRoomName,
                            "quantity" => $guestItems
                        ];
                    }
                }

                if ($menu_items["dinner"]) {

                    $all_items = ItemDetail::selectRaw("id,item_name,cat_id")->whereRaw("id IN (" . implode(",", $menu_items["dinner"]) . ")")->orderBy("cat_id")->get();
                    $count = 1;
                    $ab_count = 'A';
                    $items = array();
                    $guestItems = array();

                    if (!isset($dinner_rooms_array[$r->id]))
                        $dinner_rooms_array[$r->id] = array("room_no" => $r->room_name, "quantity" => array());
                    foreach (count($all_items) > 0 ? $all_items : array() as $a) {
                        $title = (in_array($a->cat_id, $alternative) ? "D" . $count : (in_array($a->cat_id, $ab_alternative) ? "D" . $ab_count : $cat_id[$a->cat_id]));
                        if (!isset($dinner[$a->id]))
                            $dinner[$a->id] = array();

                        if ($is_first) {
                            $dinner[$a->id] = array("item_name" => $title, "real_item_name" => $a->item_name);
                        }
                        $order_data = OrderDetail::select("quantity", "item_options", "is_dinner_tray_service", "is_dinner_escort_service")->where("date", $date)->where("room_id", $r->id)->where("item_id", $a->id)->where("is_for_guest", 0)->first();

                        $dinner[$a->id]["total_count"] = 0;

                        if ($order_data) {
                            if (!empty($order_data->is_dinner_tray_service) || !empty($order_data->is_dinner_escort_service)) {

                                $dinner[$a->id]["total_count"] += (in_array($order_data->item_options, $paid_item_options) ?  1 : 0);
                                array_push($items, (in_array($order_data->item_options, $paid_item_options) ?  1 : 0));
                            }
                        } else {
                            array_push($items, 0);
                        }

                        if ($wereGuestAvailable) {

                            $guest_order_data = OrderDetail::select("quantity", "item_options")->where("date", $date)->where("room_id", $r->id)->where("item_id", $a->id)->where("is_for_guest", 1)->first();

                            if ($guest_order_data) {
                                if (!empty($order_data->is_dinner_tray_service) || !empty($order_data->is_dinner_escort_service)) {

                                    array_push($guestItems, (in_array($guest_order_data->item_options, $paid_item_options) ?  1 : 0));
                                    $dinner[$a->id]["total_count"] += (in_array($guest_order_data->item_options, $paid_item_options) ? 1 : 0);
                                }
                            } else {
                                array_push($guestItems, 0);
                            }
                        } else {
                            array_push($guestItems, 0);
                        }

                        if (in_array($a->cat_id, $alternative)) $count++;
                        if (in_array($a->cat_id, $ab_alternative)) $ab_count = 'B';
                    }
                    $dinner_rooms_array[$r->id]["quantity"] = $items;

                    if ($wereGuestAvailable) {
                        $guestRoomName = $r->room_name . " G";

                        $dinner_rooms_array[$guestRoomName] = [
                            "room_no" => $guestRoomName,
                            "quantity" => $guestItems
                        ];
                    }
                }

                $is_first = false;

                array_push($rooms_array, array("room_id" => $r->id, "room_name" => $r->room_name, "has_special_ins" => ($r->special_instrucations != null ? 1 : 0), "has_breakfast_order" => (count($breakfast_rooms_array) ? (array_sum($breakfast_rooms_array[$r->id]["quantity"]) > 0 ? 1 : 0) : 0), "has_lunch_order" => (count($lunch_rooms_array) ?  (array_sum($lunch_rooms_array[$r->id]["quantity"]) > 0 ? 1 : 0) : 0), "has_dinner_order" => (count($lunch_rooms_array) ? (array_sum($dinner_rooms_array[$r->id]["quantity"]) > 0 ? 1 : 0) : 0), "is_for_guest" => 0));

                if ($isOccupiedByGuest) {
                    if ($isOccupiedByGuest->occupancy) {

                        $roomName = $r->room_name . " G";

                        array_push($rooms_array, array("room_id" => $r->id, "room_name" => $roomName, "has_special_ins" => 0, "has_breakfast_order" => (count($breakfast_rooms_array) ? (array_sum($breakfast_rooms_array[$roomName]["quantity"]) > 0 ? 1 : 0) : 0), "has_lunch_order" => (count($lunch_rooms_array) ?  (array_sum($lunch_rooms_array[$roomName]["quantity"]) > 0 ? 1 : 0) : 0), "has_dinner_order" => (count($lunch_rooms_array) ? (array_sum($dinner_rooms_array[$roomName]["quantity"]) > 0 ? 1 : 0) : 0), "is_for_guest" => 1));
                    }
                }
            }
        }

        $last_date = "";
        $menu_data = MenuDetail::select("date")->orderBy("date", "desc")->first();
        if ($menu_data) {
            $last_date = $menu_data->date;
        }

        if (!$menu_details) {
            return $this->sendResultJSON('1', 'Menu Details not Found!!', array('breakfast_item_list' => array_values($breakfast), 'lunch_item_list' => array_values($lunch), 'dinner_item_list' => array_values($dinner), 'report_breakfast_list' => array_values($breakfast_rooms_array), 'report_lunch_list' => array_values($lunch_rooms_array), 'report_dinner_list' => array_values($dinner_rooms_array), 'rooms_list' => $rooms_array, "last_menu_date" => $last_date));
        }

        return $this->sendResultJSON('1', '', array('breakfast_item_list' => array_values($breakfast), 'lunch_item_list' => array_values($lunch), 'dinner_item_list' => array_values($dinner), 'report_breakfast_list' => array_values($breakfast_rooms_array), 'report_lunch_list' => array_values($lunch_rooms_array), 'report_dinner_list' => array_values($dinner_rooms_array)));
    }

    public function reportDataTemp2(Request $request)
    {


        //  {   

        // quantity and items from this

        $date = $request->input('date');
        $menu_details = MenuDetail::where("date", $date)->first(); // merge the is_allday data with this also

        $breakfast = $lunch = $dinner = array();
        $breakfast_rooms_array = $lunch_rooms_array = $dinner_rooms_array = array();
        $rooms_array = array();
        $cat_id = array(
            1 => 'BA',
            2 => 'LS',
            7 => 'LD',
            13 => 'DD',
        );
        $alternative = array(4, 8, 11);
        $ab_alternative = array(5, 3);

        $breakfastIds = [];
        $lunchIds = [];
        $dinnerIds = [];

        $breakfastQuantity = [];
        $lunchQuantity = [];
        $dinnerQuantity = [];

        if ($menu_details) {

            $menu_items = json_decode($menu_details->items, true);

            if ($menu_items["breakfast"]) {

                $all_items = ItemDetail::selectRaw("id,item_name,cat_id")->whereRaw("id IN (" . implode(",", $menu_items["breakfast"]) . ")")->orderBy("cat_id")->get();
                $count = 1;
                $items = array();

                foreach (count($all_items) > 0 ? $all_items : array() as $a) {



                    $title = (in_array($a->cat_id, $alternative) ? null : $cat_id[$a->cat_id]);
                    if (!isset($breakfast[$a->id]) && !empty($title))
                        $breakfast[$a->id] = array();

                    if (!empty($title)) {

                        $breakfast[$a->id] = array("item_name" => $title, "real_item_name" => $a->item_name, 'item_id' => $a->id);
                        $breakfastIds[] = $a->id;
                    }

                    if (in_array($a->cat_id, $alternative)) $count++;
                }
            }

            // print_r($breakfast);die;

            if ($menu_items["lunch"]) {

                $all_items = ItemDetail::selectRaw("id,item_name,cat_id")->whereRaw("id IN (" . implode(",", $menu_items["lunch"]) . ")")->orderBy("cat_id")->get();
                $ab_count = 'A';
                $count = 1;
                $items = array();


                foreach (count($all_items) > 0 ? $all_items : array() as $a) {


                    $title = (in_array($a->cat_id, $alternative) ? null : (in_array($a->cat_id, $ab_alternative) ? "L" . $ab_count : $cat_id[$a->cat_id]));
                    if (!isset($lunch[$a->id]) && !empty($title))
                        $lunch[$a->id] = array();

                    if (!empty($title)) {

                        $lunch[$a->id] = array("item_name" => $title, "real_item_name" => $a->item_name, 'item_id' => $a->id);
                        $lunchIds[] = $a->id;
                    }

                    if (in_array($a->cat_id, $alternative)) $count++;
                    if (in_array($a->cat_id, $ab_alternative)) $ab_count = 'B';
                }
            }

            if ($menu_items["dinner"]) {

                $all_items = ItemDetail::selectRaw("id,item_name,cat_id")->whereRaw("id IN (" . implode(",", $menu_items["dinner"]) . ")")->orderBy("cat_id")->get();
                $count = 1;
                $ab_count = 'A';
                $items = array();
                $guestItems = array();


                foreach (count($all_items) > 0 ? $all_items : array() as $a) {


                    $title = (in_array($a->cat_id, $alternative) ? null : (in_array($a->cat_id, $ab_alternative) ? "D" . $ab_count : $cat_id[$a->cat_id]));
                    if (!isset($dinner[$a->id]) && !empty($title))
                        $dinner[$a->id] = array();

                    if (!empty($title)) {

                        $dinner[$a->id] = array("item_name" => $title, "real_item_name" => $a->item_name, 'item_id' => $a->id);
                        $dinnerIds[] = $a->id;
                    }

                    if (in_array($a->cat_id, $alternative)) $count++;
                    if (in_array($a->cat_id, $ab_alternative)) $ab_count = 'B';
                }
            }
        }

        // $last_date = "";
        // $menu_data = MenuDetail::select("date")->orderBy("date", "desc")->first();
        // if ($menu_data) {
        //     $last_date = $menu_data->date;
        // }

        // if (!$menu_details){
        //     return $this->sendResultJSON('1', 'Menu Details not Found!!', array('breakfast_item_list' => array_values($breakfast), 'lunch_item_list' => array_values($lunch), 'dinner_item_list' => array_values($dinner), 'report_breakfast_list' => array_values($breakfast_rooms_array), 'report_lunch_list' => array_values($lunch_rooms_array), 'report_dinner_list' => array_values($dinner_rooms_array),'rooms_list' => $rooms_array,"last_menu_date" => $last_date));
        // }

        //     return $this->sendResultJSON('1', '', array('breakfast_item_list' => array_values($breakfast), 'lunch_item_list' => array_values($lunch), 'dinner_item_list' => array_values($dinner), 'report_breakfast_list' => array_values($breakfast_rooms_array), 'report_lunch_list' => array_values($lunch_rooms_array), 'report_dinner_list' => array_values($dinner_rooms_array),'rooms_list' => $rooms_array,"last_menu_date" => $last_date));

        // }

        $paid_item_options_query = ItemOptionModel::select('id', 'option_name')->where("is_paid_item", 1)->get();

        $paid_item_options = [];

        foreach ($paid_item_options_query as $paid_item_option) {
            $paid_item_options[$paid_item_option->id] = $paid_item_option->option_name;
        }

        // print_r($paid_item_options);die;

        $baseItemList = [
            [
                'item_name' => 'T',
                'real_item_name' => 'Tray Service',

            ],
            [
                'item_name' => 'E',
                'real_item_name' => 'Escort Service',

            ],
            [
                'item_name' => 'G',
                'real_item_name' => 'No. Of Guests',

            ]
        ];

        $reportBreakfastList = [];
        $reportLunchList = [];
        $reportDinnerList = [];

        $selectedRoomIds = [];

        // $selectedRoomsQuery = "select od.room_id as roomId,
        //                         od.id as orderId,
        //                         od.date as date,
        //                         od.item_id as itemId,
        //                         od.quantity as quantity,
        //                         od.is_for_guest as isForGuest,
        //                         od.is_brk_tray_service as brkTrayService,
        //                         od.is_lunch_tray_service as lunchTrayService,
        //                         od.is_dinner_tray_service as dinnerTrayService,
        //                         od.is_brk_escort_service as brkEscortService,
        //                         od.is_lunch_escort_service as lunchEscortService,
        //                         od.is_dinner_escort_service as dinnerEscortService,
        //                         od.item_options as itemOptions,


        //                         rd.room_name as roomName,

        //                         id.item_name as itemName,
        //                         id.cat_id as itemCategoryId,

        //                         io1.id as itemOptionId,
        //                         io1.is_paid_item as isPaidItem,


        //                         cd.id as itemCategoryId,
        //                         cd.type as itemCategoryType,

        //                         dwo.occupancy as noOfGuest

        //                         from order_details od
        //                         left join room_details rd on rd.id = od.room_id
        //                         left join item_options io1 on io1.id = od.item_options
        //                         left join item_details id on od.item_id = id.id
        //                         left join category_details cd on cd.id = id.cat_id
        //                         left join date_wise_occupancies dwo on dwo.room_id = rd.id
        //                         where (od.deleted_at IS NULL and io1.deleted_at is NULL AND rd.is_active = 1 and od.date = '".$date. "') AND (io1.is_paid_item = 1 OR od.is_for_guest = 1 OR
        //                         od.is_brk_tray_service = 1 OR od.is_lunch_tray_service = 1 OR od.is_dinner_tray_service = 1 OR od.is_brk_escort_service
        //                         = 1 OR od.is_lunch_escort_service = 1 OR od.is_dinner_escort_service = 1) group by od.room_id,od.is_for_guest,od.item_id ORDER BY od.id DESC ";






        // echo $selectedRoomsQuery;die;
        // $selectedRoomsResults = DB::select($selectedRoomsQuery);

        // print_r($selectedRoomsResults);die;
        // foreach ($selectedRoomsResults as $selectedRoomsResult){

        //     if ($selectedRoomsResult->itemCategoryType == 1){

        //         if (empty($selectedRoomsResult->isForGuest)){

        //             $reportBreakfastList[] = [
        //                 'room_no' => $selectedRoomsResult->roomName,
        //                 'is_for_guest' => 0,
        //                 'data' => [
        //                     (!empty($selectedRoomsResult->brkTrayService) ? 1 : 0),
        //                     (!empty($selectedRoomsResult->brkEscortSerive) ? 1 : 0),
        //                     0,
        //                 ]
        //             ];

        //         }

        //         if (!empty($selectedRoomsResult->isForGuest)){

        //             $reportBreakfastList[] = [
        //                 'room_no' => $selectedRoomsResult->roomName . " G",
        //                 'is_for_guest' => 1,
        //                 'data' => [
        //                     (!empty($selectedRoomsResult->brkTrayService) ? 1 : 0),
        //                     (!empty($selectedRoomsResult->brkEscortSerive) ? 1 : 0),
        //                     $selectedRoomsResult->noOfGuest,
        //                 ]
        //             ];

        //         }
        //     }

        //     else if ($selectedRoomsResult->itemCategoryType == 2){
        //          if (empty($selectedRoomsResult->isForGuest)){

        //             $reportLunchList[] = [
        //                 'room_no' => $selectedRoomsResult->roomName,
        //                 'is_for_guest' => 0,
        //                 'data' => [
        //                     (!empty($selectedRoomsResult->lunchTrayService) ? 1 : 0),
        //                     (!empty($selectedRoomsResult->lunchEscortService) ? 1 : 0),
        //                     0,
        //                 ]
        //             ];

        //         }

        //         if (!empty($selectedRoomsResult->isForGuest)){

        //             $reportLunchList[] = [
        //                 'room_no' => $selectedRoomsResult->roomName . " G",
        //                 'is_for_guest' => 1,
        //                 'data' => [
        //                     (!empty($selectedRoomsResult->lunchTrayService) ? 1 : 0),
        //                     (!empty($selectedRoomsResult->lunchEscortService) ? 1 : 0),
        //                     $selectedRoomsResult->noOfGuest,
        //                 ]
        //             ];

        //         }
        //     }

        //     else if ($selectedRoomsResult->itemCategoryType == 3){
        //          if (empty($selectedRoomsResult->isForGuest)){

        //             $reportDinnerList[] = [
        //                 'room_no' => $selectedRoomsResult->roomName,
        //                 'is_for_guest' => 0,
        //                 'data' => [
        //                     (!empty($selectedRoomsResult->dinnerTrayService) ? 1 : 0),
        //                     (!empty($selectedRoomsResult->dinnerEscortService) ? 1 : 0),
        //                     0,
        //                 ]
        //             ];

        //         }

        //         if (!empty($selectedRoomsResult->isForGuest)){

        //             $reportDinnerList[] = [
        //                 'room_no' => $selectedRoomsResult->roomName . " G",
        //                 'is_for_guest' => 1,
        //                 'data' => [
        //                     (!empty($selectedRoomsResult->dinnerTrayService) ? 1 : 0),
        //                     (!empty($selectedRoomsResult->dinnerEscortService) ? 1 : 0),
        //                     $selectedRoomsResult->noOfGuest,
        //                 ]
        //             ];

        //         }
        //     }
        // }

        //   rd.room_name as roomName,

        //                         id.item_name as itemName,
        //                         id.cat_id as itemCategoryId,

        //                         io1.id as itemOptionId,
        //                         io1.is_paid_item as isPaidItem,


        //                         cd.id as itemCategoryId,
        //                         cd.type as itemCategoryType,

        //                         dwo.occupancy as noOfGuest

        $breakfastSql = "SELECT 
                        od.room_id as roomId,
                        od.is_for_guest as isForGuest,
                        od.is_brk_tray_service brkTrayService,
                        od.is_brk_escort_service brkEscortService,
                        od.id as orderId,
                        
                        rd.room_name as roomName,
                        
                        id.item_name as itemName,
                        id.cat_id as itemCategoryId,
                        
                        io.id as itemOptionId,
                        io.is_paid_item as isPaidItem,
                        io.option_name as itemOptionName,
                        
                        dwo.occupancy as noOfGuest
                        
                        FROM order_details od
                        left join room_details rd on rd.id = od.room_id
                        left join item_details id on id.id = od.item_id
                        left join item_options io on io.id = od.item_options
                        left join category_details cd on cd.id = id.cat_id
                        
                        left join date_wise_occupancies dwo on dwo.room_id = rd.id and dwo.date = od.date
                        where od.date = '" . $date . "' AND 
                        od.item_id IN (SELECT id FROM item_details WHERE cat_id
                        IN (SELECT id FROM category_details WHERE type = '1')) AND 
                        (od.is_brk_tray_service = 1 OR od.is_brk_escort_service = 1 OR od.is_for_guest > 0 OR (od.item_options IN (SELECT id FROM item_options WHERE is_paid_item = '1')))
                        group by od.room_id,od.is_for_guest";

        $breakFastData = DB::select($breakfastSql);

        foreach ($breakFastData as $breakFastRow) {

            $breakfastQuantity = null;

            if (empty($breakFastRow->isForGuest)) {

                $breakfastQuantity = [
                    (!empty($breakFastRow->brkTrayService) ? 1 : 0),
                    (!empty($breakFastRow->brkEscortService) ? 1 : 0),
                    0
                ];

                $option = [
                    "",
                    "",
                    ""
                ];

                foreach ($breakfastIds as $breakfastId) {

                    $quantitySql = "SELECT quantity,item_options FROM order_details where room_id = '" . $breakFastRow->roomId . "' AND date = '" . $date . "' AND is_for_guest = 0 AND item_options IN (SELECT id FROM item_options WHERE is_paid_item = '1') AND item_id = " . $breakfastId . " ";

                    $quantityData = DB::select($quantitySql);

                    if (count($quantityData)) {

                        foreach ($quantityData as $qData) {

                            $breakfastQuantity[] = empty($qData->quantity) ? 0 : $qData->quantity;

                            if (empty($qData->quantity)) {
                                $option[] = "";
                            } else {
                                if (array_key_exists($qData->item_options, $paid_item_options)) {
                                    $option[] = $paid_item_options[$qData->item_options];
                                } else {
                                    $option[] = "";
                                }
                            }
                        }
                    } else {
                        $breakfastQuantity[] = 0;
                        $option[] = "";
                    }
                }


                $reportBreakfastList[] = [
                    'room_no' => $breakFastRow->roomName,
                    'room_id' => $breakFastRow->roomId,
                    'is_for_guest' => 0,
                    'data' => $breakfastQuantity,
                    "option" => $option
                ];
            }

            if (!empty($breakFastRow->isForGuest)) {

                $breakfastQuantity = [
                    (!empty($breakFastRow->brkTrayService) ? 1 : 0),
                    (!empty($breakFastRow->brkEscortService) ? 1 : 0),
                    $breakFastRow->noOfGuest
                ];

                $option = [
                    "",
                    "",
                    ""
                ];

                foreach ($breakfastIds as $breakfastId) {

                    $quantitySql = "SELECT quantity,item_options FROM order_details where room_id = '" . $breakFastRow->roomId . "' AND date = '" . $date . "' AND is_for_guest = 1 AND item_options IN (SELECT id FROM item_options WHERE is_paid_item = '1') AND item_id = " . $breakfastId . " ";

                    $quantityData = DB::select($quantitySql);

                    if (count($quantityData)) {

                        foreach ($quantityData as $qData) {

                            $breakfastQuantity[] = empty($qData->quantity) ? 0 : $qData->quantity;

                            if (empty($qData->quantity)) {
                                $option[] = "";
                            } else {
                                if (array_key_exists($qData->item_options, $paid_item_options)) {
                                    $option[] = $paid_item_options[$qData->item_options];
                                } else {
                                    $option[] = "";
                                }
                            }
                        }
                    } else {
                        $breakfastQuantity[] = 0;
                        $option[] = "";
                    }
                }

                $reportBreakfastList[] = [
                    'room_no' => $breakFastRow->roomName . " G",
                    'room_id' => $breakFastRow->roomId,
                    'is_for_guest' => 1,
                    'data' => $breakfastQuantity,
                    "option" => $option
                ];
            }
        }

        // lunch

        $lunchSql = "SELECT 
                        od.room_id as roomId,
                        od.is_for_guest as isForGuest,
                        od.is_lunch_tray_service lunchTrayService,
                        od.is_lunch_escort_service lunchEscortService,
                        od.id as orderId,
                        
                        rd.room_name as roomName,
                        
                        id.item_name as itemName,
                        id.cat_id as itemCategoryId,
                        
                        io.id as itemOptionId,
                        io.is_paid_item as isPaidItem,
                        io.option_name as itemOptionName,
                        
                        dwo.occupancy as noOfGuest
                        
                        FROM order_details od
                        left join room_details rd on rd.id = od.room_id
                        left join item_details id on id.id = od.item_id
                        left join item_options io on io.id = od.item_options
                        left join category_details cd on cd.id = id.cat_id
                        
                        left join date_wise_occupancies dwo on dwo.room_id = rd.id and dwo.date = od.date
                        where od.date = '" . $date . "' AND 
                        od.item_id IN (SELECT id FROM item_details WHERE cat_id
                        IN (SELECT id FROM category_details WHERE type = '2')) AND 
                        (od.is_lunch_tray_service = 1 OR od.is_lunch_escort_service = 1 OR od.is_for_guest > 0 OR (od.item_options IN (SELECT id FROM item_options WHERE is_paid_item = '1')))
                        group by od.room_id,od.is_for_guest";

        $lunchData = DB::select($lunchSql);


        foreach ($lunchData as $lunchRow) {

            $lunchQuantity = null;

            if (empty($lunchRow->isForGuest)) {

                $lunchQuantity = [
                    (!empty($lunchRow->lunchTrayService) ? 1 : 0),
                    (!empty($lunchRow->lunchEscortService) ? 1 : 0),
                    0
                ];

                $option = [
                    "",
                    "",
                    ""
                ];

                foreach ($lunchIds as $lunchId) {

                    $quantitySql = "SELECT quantity,item_options FROM order_details where room_id = '" . $lunchRow->roomId . "' AND date = '" . $date . "' AND is_for_guest = 0 AND item_options IN (SELECT id FROM item_options WHERE is_paid_item = '1') AND item_id = " . $lunchId . " ";

                    $quantityData = DB::select($quantitySql);

                    if (count($quantityData)) {

                        foreach ($quantityData as $qData) {

                            $lunchQuantity[] = empty($qData->quantity) ? 0 : $qData->quantity;

                            if (empty($qData->quantity)) {
                                $option[] = "";
                            } else {
                                if (array_key_exists($qData->item_options, $paid_item_options)) {
                                    $option[] = $paid_item_options[$qData->item_options];
                                } else {
                                    $option[] = "";
                                }
                            }
                        }
                    } else {
                        $lunchQuantity[] = 0;
                        $option[] = "";
                    }
                }

                $reportLunchList[] = [
                    'room_no' => $lunchRow->roomName,
                    'room_id' => $lunchRow->roomId,
                    'is_for_guest' => 0,
                    'data' => $lunchQuantity,
                    "option" => $option
                ];
            }

            if (!empty($lunchRow->isForGuest)) {

                $lunchQuantity = [
                    (!empty($lunchRow->lunchTrayService) ? 1 : 0),
                    (!empty($lunchRow->lunchEscortService) ? 1 : 0),
                    $lunchRow->noOfGuest
                ];

                $option = [
                    "",
                    "",
                    ""
                ];

                foreach ($lunchIds as $lunchId) {

                    $quantitySql = "SELECT quantity,item_options FROM order_details where room_id = '" . $lunchRow->roomId . "' AND date = '" . $date . "' AND is_for_guest = 1 AND item_options IN (SELECT id FROM item_options WHERE is_paid_item = '1') AND item_id = " . $lunchId . " ";

                    $quantityData = DB::select($quantitySql);

                    if (count($quantityData)) {

                        foreach ($quantityData as $qData) {

                            $lunchQuantity[] = empty($qData->quantity) ? 0 : $qData->quantity;

                            if (empty($qData->quantity)) {
                                $option[] = "";
                            } else {
                                if (array_key_exists($qData->item_options, $paid_item_options)) {
                                    $option[] = $paid_item_options[$qData->item_options];
                                } else {
                                    $option[] = "";
                                }
                            }
                        }
                    } else {
                        $lunchQuantity[] = 0;
                        $option[] = "";
                    }
                }

                $reportLunchList[] = [
                    'room_no' => $lunchRow->roomName . " G",
                    'room_id' => $lunchRow->roomId,
                    'is_for_guest' => 1,
                    'data' => $lunchQuantity,
                    "option" => $option
                ];
            }
        }

        // dinner

        $dinnerSql = "SELECT 
                        od.room_id as roomId,
                        od.is_for_guest as isForGuest,
                        od.is_dinner_tray_service dinnerTrayService,
                        od.is_dinner_escort_service dinnerEscortService,
                        od.id as orderId,
                        
                        rd.room_name as roomName,
                        
                        id.item_name as itemName,
                        id.cat_id as itemCategoryId,
                        io.option_name as itemOptionName,
                        
                        io.id as itemOptionId,
                        io.is_paid_item as isPaidItem,
                        
                        dwo.occupancy as noOfGuest
                        
                        FROM order_details od
                        left join room_details rd on rd.id = od.room_id
                        left join item_details id on id.id = od.item_id
                        left join item_options io on io.id = od.item_options
                        left join category_details cd on cd.id = id.cat_id
                        
                        left join date_wise_occupancies dwo on dwo.room_id = rd.id and dwo.date = od.date
                        where od.date = '" . $date . "' AND 
                        od.item_id IN (SELECT id FROM item_details WHERE cat_id
                        IN (SELECT id FROM category_details WHERE type = '3')) AND 
                        (od.is_dinner_tray_service = 1 OR od.is_dinner_escort_service = 1 OR od.is_for_guest > 0 OR (od.item_options IN (SELECT id FROM item_options WHERE is_paid_item = '1')))
                        group by od.room_id,od.is_for_guest";

        $dinnerData = DB::select($dinnerSql);

        foreach ($dinnerData as $dinnerRow) {

            $dinnerQuantity = null;

            if (empty($dinnerRow->isForGuest)) {

                $dinnerQuantity = [
                    (!empty($dinnerRow->dinnerTrayService) ? 1 : 0),
                    (!empty($dinnerRow->dinnerEscortService) ? 1 : 0),
                    0
                ];

                $option = [
                    "",
                    "",
                    ""
                ];

                foreach ($dinnerIds as $dinnerId) {

                    $quantitySql = "SELECT quantity,item_options FROM order_details where room_id = '" . $dinnerRow->roomId . "' AND date = '" . $date . "' AND is_for_guest = 0 AND item_options IN (SELECT id FROM item_options WHERE is_paid_item = '1') AND item_id = " . $dinnerId . " ";

                    $quantityData = DB::select($quantitySql);

                    if (count($quantityData)) {

                        foreach ($quantityData as $qData) {

                            $dinnerQuantity[] = empty($qData->quantity) ? 0 : $qData->quantity;

                            if (empty($qData->quantity)) {
                                $option[] = "";
                            } else {
                                if (array_key_exists($qData->item_options, $paid_item_options)) {
                                    $option[] = $paid_item_options[$qData->item_options];
                                } else {
                                    $option[] = "";
                                }
                            }
                        }
                    } else {
                        $dinnerQuantity[] = 0;
                        $option[] = "";
                    }
                }



                $reportDinnerList[] = [
                    'room_no' => $dinnerRow->roomName,
                    'room_id' => $dinnerRow->roomId,
                    'is_for_guest' => 0,
                    'data' => $dinnerQuantity,
                    "option" => $option
                ];
            }

            if (!empty($dinnerRow->isForGuest)) {

                $dinnerQuantity = [
                    (!empty($dinnerRow->dinnerTrayService) ? 1 : 0),
                    (!empty($dinnerRow->dinnerEscortService) ? 1 : 0),
                    $dinnerRow->noOfGuest
                ];

                $option = [
                    "",
                    "",
                    ""
                ];

                foreach ($dinnerIds as $dinnerId) {

                    $quantitySql = "SELECT quantity,item_options FROM order_details where room_id = '" . $dinnerRow->roomId . "' AND date = '" . $date . "' AND is_for_guest = 1 AND item_options IN (SELECT id FROM item_options WHERE is_paid_item = '1') AND item_id = " . $dinnerId . " ";

                    $quantityData = DB::select($quantitySql);

                    if (count($quantityData)) {

                        foreach ($quantityData as $qData) {

                            $dinnerQuantity[] = empty($qData->quantity) ? 0 : $qData->quantity;

                            if (empty($qData->quantity)) {
                                $option[] = "";
                            } else {
                                if (array_key_exists($qData->item_options, $paid_item_options)) {
                                    $option[] = $paid_item_options[$qData->item_options];
                                } else {
                                    $option[] = "";
                                }
                            }
                        }
                    } else {
                        $dinnerQuantity[] = 0;
                        $option[] = "";
                    }
                }

                $reportDinnerList[] = [
                    'room_no' => $dinnerRow->roomName . " G",
                    'room_id' => $dinnerRow->roomId,
                    'is_for_guest' => 1,
                    'data' => $dinnerQuantity,
                    "option" => $option
                ];
            }
        }

        $finalData = [
            'breakfast_item_list' => array_merge($baseItemList, array_values($breakfast)),
            'report_breakfast_list' => $reportBreakfastList,
            'lunch_item_list' =>   array_merge($baseItemList, array_values($lunch)),
            'report_lunch_list' => $reportLunchList,
            'dinner_item_list' => array_merge($baseItemList, array_values($dinner)),
            'report_dinner_list' => $reportDinnerList
        ];

        return $this->sendResultJSON('1', '', $finalData);
    }

    public function getTempFormDownload()
    {

        $temp = '{"logged_by":"R O","logged_at":"2024-11-21 6:12:56","action_taken":"forwarded task to front end team","follow_up_required":"Need to develop form UI, integrate form data and required testing ","log_text":"Form pdf","is_completed":0,"room_number":"440A","resident_name":"Mr. Eleanor Yee Nor CHEUNG"}';

        $data = json_decode($temp, true);

        $pdf = PDF::loadView('ui-test', $data);
        return $content = $pdf->download();
    }

    public function saveFormTempPdf(Request $request)
    {


        try {

            $sampleData = '{"type_of_inc_other_text":"other incident","witness_position2":"pos 2","fire_false_alarm":"No","condition_at_inc_sedated":0,"type_of_inc_security":1,"ambulation_limited":1,"fire_property_damage":"Yes","ambulation_wheelChair":0,"notified_other_date":"23 Oct 2024 02:50 PM","ambulation_unlimited":0,"notified_other_dt":"23 Oct 2024","type_of_incident":"Fall,Resident Abase,Fire,Treatment,Security,Loss Of Property,Elopement,Aggressive Behavior,other incident","fall_assess_moodAltMedi":0,"incident_dt":"23 Oct 2024","notified_other":"other notified","followUp_possible_solutions":"Solutions text","fall_assess_mediChange":1,"inc_invl_staff":1,"factual_description":"Fact","notified_resident_date":"23 Oct 2024 02:50 PM","informed_of_inc_AGM":1,"type_of_inc_treatment":1,"completed_position":"developer","safety_callbell":"No","condition_at_incident":"Oriented,other conditions ","notified_resident_responsible_party":"Yes","notified_other_tm":"02:50 PM","informed_of_inc_GM":1,"incident_location":"room","completed_by":"Rahi","completed_dt":"23 Oct 2024","witness_name1":"nm 1","followUp_examine_result":"Follow up text","incident_involved":"Resident,Staff,oth inc","discovered_by":"mng","type_of_inc_choking":0,"discovery_dt":"23 Oct 2024","inc_invl_visitor":0,"completed_tm":"02:50 PM","type_of_inc_aggresiveBeh":1,"safety_caution":"N\/A","fall_assess_tempIllness":1,"ambulation_reqAssist":1,"followUp_findings":"Findings text","fire_alarm_pulled":"Yes","informed_of_inc_RMC":1,"discovery_date":"23 Oct 2024 02:48 PM","notified_family_doctor":"doc","other_witnesses":"Yes","notified_family_doctor_dt":"23 Oct 2024","type_of_inc_other":1,"discovery_location":"room","condition_at_inc_other_text":"other conditions ","fall_assess_relocation":1,"informed_of_inc_other":1,"inc_invl_other":1,"inc_invl_resident":1,"discovery_tm":"02:48 PM","fall_assess_visDef":0,"notified_family_doctor_date":"23 Oct 2024 02:50 PM","initial_other":"oth","safety_other":"other safety","incident_date":"23 Oct 2024 02:48 PM","condition_at_inc_other":1,"type_of_inc_fire":1,"fall_assess_cardMedi":1,"ambulation_other_text":"other Ambulation","ambulation_other":1,"type_of_inc_elopement":1,"fall_assessment":"Medication Change,Cardiac Medications,Relocation,Temporary Illness","type_of_inc_fall":1,"initial_gm":"gm","condition_at_inc_disOriented":0,"informed_of_incident":"Assistant General Manager,General Manager,Risk Management Committee,other notification ","condition_at_inc_oriented":1,"informed_of_inc_other_text":"other notification ","notified_resident_tm":"02:50 PM","inc_invl_other_text":"oth inc","notified_resident_dt":"23 Oct 2024","followUp_action_plan":"Plan text","fire_personal_injury":"No","initial_risk_mng_committee":"rmc","followUp_issue":"Issue text","initial_assistant_gm":"agm","witnessed_by":"mng","notified_family_doctor_tm":"02:50 PM","type_of_inc_resAbase":1,"completed_date":"23 Oct 2024 02:50 PM","safety_fob":"Yes","ambulation":"Limited,Required assistance,Walker,other Ambulation","witness_name2":"nm 2","notified_resident_name":"RAO","fire_extinguisher_used":"Yes","type_of_inc_death":0,"incident_tm":"02:48 PM","ambulation_walker":1,"type_of_inc_lossOfProp":1,"witness_position1":"pos 1"}';

            $data = json_decode($sampleData, true);

            $files = $_FILES;
            // print_r($files);die;

            $uniqueFileName = uniqid() . time() . '.pdf';

            foreach ($files as $key => $file) {


                $fileExtension = explode("/", $file['type']);
                $mediaFileName = uniqid() . time() . '.' . end($fileExtension);
                Storage::put('public/FormResponses/media/' . $mediaFileName, file_get_contents($file['tmp_name']));
                $mediaLinks[] = Storage::url('public/FormResponses/media/' . $mediaFileName);
            }

            $data['images']  = $mediaLinks;

            $pdf = PDF::loadView('demo-form-copy', $data);

            $content = $pdf->download()->getOriginalContent();

            Storage::put('public/FormResponses/' . $uniqueFileName, $content);

            return $this->sendResultJSON("1", "Successfully Submitted", array("link" => Storage::url('public/FormResponses/' . $uniqueFileName)));
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function saveFormPhase1(Request $request)
    {
        $userId = null;
        $files = $_FILES;

        try {

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
                            return response()->json(['ResponseCode' => "11", 'ResponseText' => "Unauthorised"], 200);
                        }
                    }
                }
            } else {

                return response()->json(['ResponseCode' => "11", 'ResponseText' => "Unauthorised"], 200);
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
                return $this->sendResultJSON("2", $validator->errors()->first());
            }

            $form_type = $request->input('form_type');
            $form_data = $request->input('data');
            $room_id = $request->input('room_id');
            $follow_up_assigned_to = $request->input('follow_up_assigned_to');

            if (!in_array($form_type, [1, 2, 3])) {
                return $this->sendResultJSON("2", "Invalid Form Type");
            }

            if ($form_type == 3 && !array_key_exists('file', $files)) {
                return $this->sendResultJSON("2", "Signature Not Found");
            }

            $uniqueFileName = uniqid() . time() . '.pdf';

            $form = FormResponse::create([
                'form_type_id' => $form_type,
                'form_response' => json_decode($form_data, true),
                'created_by' => $userId,
                // 'created_by' => "1",
                'file_name' => $uniqueFileName,
                'room_id' => $room_id,
                'follow_up_assigned_to' => (!empty($follow_up_assigned_to) && $form_type == 1) ? $follow_up_assigned_to : 0
            ]);

            $imageOnlyAttachments = [];
            $mediaLinks = [];
            $filesToDelete = [];

            foreach ($files as $key => $file) {

                $thumbnailFileName = null;

                if (substr($key, 0, -1) != 'thumbnail') { // remove the trailing 1,2 .....

                    $fileExtension = explode("/", $file['type']);
                    $mediaFileName = uniqid() . time() . '.' . end($fileExtension);
                    Storage::put('public/FormResponses/media/' . $mediaFileName, file_get_contents($file['tmp_name']));
                    $mediaLinks[] = Storage::url('public/FormResponses/media/' . $mediaFileName);

                    if ($fileExtension[0] == 'image') {
                        $imageOnlyAttachments[] = Storage::url('public/FormResponses/media/' . $mediaFileName);
                        $filesToDelete[] = 'public/FormResponses/media/' . $mediaFileName;
                    }

                    if (array_key_exists("thumbnail" . substr($key, -1), $files) && $fileExtension[0] == 'video') {

                        $originalThumbnailFile = $files["thumbnail" . substr($key, -1)];

                        $thumbnailExtension = explode("/", $originalThumbnailFile['type']);
                        $thumbnailFileName = uniqid() . time() . '.' . end($thumbnailExtension);
                        Storage::put('public/FormResponses/media/thumbnail/' . $thumbnailFileName, file_get_contents($originalThumbnailFile['tmp_name']));
                    }

                    $attachmentCreated = FormMediaAttachments::create([
                        'name' => $mediaFileName,
                        'form_response_id' => $form->id,
                        'type' => $fileExtension[0],
                        'file_extension' => end($fileExtension),
                        'size_in_kb' => ceil($file['size'] / 1024),
                        'thumbnail' => $thumbnailFileName
                    ]);
                }
            }

            $data = [];
            $data['formType'] = FormType::find($form_type)->name;
            $data['images'] = $imageOnlyAttachments;

            $formData =  json_decode($form_data, true);

            $data = array_merge($data, $formData);

            if ($form_type == 3) {

                if (!count($imageOnlyAttachments)) {
                    return $this->sendResultJSON("2", "Signature Not Found after saving the attachments");
                }

                $data['signature'] = $imageOnlyAttachments[0];
                $pdf = PDF::loadView('resident-move-in-summary', $data);
            }
            if ($form_type == 1) {

                $data['followUp_done_by'] = !empty($follow_up_assigned_to) ? User::find($follow_up_assigned_to)->name : null;

                $pdf = PDF::loadView('demo-form-copy-backup', $data);
            }
            if ($form_type == 2) {

                // $data = [];

                // $data['formType'] = FormType::find($form_type)->name;
                // $data['images'] = $imageOnlyAttachments;
                $printFormData =  json_decode($form_data, true);

                $pdf = PDF::loadView('temp-form-template', $printFormData);
            }

            $content = $pdf->download()->getOriginalContent();

            Storage::put('public/FormResponses/' . $uniqueFileName, $content);

            $formData = json_decode($form_data, true);

            // this if block is for fom type = 1

            if (
                array_key_exists("followUp_issue", $formData)
                || array_key_exists("followUp_findings", $formData)
                || array_key_exists("followUp_action_plan", $formData)
                || array_key_exists("followUp_possible_solutions", $formData)
                || array_key_exists("followUp_examine_result", $formData)
            ) {
                if (
                    $formData["followUp_issue"] ||
                    $formData["followUp_findings"] ||
                    $formData["followUp_action_plan"] ||
                    $formData["followUp_possible_solutions"] ||
                    $formData["followUp_examine_result"]
                ) {
                    $form->is_follow_up_incomplete = 0;
                } else {
                    $form->is_follow_up_incomplete = 1;
                }
            } else {
                $form->is_follow_up_incomplete = 1;
            }

            // this is for form type is other than 1

            if (in_array($form_type, [2, 3])) {
                $form->is_follow_up_incomplete = 0;
            }

            $form->save();


            return $this->sendResultJSON("1", "Successfully Submitted", array("submitted_form_id" => $form->id, 'form_link' => Storage::url('public/FormResponses/' . $uniqueFileName), 'media_links' => $mediaLinks, 'isFollowUpIncomplete' => $form->is_follow_up_incomplete));
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function editGeneratedFormResponsePhase1(Request $request)
    {

        // this will for form type 1,2

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
            $follow_up_assigned_to = $request->input('follow_up_assigned_to');
            $files = $_FILES;



            // $uniqueFileName = uniqid() . time() . '.pdf';

            $existingFormResponse = FormResponse::find($form_id);

            if ($existingFormResponse->form_type_id == 3) { // 'file' is signature

                if (array_key_exists('file', $files)) {
                    return $this->editGeneratedFormResponseForResidentMoveInSummaryReport($request, $files);
                } else {
                    return $this->sendResultJSON("0", "Signature is not sent");
                }
            }

            if (!$existingFormResponse) {
                return $this->sendResultJSON("0", "Form with This Id is not exist");
            }

            if ($existingFormResponse->file_name) {

                Storage::delete('public/FormResponses/' . $existingFormResponse->file_name);
            }



            $existingFormResponse->form_response = json_decode($form_data, true);
            // $existingFormResponse->file_name = $uniqueFileName;

            $formData = json_decode($form_data, true);

            // form type == 1

            if (
                array_key_exists("followUp_issue", $formData)
                || array_key_exists("followUp_findings", $formData)
                || array_key_exists("followUp_action_plan", $formData)
                || array_key_exists("followUp_possible_solutions", $formData)
                || array_key_exists("followUp_examine_result", $formData)
            ) {
                if (
                    $formData["followUp_issue"] ||
                    $formData["followUp_findings"] ||
                    $formData["followUp_action_plan"] ||
                    $formData["followUp_possible_solutions"] ||
                    $formData["followUp_examine_result"]
                ) {
                    $existingFormResponse->is_follow_up_incomplete = 0;
                } else {
                    $existingFormResponse->is_follow_up_incomplete = 1;
                }
            } else {
                $existingFormResponse->is_follow_up_incomplete = 1;
            }

            // form type != 1

            if (in_array($existingFormResponse->form_type_id, [2, 3])) {
                $existingFormResponse->is_follow_up_incomplete = 0;
            }

            $existingFormResponse->follow_up_assigned_to = (!empty($follow_up_assigned_to) && $existingFormResponse->form_type_id == 1) ? $follow_up_assigned_to : 0;

            $existingFormResponse->save();

            // $formData =  (array)json_decode($form_data,true); 
            // $formDataArray = json_decode($formData[0],true);

            // $data = [];
            // $data['formType'] = FormType::find($existingFormResponse->form_type_id)->name;
            // $data['data'] =json_decode($form_data,true);
            // $data['images'] = [];

            // $pdf = PDF::loadView('form-template', $data);
            // $content = $pdf->download()->getOriginalContent();

            // Storage::put('public/FormResponses/'.$uniqueFileName,$content);

            $newLink = $this->regenerateFormResponsePhase1($form_id);




            return $this->sendResultJSON("1", "Successfully Submitted", array('new_form_link' => $newLink, 'isFollowUpIncomplete' => $existingFormResponse->is_follow_up_incomplete));
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function editGeneratedFormResponseForResidentMoveInSummaryReport(Request $request, $files)
    { // form type == 3

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

            $existingFormResponse = FormResponse::find($form_id);

            if (!$existingFormResponse) {
                return $this->sendResultJSON("0", "Form with This Id is not exist");
            }

            if ($existingFormResponse->file_name) {

                Storage::delete('public/FormResponses/' . $existingFormResponse->file_name);
            }



            $existingFormResponse->form_response = json_decode($form_data, true);
            // $existingFormResponse->file_name = $uniqueFileName;

            $formData = json_decode($form_data, true);

            if (in_array($existingFormResponse->form_type_id, [2, 3])) {
                $existingFormResponse->is_follow_up_incomplete = 0;
            }

            $existingFormResponse->save();

            // $formData =  (array)json_decode($form_data,true); 
            // $formDataArray = json_decode($formData[0],true);

            // $data = [];
            // $data['formType'] = FormType::find($existingFormResponse->form_type_id)->name;
            // $data['data'] =json_decode($form_data,true);
            // $data['images'] = [];

            // $pdf = PDF::loadView('form-template', $data);
            // $content = $pdf->download()->getOriginalContent();

            // Storage::put('public/FormResponses/'.$uniqueFileName,$content);

            $newLink = $this->regenerateFormUiResponseForResidentMoveInSummary($form_id, $files);




            return $this->sendResultJSON("1", "Successfully Submitted", array('new_form_link' => $newLink, 'isFollowUpIncomplete' => $existingFormResponse->is_follow_up_incomplete));
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function regenerateFormUiResponseForResidentMoveInSummary($formId, $files)
    {

        $mediaLinks = [];

        $uniqueFileName = uniqid() . time() . '.pdf';

        $existingFormResponse = FormResponse::find($formId);

        $existingFormResponse->file_name = $uniqueFileName;

        $existingFormResponse->save();

        foreach ($files as $key => $file) {

            $fileExtension = explode("/", $file['type']);

            if ($fileExtension[0] == 'image') {

                $mediaFileName = uniqid() . time() . '.' . end($fileExtension);
                Storage::put('public/FormResponses/media/' . $mediaFileName, file_get_contents($file['tmp_name']));
                $mediaLinks[] = Storage::url('public/FormResponses/media/' . $mediaFileName);

                FormMediaAttachments::where(['form_response_id' => $existingFormResponse->id])->delete();

                $attachmentCreated = FormMediaAttachments::create([
                    'name' => $mediaFileName,
                    'form_response_id' => $existingFormResponse->id,
                    'type' => $fileExtension[0],
                    'file_extension' => end($fileExtension),
                    'size_in_kb' => ceil($file['size'] / 1024),
                    'thumbnail' => null
                ]);
            }
        }

        $data = [];

        $newFormData = $existingFormResponse->form_response;

        $data =  $newFormData;
        $data['signature'] = $mediaLinks[0];

        $pdf = null;

        $pdf = PDF::loadView('resident-move-in-summary', $data);

        if (empty($pdf)) {
            return $this->sendResultJSON("0", "Unknown Form Type Id in Edit Form Response:-" . $existingFormResponse->form_type_id);
        }

        $content = $pdf->download()->getOriginalContent();

        Storage::put('public/FormResponses/' . $uniqueFileName, $content);

        return Storage::url('public/FormResponses/' . $uniqueFileName);
    }

    public function regenerateFormResponsePhase1($formId)
    {

        try {

            $uniqueFileName = uniqid() . time() . '.pdf';

            $existingFormResponse = FormResponse::find($formId);

            if ($existingFormResponse->file_name) {

                Storage::delete('public/FormResponses/' . $existingFormResponse->file_name);
            }

            $existingFormResponse->file_name = $uniqueFileName;

            $existingFormResponse->save();

            // $formData =  (array)json_decode($form_data,true); 
            // $formDataArray = json_decode($formData[0],true);

            $results = FormMediaAttachments::where([
                'form_response_id' => $formId,
                'type' => 'image'
            ])->get();

            $images = [];

            foreach ($results as $attachment) {
                $images[] = Storage::url('public/FormResponses/media/' . $attachment['name']);
            }

            $data = [];

            $data['formType'] = FormType::find($existingFormResponse->form_type_id)->name;
            $data['images'] = $images;
            $newFormData = $existingFormResponse->form_response;

            $data = array_merge($data, $newFormData);

            $pdf = null;

            if ($existingFormResponse->form_type_id == 3) {
                $pdf = PDF::loadView('resident-move-in-summary', $data);
            }
            if ($existingFormResponse->form_type_id == 1) {

                $data['followUp_done_by'] = $existingFormResponse->follow_up_assigned_to ? User::find($existingFormResponse->follow_up_assigned_to)->name : null;
                $pdf = PDF::loadView('demo-form-copy-backup', $data);
            }
            if ($existingFormResponse->form_type_id == 2) {
                $pdf = PDF::loadView('temp-form-template', $data);
            }

            if (empty($pdf)) {
                return $this->sendResultJSON("0", "Unknown Form Type Id in Edit Form Response:-" . $existingFormResponse->form_type_id);
            }

            $content = $pdf->download()->getOriginalContent();

            Storage::put('public/FormResponses/' . $uniqueFileName, $content);

            return Storage::url('public/FormResponses/' . $uniqueFileName);
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", "Edit PDF Method:- " . $e->getMessage());
        }
    }

    public function addAttachmentsToExistingFormPhase1(Request $request)
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


            foreach ($files as $key => $file) {

                $thumbnailFileName = null;


                if (substr($key, 0, -1) != 'thumbnail') { // remove the trailing 1,2 .....

                    $fileExtension = explode("/", $file['type']);
                    $mediaFileName = uniqid() . time() . '.' . end($fileExtension);
                    Storage::put('public/FormResponses/media/' . $mediaFileName, file_get_contents($file['tmp_name']));
                    $mediaLinks[] = Storage::url('public/FormResponses/media/' . $mediaFileName);

                    if ($fileExtension[0] == 'image') {
                        $imageOnlyAttachments[] = Storage::url('public/FormResponses/media/' . $mediaFileName);
                    }

                    if (array_key_exists("thumbnail" . substr($key, -1), $files) && $fileExtension[0] == 'video') {

                        $originalThumbnailFile = $files["thumbnail" . substr($key, -1)];

                        $thumbnailExtension = explode("/", $originalThumbnailFile['type']);
                        $thumbnailFileName = uniqid() . time() . '.' . end($thumbnailExtension);
                        Storage::put('public/FormResponses/media/thumbnail/' . $thumbnailFileName, file_get_contents($originalThumbnailFile['tmp_name']));
                    }

                    $attachmentCreated = FormMediaAttachments::create([
                        'name' => $mediaFileName,
                        'form_response_id' => $formId,
                        'type' => $fileExtension[0],
                        'file_extension' => end($fileExtension),
                        'size_in_kb' => ceil($file['size'] / 1024),
                        'thumbnail' => $thumbnailFileName
                    ]);
                }
            }

            $results = FormMediaAttachments::where([
                'form_response_id' => $formId,
            ])->orderBy('id', 'DESC')->get();

            $attachments = [];

            foreach ($results as $attachment) {
                $attachments[] = $attachment;
            }

            $newLink = $this->regenerateFormResponsePhase1($formId);

            return $this->sendResultJSON("1", "Attachments Uploaded Successfully", array("new_form_link" => $newLink, "attachments" => $attachments));
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function deleteFormAttachmentPhase1(Request $request)
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

            FormMediaAttachments::where(['id' => $attachmentId, 'form_response_id' => $formId])->delete();

            $attachments = FormMediaAttachments::where('form_response_id', $formId)->orderBy('id', 'DESC')->get();

            $newLink = $this->regenerateFormResponsePhase1($formId);

            return $this->sendResultJSON("1", "Attachment Deleted Successfully", array("newLink" => $newLink, "attachments" => $attachments));
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function printOrderDataTemp(Request $request)
    {

        $date = $request->input('date');
        $room_name = intval($request->input('room_name'));
        $meal_type = $request->input('meal_type');

        $instruction = "";
        $food_texture = "";
        $resident_name = "";
        $breakfast = $lunch = $dinner = array();

        $finalData = [];

        if ($date != "" && $room_name != "") {

            $preference_details = array();

            $preferences = ItemPreference::all();
            foreach (count($preferences) > 0 ? $preferences : array() as $p) {
                $preference_details[$p->id] = array("name" => $p->pname, "name_cn" => ($p->pname_cn != null ? $p->pname_cn : $p->pname));
            }

            $category_details = array();

            $categoryDetails = CategoryDetail::all();
            foreach (count($categoryDetails) > 0 ? $categoryDetails : array() as $cd) {


                if ($cd->type == 1) {

                    $category_details["breakfast"][] = $cd->id;
                }

                if ($cd->type == 2) {

                    $category_details["lunch"][] = $cd->id;
                }

                if ($cd->type == 3) {

                    $category_details["dinner"][] = $cd->id;
                }
            }

            // print_r($category_details);die;

            $roomIds = RoomDetail::select('id', 'room_name')->where("room_name", 'like', '%' . $room_name . '%')->get();

            $allowedRoomIds = [];

            foreach ($roomIds as $roomId) {

                $allowedRoomIds[$roomId->id] = $roomId->room_name;
            }

            $ordersEncountered = [];

            $combinedItemsData = [];


            $order_data = OrderDetail::where("date", $date)->orderBy("id", "asc")->get();

            foreach (count($order_data) > 0 ? $order_data : array() as $o) {

                $breakfast = $lunch = $dinner = array();

                if (array_key_exists($meal_type, $category_details)) {

                    if ($o->itemData) {

                        if (!in_array($o->itemData->categoryData->type, $category_details[$meal_type])) {

                            continue;
                        }
                    }
                }

                if (!array_key_exists($o->room_id, $allowedRoomIds)) {
                    continue;
                }

                $preference_array = array();
                $option_details = "";

                $room_id = $o->room_id;



                if (isset($o->itemData) && isset($o->itemData->categoryData)) {

                    // if (in_array($o->id , $ordersEncountered)){
                    //     continue;
                    // }

                    // $ordersEncountered[] = $o->id;

                    $cat_data = $o->itemData->categoryData;
                    $type = intval($cat_data->type);
                    if ($o->item_options != "") {
                        $option_data = ItemOption::select("option_name")->where("id", $o->item_options)->first();
                        if ($option_data) {
                            $option_details = $option_data->option_name;
                        }
                    }


                    if ($o->preference != "") {
                        $c_preferences = explode(",", $o->preference);
                        foreach (count($c_preferences) > 0 ? $c_preferences : array() as $cp) {
                            $cp = intval($cp);
                            if ($preference_details[$cp]) {
                                array_push($preference_array, $preference_details[$cp]['name']);
                            }
                        }
                    }

                    $o->cat_id = intval($o->itemData->categoryData->id);
                    $data = array("category" => (intval($cat_data->parent_id) == 0 ? $cat_data->cat_name : ($cat_data->catParentId ? $cat_data->catParentId->cat_name : "")), "sub_cat" => (intval($cat_data->parent_id) == 0 ? "" : $cat_data->cat_name), "item_name" => $o->itemData->item_name, "quantity" => intval($o->quantity), "options" => $option_details, "preference" => $preference_array, "order_id" => $o->id);
                    if (!in_array(intval($o->itemData->categoryData->id), [2, 7, 10, 13])) { // LUNCH SOUP , LUNCH DESSERT, DINNER DESSERT , 13 is deleted

                        if ($type == 1) {
                            array_push($breakfast, $data);
                            $combinedItemsData[$o->room_id]['breakfast'][$o->is_for_guest][] = $data;
                        } else if ($type == 2) {
                            array_push($lunch, $data);
                            $combinedItemsData[$o->room_id]['lunch'][$o->is_for_guest][] = $data;
                        } else {
                            array_push($dinner, $data);
                            $combinedItemsData[$o->room_id]['dinner'][$o->is_for_guest][] = $data;
                        }
                    }
                }

                $spi_data = RoomDetail::selectRaw("special_instrucations,food_texture,resident_name,room_name")->where("id", $room_id)->first();

                if ($spi_data)
                    $instruction = $spi_data->special_instrucations;

                $food_texture = $spi_data ? $spi_data->food_texture : "";

                $resident_name = "NA";

                if ($spi_data) {
                    $resident_name = $spi_data->resident_name != null ? $spi_data->resident_name : "NA";
                }

                if ($o->is_for_guest) {

                    $lastOrder = OrderDetail::where("date", $date)->where("is_for_guest", 1)->where("room_id", $room_id)->orderBy('id', 'DESC')->first();
                } else {

                    $lastOrder = OrderDetail::where("date", $date)->where("room_id", $room_id)->orderBy('id', 'DESC')->first();
                }

                $items = null;

                if ($meal_type == 'breakfast') {

                    $items = $breakfast;
                    // $items = $combinedItemsData[$room_id]['breakfast'][$o->is_for_guest];

                } else if ($meal_type == 'lunch') {

                    $items = $lunch;
                } else if ($meal_type == 'dinner') {

                    $items = $dinner;
                }


                // $finalData[] = [
                //         "Items" => $items,
                //         'special_instruction' => $instruction, 
                //         'food_texture' => $food_texture,
                //         'resident_name' => $resident_name ,
                //         'is_brk_tray_service' => $lastOrder ? $lastOrder->is_brk_tray_service : 0 , 
                //         'is_lunch_tray_service' => $lastOrder ? $lastOrder->is_lunch_tray_service : 0,
                //         'is_dinner_tray_service' => $lastOrder ? $lastOrder->is_dinner_tray_service : 0, 
                //         'is_brk_escort_service' => $lastOrder ? $lastOrder->is_brk_escort_service : 0, 
                //         'is_lunch_escort_service' => $lastOrder ? $lastOrder->is_lunch_escort_service : 0,
                //         'is_dinner_escort_service' => $lastOrder ? $lastOrder->is_dinner_escort_service : 0 ,
                //         'room_id' => $o->is_for_guest ? 0 : $room_id,
                //         'room_name' => $o->is_for_guest ? $spi_data->room_name . " Guest" : $spi_data->room_name,
                //         'is_guest' => $o->is_for_guest

                // ];

                $finalData[] = [
                    'special_instruction' => $instruction,
                    'food_texture' => $food_texture,
                    'resident_name' => $resident_name,
                    'is_brk_tray_service' => $lastOrder ? $lastOrder->is_brk_tray_service : 0,
                    'is_lunch_tray_service' => $lastOrder ? $lastOrder->is_lunch_tray_service : 0,
                    'is_dinner_tray_service' => $lastOrder ? $lastOrder->is_dinner_tray_service : 0,
                    'is_brk_escort_service' => $lastOrder ? $lastOrder->is_brk_escort_service : 0,
                    'is_lunch_escort_service' => $lastOrder ? $lastOrder->is_lunch_escort_service : 0,
                    'is_dinner_escort_service' => $lastOrder ? $lastOrder->is_dinner_escort_service : 0,
                    'room_id' => $o->room_id,
                    'room_name' => $o->is_for_guest ? $spi_data->room_name . " Guest" : $spi_data->room_name,
                    'is_guest' => $o->is_for_guest

                ];
            }
        }

        $dataToBeSent = [];

        $encounteredRoomIds = [];

        foreach ($finalData as $item) {

            if (in_array($item['room_name'], $encounteredRoomIds)) {
                continue;
            }

            $encounteredRoomIds[] = $item['room_name'];

            if (array_key_exists($item['room_id'], $combinedItemsData)) {

                $item['Items'] = $combinedItemsData[$item['room_id']][$meal_type][$item['is_guest']];

                $dataToBeSent[] = $item;
            }
        }

        return $this->sendResultJSON('1', '', array('Data' => $dataToBeSent));
    }

    public function getMoveInSummaryValues()
    {

        $data = MoveInSummaryValues::select('key_param', 'value')->get();

        $finalData = [];

        foreach ($data as $row) {

            $finalData[$row->key_param] = $row->value;
        }

        return $this->sendResultJSON('1', '', array('Data' => $finalData));
    }

    public function logActivity() {}
}
