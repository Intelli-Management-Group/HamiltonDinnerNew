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

        $item_array = [];
        $final_array = [];
        $table_column[0] = [];
        $table_column[1] = [];
        $table_column[2] = [];

        $table_column[0][] = ["title" => 'Room No', "field" => 'room_id', "rowspan" => 3];

        $cat_id = [
            1 => 'BA',
            2 => 'LS',
            7 => 'LD',
           13 => 'DD',
        ];
        $alternative = [4, 8, 11];
        $ab_alternative = [5, 3];
        
        $all_rooms = RoomDetail::where("is_active", 1)->get();
        
        if ($menu_details) {
            $menu_items = $menu_details->items;
            if (is_string($menu_details->items)) {
                $menu_items = json_decode($menu_details->items, true);
            }
            
            // Initialize arrays if they don't exist
            if (!isset($menu_items["breakfast"])) $menu_items["breakfast"] = [];
            if (!isset($menu_items["lunch"])) $menu_items["lunch"] = [];
            if (!isset($menu_items["dinner"])) $menu_items["dinner"] = [];
            
            // Get counts for column spans
            $breakfast_count = count($menu_items["breakfast"]);
            $lunch_count = count($menu_items["lunch"]);
            $dinner_count = count($menu_items["dinner"]);
            
            // Only add columns for meal types that have items
            if ($breakfast_count > 0) {
                $table_column[0][] = ["title" => 'Breakfast', "colspan" => $breakfast_count];
            }
            
            if ($lunch_count > 0) {
                $table_column[0][] = ["title" => 'Lunch', "colspan" => $lunch_count];
            }
            
            if ($dinner_count > 0) {
                $table_column[0][] = ["title" => 'Dinner', "colspan" => $dinner_count];
            }

            $is_first = true;
            $total = [];
            
            // Pre-fetch all order data for the date to avoid N+1 query problem
            $order_data_map = [];
            if (!empty($menu_items["breakfast"]) || !empty($menu_items["lunch"]) || !empty($menu_items["dinner"])) {
                $item_ids = array_merge(
                    $menu_items["breakfast"], 
                    $menu_items["lunch"], 
                    $menu_items["dinner"]
                );
                
                if (!empty($item_ids)) {
                    $all_order_data = OrderDetail::select("room_id", "item_id", "quantity")
                        ->where("date", $search_date)
                        ->whereIn("item_id", $item_ids)
                        ->get();
                    
                    foreach ($all_order_data as $order) {
                        $order_data_map[$order->room_id][$order->item_id] = $order->quantity;
                    }
                }
            }
            
            // Pre-fetch all meal items
            $breakfast_items = [];
            $lunch_items = [];
            $dinner_items = [];
            
            if (!empty($menu_items["breakfast"])) {
                $breakfast_items = ItemDetail::selectRaw("id,item_name,cat_id")
                    ->whereIn("id", $menu_items["breakfast"])
                    ->orderBy("cat_id")->get();
            }
            
            if (!empty($menu_items["lunch"])) {
                $lunch_items = ItemDetail::selectRaw("id,item_name,cat_id")
                    ->whereIn("id", $menu_items["lunch"])
                    ->orderBy("cat_id")->get();
            }
            
            if (!empty($menu_items["dinner"])) {
                $dinner_items = ItemDetail::selectRaw("id,item_name,cat_id")
                    ->whereIn("id", $menu_items["dinner"])
                    ->orderBy("cat_id")->get();
            }
            
            // Process each room only once
            foreach ($all_rooms as $r) {
                $item_array[$r->id] = ["room_id" => $r->room_name];
                $room_id = $r->id;

                // Process breakfast items
                $count = 1;
                foreach ($breakfast_items as $a) {
                    $title = (in_array($a->cat_id, $alternative) ? "B" . $count : $cat_id[$a->cat_id] ?? '');
                    
                    if ($is_first) {
                        $table_column[2][] = ["title" => $title, "tooltip" => $a->item_name, "field" => $title];
                    }
                    
                    // Set default to 0
                    $item_array[$room_id][$title] = 0;
                    
                    // Check if we have order data for this room and item
                    if (isset($order_data_map[$room_id][$a->id])) {
                        $item_array[$room_id][$title] = intval($order_data_map[$room_id][$a->id]);
                    }
                    
                    // Update totals
                    $total[$title] = ($total[$title] ?? 0) + $item_array[$room_id][$title];
                    
                    if (in_array($a->cat_id, $alternative)) $count++;
                }
                
                // Process lunch items
                $count1 = 1;
                $ab_count = 'A';
                foreach ($lunch_items as $a) {
                    $title = (in_array($a->cat_id, $alternative) ? "L" . $count1 : 
                            (in_array($a->cat_id, $ab_alternative) ? "L" . $ab_count : $cat_id[$a->cat_id] ?? ''));
                    
                    if ($is_first) {
                        $table_column[2][] = ["title" => $title, "tooltip" => $a->item_name, "field" => $title];
                    }
                    
                    // Set default to 0
                    $item_array[$room_id][$title] = 0;
                    
                    // Check if we have order data for this room and item
                    if (isset($order_data_map[$room_id][$a->id])) {
                        $item_array[$room_id][$title] = intval($order_data_map[$room_id][$a->id]);
                    }
                    
                    // Update totals
                    $total[$title] = ($total[$title] ?? 0) + $item_array[$room_id][$title];
                    
                    if (in_array($a->cat_id, $alternative)) $count1++;
                    if (in_array($a->cat_id, $ab_alternative)) $ab_count = 'B';
                }
                
                // Process dinner items
                $count2 = 1;
                $ab_count = 'A';
                foreach ($dinner_items as $a) {
                    $title = (in_array($a->cat_id, $alternative) ? "D" . $count2 : 
                            (in_array($a->cat_id, $ab_alternative) ? "D" . $ab_count : $cat_id[$a->cat_id] ?? ''));
                    
                    if ($is_first) {
                        $table_column[2][] = ["title" => $title, "tooltip" => $a->item_name, "field" => $title];
                    }
                    
                    // Set default to 0
                    $item_array[$room_id][$title] = 0;
                    
                    // Check if we have order data for this room and item
                    if (isset($order_data_map[$room_id][$a->id])) {
                        $item_array[$room_id][$title] = intval($order_data_map[$room_id][$a->id]);
                    }
                    
                    // Update totals
                    $total[$title] = ($total[$title] ?? 0) + $item_array[$room_id][$title];
                    
                    if (in_array($a->cat_id, $alternative)) $count2++;
                    if (in_array($a->cat_id, $ab_alternative)) $ab_count = 'B';
                }
                
                $final_array[] = $item_array[$r->id];
                $is_first = false;
            }

            // Optimize the total loop using array_map
            if (!empty($total)) {
                $table_column[1] = array_map(
                    function($v) { return ["title" => $v]; },
                    $total
                );
            }
        }
        
        return json_encode([
            "result" => ["rows" => $final_array], 
            "columns" => $table_column, 
            "total" => empty($total) ? NULL : $total
        ]);
    }

}