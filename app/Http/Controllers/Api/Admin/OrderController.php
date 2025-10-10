<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;

use App\Models\MenuDetail;
use App\Models\RoomDetail;
use App\Models\ItemDetail;
use App\Models\OrderDetail;

class OrderController extends Controller
{
    /**
     * Get room-wise orders for a given single day.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function reportListSingle(Request $request)
    {
        $search_date = $request->input("search_date");
        $menu_details = MenuDetail::where("date", $search_date)->first();

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

        /**
     * Get room-wise orders for a given day.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function reportListRange(Request $request)
    {
        $start_date = $request->input("start_date");
        $end_date = $request->input("end_date");

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

        // init outside of loop to avoid re-initialization
        $breakfast_count = 0;
        $lunch_count = 0;
        $dinner_count = 0;
        $breakfast_longest_day = '';
        $lunch_longest_day = '';
        $dinner_longest_day = '';
        $curr_item_array = [];
        $total = [];
        
        $period = new \DatePeriod(
            new \DateTime($start_date),
            new \DateInterval('P1D'),
            (new \DateTime($end_date))->modify('+1 day') // Make end date inclusive
        );

        $all_rooms = RoomDetail::where("is_active", 1)->get();

        foreach ($period as $date) {
            $search_date = $date->format('Y-m-d');
            $menu_details = MenuDetail::where("date", $search_date)->first();

            $is_first = true;  // Reset for each date (stopgap fix)
            $curr_day_tooltips = [];
            
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
                $breakfast_count = max($breakfast_count, count($menu_items["breakfast"]));
                $lunch_count = max($lunch_count, count($menu_items["lunch"]));
                $dinner_count = max($dinner_count, count($menu_items["dinner"]));

                if ($breakfast_count > count($menu_items["breakfast"])) {
                    $breakfast_count = count($menu_items["breakfast"]);
                    $breakfast_longest_day = $search_date;
                }

                if ($lunch_count > count($menu_items["lunch"])) {
                    $lunch_count = count($menu_items["lunch"]);
                    $lunch_longest_day = $search_date;
                }

                if ($dinner_count > count($menu_items["dinner"])) {
                    $dinner_count = count($menu_items["dinner"]);
                    $dinner_longest_day = $search_date;
                }

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
                    $curr_item_array[$r->id] = ["room_id" => $r->room_name];
                    $room_id = $r->id;

                    // Process breakfast items
                    $count = 1;
                    foreach ($breakfast_items as $a) {
                        $title = (in_array($a->cat_id, $alternative) ? "B" . $count : $cat_id[$a->cat_id] ?? '');

                        if ($is_first) {
                            $curr_day_tooltips[] = ["title" => $title, "tooltip" => $a->item_name, "field" => $title];
                        }

                        // Set default to 0
                        $curr_item_array[$room_id][$title] = ($curr_item_array[$room_id][$title] ?? 0);

                        // Check if we have order data for this room and item
                        if (isset($order_data_map[$room_id][$a->id])) {
                            $curr_item_array[$room_id][$title] += intval($order_data_map[$room_id][$a->id]);
                        }

                        // Update totals
                        $total[$title] = ($total[$title] ?? 0) + $curr_item_array[$room_id][$title];

                        if (in_array($a->cat_id, $alternative)) $count++;
                    }

                    // Process lunch items
                    $count1 = 1;
                    $ab_count = 'A';
                    foreach ($lunch_items as $a) {
                        $title = (in_array($a->cat_id, $alternative) ? "L" . $count1 : 
                                (in_array($a->cat_id, $ab_alternative) ? "L" . $ab_count : $cat_id[$a->cat_id] ?? ''));

                        if ($is_first) {
                            $curr_day_tooltips[] = ["title" => $title, "tooltip" => $a->item_name, "field" => $title];
                        }

                        // Set default to 0 if unset
                        $curr_item_array[$room_id][$title] = ($curr_item_array[$room_id][$title] ?? 0);

                        // Check if we have order data for this room and item
                        if (isset($order_data_map[$room_id][$a->id])) {
                            $curr_item_array[$room_id][$title] += intval($order_data_map[$room_id][$a->id]);
                        }

                        // Update totals
                        $total[$title] = ($total[$title] ?? 0) + $curr_item_array[$room_id][$title];

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
                            $curr_day_tooltips[] = ["title" => $title, "tooltip" => $a->item_name, "field" => $title];
                        }

                        // Set default to 0 if unset
                        $curr_item_array[$room_id][$title] = ($curr_item_array[$room_id][$title] ?? 0);

                        // Check if we have order data for this room and item
                        if (isset($order_data_map[$room_id][$a->id])) {
                            $curr_item_array[$room_id][$title] += intval($order_data_map[$room_id][$a->id]);
                        }

                        // Update totals
                        $total[$title] = ($total[$title] ?? 0) + $curr_item_array[$room_id][$title];

                        if (in_array($a->cat_id, $alternative)) $count2++;
                        if (in_array($a->cat_id, $ab_alternative)) $ab_count = 'B';
                    }

                    foreach ($curr_item_array as $row) {
                        $room_id = $row['room_id'];
                        if (!isset($final_array[$room_id])) {
                            $final_array[$room_id] = $row;
                        } else {
                            foreach ($row as $key => $value) {
                                if ($key !== 'room_id') {
                                    if (!isset($final_array[$room_id][$key])) {
                                        $final_array[$room_id][$key] = intval($value);
                                    } else {
                                    $final_array[$room_id][$key] += intval($value);
                                    }
                                }
                            }
                        }
                    }

                    $is_first = false;
                    $curr_item_array = [];
                }
            }
            $table_column[2][] = $curr_day_tooltips;
        }

        // Custom sort function to order keys as required
        // TODO: the room loop above should be optimized to avoid this step
        $meal_order = ['B', 'L', 'D'];
        $customSort = function(&$arr) use ($meal_order) {
            uksort($arr, function($a, $b) use ($meal_order) {
                if ($a === 'room_id') return -1;
                if ($b === 'room_id') return 1;
                $prefixA = substr($a, 0, 1);
                $prefixB = substr($b, 0, 1);
                $orderA = array_search($prefixA, $meal_order);
                $orderB = array_search($prefixB, $meal_order);
                if ($orderA !== $orderB) return $orderA - $orderB;
            
                $suffixA = substr($a, 1);
                $suffixB = substr($b, 1);
            
                $isAlphaA = ctype_alpha($suffixA);
                $isAlphaB = ctype_alpha($suffixB);
            
                if ($isAlphaA && !$isAlphaB) return -1; // letters before numbers
                if (!$isAlphaA && $isAlphaB) return 1;
                return strcmp($suffixA, $suffixB);
            });
        };

        // Apply to each row in $final_array
        foreach ($final_array as &$row) {
            $customSort($row);
        }
        unset($row); // break reference
        
        // Apply to $total
        $customSort($total);

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

        // Optimize the total loop using array_map
        if (!empty($total)) {
            $table_column[1] = array_map(
                function($v) { return ["title" => $v]; },
                $total
            );
        }
        
        return json_encode([
            "result" => ["rows" => $final_array], 
            "columns" => $table_column, 
            "total" => empty($total) ? NULL : $total
        ]);
    }

    /**
     * Get room-wise orders for a given day or date range.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function reportList(Request $request)
    {
        if ($request->has('start_date') && $request->has('end_date')) {
            return $this->reportListRange($request);
        } elseif ($request->has('search_date')) {
            return $this->reportListSingle($request);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Please provide either search_date or both start_date and end_date.'
            ], 400);
        }   
    }
}