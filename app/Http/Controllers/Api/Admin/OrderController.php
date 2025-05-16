<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;

use App\Models\MenuDetail;
use App\Models\RoomDetail;
use App\Models\ItemDetail;
use App\Models\OrderDetail;

class OrderController extends Controller
{
    public function reportList(Request $request)
    {
        $search_date = $request->input("search_date");
        $menu_details = MenuDetail::where("date", $search_date)->first();
        $item_array = array();
        $final_array = array();
        $table_column[0] = array();
        $table_column[1] = array();
        $table_column[2] = array();
        array_push($table_column[0], array("title" => 'Room No', "field" => 'room_id', "rowspan" => 3));

        $cat_id = array(
            1 => 'BA',
            2 => 'LS',
            7 => 'LD',
            13 => 'DD',
        );
        $alternative = array(4, 8, 11);
        $ab_alternative = array(5, 3);
        $all_rooms = RoomDetail::where("is_active", 1)->get();
        if ($menu_details) {

            $menu_items = $menu_details->items;
            if (is_string($menu_details->items)) {
                $menu_details->items = json_decode($menu_details->items, true);
            }

            // Check if menu items exist for each meal type
            $breakfast_count = !empty($menu_items["breakfast"]) ? count($menu_items["breakfast"]) : 0;
            $lunch_count = !empty($menu_items["lunch"]) ? count($menu_items["lunch"]) : 0;
            $dinner_count = !empty($menu_items["dinner"]) ? count($menu_items["dinner"]) : 0;

            array_push($table_column[0], array("title" => 'Breakfast', "colspan" => $breakfast_count));
            array_push($table_column[0], array("title" => 'Lunch', "colspan" => $lunch_count));
            array_push($table_column[0], array("title" => 'Dinner', "colspan" => $dinner_count));

            $is_first = true;
            $total = array();
            foreach (count($all_rooms) > 0 ? $all_rooms : array() as $r) {
                $item_array[$r->id] = array("room_id" => $r->room_name);

                // Handle breakfast items
                if (!empty($menu_items["breakfast"])) {
                    $all_items = ItemDetail::selectRaw("id,item_name,cat_id")
                        ->whereIn("id", $menu_items["breakfast"])
                        ->orderBy("cat_id")
                        ->get();

                    $count = 1;
                    foreach ($all_items as $a) {
                        $title = (in_array($a->cat_id, $alternative) ? "B" . $count : $cat_id[$a->cat_id]);
                        if ($is_first) {
                            array_push($table_column[2], array("title" => $title, "tooltip" => $a->item_name, "field" => $title));
                        }
                        $item_array[$r->id][$title] = 0;
                        $order_data = OrderDetail::select("quantity")->where("date", $search_date)->where("room_id", $r->id)->where("item_id", $a->id)->first();
                        if ($order_data) {
                            $item_array[$r->id][$title] = intval($order_data->quantity);
                        }
                        if (!isset($total[$title])) {
                            $total[$title] = 0;
                        }
                        $total[$title] += $item_array[$r->id][$title];
                        if (in_array($a->cat_id, $alternative)) $count++;
                    }
                }

                // Handle lunch items
                if (!empty($menu_items["lunch"])) {
                    $count1 = 1;
                    $ab_count = 'A';
                    $all_items = ItemDetail::selectRaw("id,item_name,cat_id")
                        ->whereIn("id", $menu_items["lunch"])
                        ->orderBy("cat_id")
                        ->get();

                    foreach ($all_items as $a) {
                        $title = (in_array($a->cat_id, $alternative) ? "L" . $count1 : (in_array($a->cat_id, $ab_alternative) ? "L" . $ab_count : $cat_id[$a->cat_id]));
                        if ($is_first) {
                            array_push($table_column[2], array("title" => $title, "tooltip" => $a->item_name, "field" => $title));
                        }
                        $item_array[$r->id][$title] = 0;
                        $order_data = OrderDetail::select("quantity")->where("date", $search_date)->where("room_id", $r->id)->where("item_id", $a->id)->first();
                        if ($order_data) {
                            $item_array[$r->id][$title] = intval($order_data->quantity);
                        }
                        if (!isset($total[$title])) {
                            $total[$title] = 0;
                        }
                        $total[$title] += $item_array[$r->id][$title];
                        if (in_array($a->cat_id, $alternative)) $count1++;
                        if (in_array($a->cat_id, $ab_alternative)) $ab_count = 'B';
                    }
                }

                // Handle dinner items
                if (!empty($menu_items["dinner"])) {
                    $count2 = 1;
                    $ab_count = 'A';
                    $all_items = ItemDetail::selectRaw("id,item_name,cat_id")
                        ->whereIn("id", $menu_items["dinner"])
                        ->orderBy("cat_id")
                        ->get();

                    foreach ($all_items as $a) {
                        $title = (in_array($a->cat_id, $alternative) ? "D" . $count2 : (in_array($a->cat_id, $ab_alternative) ? "D" . $ab_count : $cat_id[$a->cat_id]));
                        if ($is_first) {
                            array_push($table_column[2], array("title" => $title, "tooltip" => $a->item_name, "field" => $title));
                        }
                        $item_array[$r->id][$title] = 0;
                        $order_data = OrderDetail::select("quantity")->where("date", $search_date)->where("room_id", $r->id)->where("item_id", $a->id)->first();
                        if ($order_data) {
                            $item_array[$r->id][$title] = intval($order_data->quantity);
                        }
                        if (!isset($total[$title])) {
                            $total[$title] = 0;
                        }
                        $total[$title] += $item_array[$r->id][$title];
                        if (in_array($a->cat_id, $alternative)) $count2++;
                        if (in_array($a->cat_id, $ab_alternative)) $ab_count = 'B';
                    }
                }

                array_push($final_array, $item_array[$r->id]);
                $is_first = false;
            }

            foreach (count($total) > 0 ? $total : array() as $k => $v) {
                array_push($table_column[1], array("title" => $v));
            }
        }
        return json_encode(array(
            "result" => array("rows" => $final_array),
            "columns" => $table_column,
            "total" => empty($total) ? NULL : $total
        ));
    }
}
