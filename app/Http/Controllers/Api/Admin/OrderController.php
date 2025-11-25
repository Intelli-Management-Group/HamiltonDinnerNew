<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;

use App\Models\MenuDetail;
use App\Models\RoomDetail;
use App\Models\ItemDetail;
use App\Models\OrderDetail;

class OrderController extends Controller
{
    // Meal/category constants
    private const CAT_ID = [
        1 => 'BA',
        2 => 'LS',
        7 => 'LD',
        13 => 'DD',
    ];
    private const ALTERNATIVE = [4, 8, 11];
    private const AB_ALTERNATIVE = [5, 3];

    private const PREFIX_MEAL = [
        'B' => 'breakfast',
        'L' => 'lunch',
        'D' => 'dinner',
    ];

    /**
     * Retrieve item details by their IDs.
     * 
     * @param array $item_ids
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function retrieveItemDetails($item_ids) {
        return ItemDetail::selectRaw("id,item_name,cat_id")
            ->whereIn("id", $item_ids)
            ->orderBy("cat_id")->get();
    }

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

            $is_first = true;
            $total = [];
            
            // Pre-fetch all order data for the date to avoid N+1 query problem
            $order_data_map = [];
            $item_ids = array_merge(
                $menu_items["breakfast"], 
                $menu_items["lunch"], 
                $menu_items["dinner"]
            );

            if (!empty($item_ids)) {
                $all_order_data = OrderDetail::select("room_id", "item_id", "quantity", "is_for_guest")
                    ->where("date", $search_date)
                    ->whereIn("item_id", $item_ids)
                    ->get();
                
                foreach ($all_order_data as $order) {
                    $order_data_map[$order->room_id . ($order->is_for_guest ? " G" : "")][$order->item_id] = $order->quantity;
                }
            }
            
            // Pre-fetch all meal items
            $breakfast_items = [];
            $lunch_items = [];
            $dinner_items = [];
            
            if (!empty($menu_items["breakfast"])) {
                $breakfast_items = $this->retrieveItemDetails($menu_items["breakfast"]);
            }
            
            if (!empty($menu_items["lunch"])) {
                $lunch_items = $this->retrieveItemDetails($menu_items["lunch"]);
            }
            
            if (!empty($menu_items["dinner"])) {
                $dinner_items = $this->retrieveItemDetails($menu_items["dinner"]);
            }
            
            // Process each room only once
            foreach ($all_rooms as $r) {
                $item_array[$r->id] = [
                    "room_id" => $r->id,
                    "room_name" => $r->room_name,
                    "has_special_ins" => $r->special_instrucations != null ? 1 : 0,
                    "has_breakfast_order" => 0,
                    "has_lunch_order" => 0,
                    "has_dinner_order" => 0,
                    "is_for_guest" => 0,
                ];

                $item_array[$r->id." G"] = [
                    "room_id" => $r->id,
                    "room_name" => $r->room_name." G",
                    "has_special_ins" => $r->special_instrucations != null ? 1 : 0,
                    "has_breakfast_order" => 0,
                    "has_lunch_order" => 0,
                    "has_dinner_order" => 0,
                    "is_for_guest" => 1,
                ];

                $room_id = $r->id;
                $add_guest = false;

                    // DRY: process all meal items with a helper
                    $processMealItems = function($items, $mealPrefix, &$count, &$ab_count, &$cat_id_map, $is_guest, &$add_guest) use (
                        $room_id,
                        &$item_array,
                        &$order_data_map,
                        &$total,
                        &$table_column,
                        $is_first,
                    ) {
                        foreach ($items as $a) {

                            // Set to track unique items per category
                            if (array_key_exists($a->cat_id, self::CAT_ID)) {
                                $cat_id_map[$a->cat_id][$a->id] = true;
                            }

                            if ($mealPrefix === 'B') {
                                $title = (
                                    in_array($a->cat_id, self::ALTERNATIVE) ? 
                                    $mealPrefix . $count 
                                    : (
                                        array_key_exists($a->cat_id, self::CAT_ID) ?
                                        self::CAT_ID[$a->cat_id] . (
                                            count($cat_id_map[$a->cat_id]) > 1 ?
                                            count($cat_id_map[$a->cat_id]) : ''
                                        ) : ''
                                    )
                                );
                            } else {
                                $title = (
                                    in_array($a->cat_id, self::ALTERNATIVE) ?
                                    $mealPrefix . $count
                                    : (
                                        in_array($a->cat_id, self::AB_ALTERNATIVE) ?
                                        $mealPrefix . $ab_count
                                        : (
                                            array_key_exists($a->cat_id, self::CAT_ID) ?
                                            self::CAT_ID[$a->cat_id] . (
                                                count($cat_id_map[$a->cat_id]) > 1 ?
                                                count($cat_id_map[$a->cat_id]) : ''
                                            ) : ''
                                        )
                                    )
                                );
                            }
                            if ($is_first) {
                                $table_column[2][$title] = ["title" => $title, "tooltip" => $a->item_name, "field" => $title];
                            }

                            $item_array[$room_id.($is_guest ? " G" : "")][$title] = 0;

                            if (isset($order_data_map[$room_id.($is_guest ? " G" : "")][$a->id])) {
                                $item_array[$room_id.($is_guest ? " G" : "")][$title] = intval($order_data_map[$room_id.($is_guest ? " G" : "")][$a->id]);

                                // Mark that this room has an order for this meal
                                $item_array[$room_id.($is_guest ? " G" : "")]["has_".self::PREFIX_MEAL[$mealPrefix]."_order"] = 1;

                                if ($is_guest) {
                                    $add_guest = true;
                                }
                            }
                            $total[$title] = ($total[$title] ?? 0) + $item_array[$room_id.($is_guest ? " G" : "")][$title];

                            if (in_array($a->cat_id, self::ALTERNATIVE)) $count++;
                            if ($mealPrefix !== 'B' && in_array($a->cat_id, self::AB_ALTERNATIVE)) $ab_count = 'B';
                        }
                    };

                    foreach ([false, true] as $is_guest) {
                        // Process breakfast
                        $count = 1;
                        $ab_count = 'A';
                        $cat_id_map = array_fill_keys(array_keys(self::CAT_ID), []);
                        $processMealItems($breakfast_items, 'B', $count, $ab_count, $cat_id_map, $is_guest, $add_guest);

                        // Process lunch
                        $count = 1;
                        $ab_count = 'A';
                        $cat_id_map = array_fill_keys(array_keys(self::CAT_ID), []);
                        $processMealItems($lunch_items, 'L', $count, $ab_count, $cat_id_map, $is_guest, $add_guest);

                        // Process dinner
                        $count = 1;
                        $ab_count = 'A';
                        $cat_id_map = array_fill_keys(array_keys(self::CAT_ID), []);
                        $processMealItems($dinner_items, 'D', $count, $ab_count, $cat_id_map, $is_guest, $add_guest);

                        $is_first = false;
                    }

                    $final_array[] = $item_array[$r->id];
                    if ($add_guest) {
                        $final_array[] = $item_array[$r->id." G"];
                    }
            }

            // Optimize the total loop using array_map
            if (!empty($total)) {
                $table_column[1] = array_map(
                    function($v) { return ["title" => $v]; },
                    $total
                );
            }

            // Only add columns for meal types that have items
            $breakfast_count = 0;
            $lunch_count = 0;
            $dinner_count = 0;

            foreach ($total as $key => $value) {
                if (strpos($key, 'B') === 0) $breakfast_count++;
                else if (strpos($key, 'L') === 0) $lunch_count++;
                else if (strpos($key, 'D') === 0) $dinner_count++;
            }

            if ($breakfast_count > 0) {
                $table_column[0][] = ["title" => 'Breakfast', "colspan" => $breakfast_count];
            }
            if ($lunch_count > 0) {
                $table_column[0][] = ["title" => 'Lunch', "colspan" => $lunch_count];
            }
            if ($dinner_count > 0) {
                $table_column[0][] = ["title" => 'Dinner', "colspan" => $dinner_count];
            }
        }

        $table_column[2] = array_values($table_column[2]);

        $menu_data = MenuDetail::select("date")
            ->orderBy("date", "desc")
            ->first();
        $last_date = $menu_data?->date;

        $finalData = [
            "result" => ["rows" => $final_array], 
            "columns" => $table_column, 
            "total" => empty($total) ? NULL : $total, 
            "last_menu_date" => $last_date
        ];
        
        return $this->sendResultJSON('1', '', $finalData);
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
        $tooltips_array = [];
        $final_array = [];
        $table_column[0] = [];
        $table_column[1] = [];
        $table_column[2] = [];

        $table_column[0][] = ["title" => 'Room No', "field" => 'room_id', "rowspan" => 3];

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

            $is_first = true;
            
            if ($menu_details) {
                $menu_items = $menu_details->items;
                if (is_string($menu_details->items)) {
                    $menu_items = json_decode($menu_details->items, true);
                }

                // Initialize arrays if they don't exist
                if (!isset($menu_items["breakfast"])) $menu_items["breakfast"] = [];
                if (!isset($menu_items["lunch"])) $menu_items["lunch"] = [];
                if (!isset($menu_items["dinner"])) $menu_items["dinner"] = [];

                // Pre-fetch all order data for the date to avoid N+1 query problem
                $order_data_map = [];
                $item_ids = array_merge(
                    $menu_items["breakfast"], 
                    $menu_items["lunch"], 
                    $menu_items["dinner"]
                );

                if (!empty($item_ids)) {
                    $all_order_data = OrderDetail::select("room_id", "item_id", "quantity", "is_for_guest")
                        ->where("date", $search_date)
                        ->whereIn("item_id", $item_ids)
                        ->get();

                    foreach ($all_order_data as $order) {
                        $order_data_map[$order->room_id . ($order->is_for_guest ? ' G' : '')][$order->item_id] = $order->quantity;
                    }
                }

                // Pre-fetch all meal items
                $breakfast_items = [];
                $lunch_items = [];
                $dinner_items = [];

                if (!empty($menu_items["breakfast"])) {
                    $breakfast_items = $this->retrieveItemDetails($menu_items["breakfast"]);
                }

                if (!empty($menu_items["lunch"])) {
                    $lunch_items = $this->retrieveItemDetails($menu_items["lunch"]);
                }

                if (!empty($menu_items["dinner"])) {
                    $dinner_items = $this->retrieveItemDetails($menu_items["dinner"]);
                }

                foreach ($all_rooms as $r) {
                    $curr_item_array[$r->id] = [
                        "room_id" => $r->id,
                        "room_name" => $r->room_name,
                        "has_special_ins" => $r->special_instrucations != null ? 1 : 0,
                    ];

                    $curr_item_array[$r->id." G"] = [
                        "room_id" => $r->id,
                        "room_name" => $r->room_name." G",
                        "has_special_ins" => $r->special_instrucations != null ? 1 : 0,
                    ];

                    $room_id = $r->id;
                    $add_guest = false;

                    // DRY: process all meal items with a helper
                    $processMealItemsRange = function($items, $mealPrefix, &$count, &$ab_count, &$cat_id_map, $is_guest, &$add_guest) use (
                        $room_id,
                        &$curr_item_array,
                        &$tooltips_array,
                        &$order_data_map,
                        &$total,
                        $is_first,
                        $search_date,
                    ) {
                        foreach ($items as $a) {
                            $room_title = $room_id . ($is_guest ? ' G' : '');

                            // Set to track unique items per category
                            if (array_key_exists($a->cat_id, self::CAT_ID)) {
                                $cat_id_map[$a->cat_id][$a->id] = true;
                            }

                            if ($mealPrefix === 'B') {
                                $title = (
                                    in_array($a->cat_id, self::ALTERNATIVE) ? 
                                    $mealPrefix . $count 
                                    : (
                                        array_key_exists($a->cat_id, self::CAT_ID) ?
                                        self::CAT_ID[$a->cat_id] . (
                                            count($cat_id_map[$a->cat_id]) > 1 ?
                                            count($cat_id_map[$a->cat_id]) : ''
                                        ) : ''
                                    )
                                );
                            } else {
                                $title = (
                                    in_array($a->cat_id, self::ALTERNATIVE) ?
                                    $mealPrefix . $count
                                    : (
                                        in_array($a->cat_id, self::AB_ALTERNATIVE) ?
                                        $mealPrefix . $ab_count
                                        : (
                                            array_key_exists($a->cat_id, self::CAT_ID) ?
                                            self::CAT_ID[$a->cat_id] . (
                                                count($cat_id_map[$a->cat_id]) > 1 ?
                                                count($cat_id_map[$a->cat_id]) : ''
                                            ) : ''
                                        )
                                    )
                                );
                            }

                            if ($is_first) {
                                if (!array_key_exists($title, $tooltips_array)) {
                                    $tooltips_array[$title] = [
                                        "title" => $title,
                                        "tooltip" => [
                                            $search_date => $a->item_name
                                        ],
                                        "field" => $title
                                    ];
                                }
                                else {
                                    $tooltips_array[$title]["tooltip"][$search_date] = $a->item_name;
                                }
                            }

                            $curr_item_array[$room_title][$title] = ($curr_item_array[$room_title][$title] ?? 0);
                            if (isset($order_data_map[$room_title][$a->id])) {
                                $curr_item_array[$room_title][$title] += intval($order_data_map[$room_title][$a->id]);

                                if ($is_guest) {
                                    $add_guest = true;
                                }
                            }

                            $total[$title] = ($total[$title] ?? 0) + $curr_item_array[$room_title][$title];

                            if (in_array($a->cat_id, self::ALTERNATIVE)) $count++;
                            if ($mealPrefix !== 'B' && in_array($a->cat_id, self::AB_ALTERNATIVE)) $ab_count = 'B';
                        }
                    };

                    foreach ([false, true] as $is_guest) {
                        // Process breakfast
                        $count = 1;
                        $ab_count = 'A';
                        $cat_id_map = array_fill_keys(array_keys(self::CAT_ID), []);
                        $processMealItemsRange($breakfast_items, 'B', $count, $ab_count, $cat_id_map, $is_guest, $add_guest);

                        // Process lunch
                        $count = 1;
                        $ab_count = 'A';
                        $cat_id_map = array_fill_keys(array_keys(self::CAT_ID), []);
                        $processMealItemsRange($lunch_items, 'L', $count, $ab_count, $cat_id_map, $is_guest, $add_guest);

                        // Process dinner
                        $count = 1;
                        $ab_count = 'A';
                        $cat_id_map = array_fill_keys(array_keys(self::CAT_ID), []);
                        $processMealItemsRange($dinner_items, 'D', $count, $ab_count, $cat_id_map, $is_guest, $add_guest);
                    }

                    foreach ($curr_item_array as $row) {
                        $room_name = $row['room_name'];
                        $is_guest = strpos($room_name, ' G') !== false;
                        if (!isset($final_array[$room_name])) {
                            if ($is_guest && !$add_guest) {
                                continue;
                            }
                            $final_array[$room_name] = $row;
                            
                        } else {
                            foreach ($row as $key => $value) {
                                if (!($key === 'room_id' || $key === 'room_name' || $key === 'has_special_ins')) {
                                    if (!isset($final_array[$room_name][$key])) {
                                        $final_array[$room_name][$key] = 0;
                                    }
                                    $final_array[$room_name][$key] += $value;
                                }
                            }
                        }
                    }

                    $curr_item_array = [];
                    $is_first = false;
                }
            }
        }

        // Custom sort function to order keys as required
        // TODO: the room loop above should be optimized to avoid this step
        $meal_order = ['B', 'L', 'D'];
        $item_type_order = [
            'Soup' => ['BS', 'LS', 'DS'],
            'Main' => ['BA', 'LA', 'LB', 'DA', 'DB'],
            'Alternative' => [], // Will match B1, B2, ..., L1, L2, ..., D1, D2, ...
            'Dessert' => ['BD', 'LD', 'DD'],
        ];
        
        $customSort = function(&$arr) use ($meal_order, $item_type_order) {
            uksort($arr, function($a, $b) use ($meal_order, $item_type_order) {
                // Always keep room_id first
                if ($a === 'room_id') return -1;
                if ($b === 'room_id') return 1;
            
                $prefixA = substr($a, 0, 1);
                $prefixB = substr($b, 0, 1);
                $orderA = array_search($prefixA, $meal_order);
                $orderB = array_search($prefixB, $meal_order);
                if ($orderA !== $orderB) return $orderA - $orderB;
            
                // Helper to get item type index
                $getTypeIndex = function($key) use ($item_type_order) {
                    // Soup
                    foreach ($item_type_order['Soup'] as $soup) {
                        if (strpos($key, $soup) === 0) return 0;
                    }
                    // Main
                    foreach ($item_type_order['Main'] as $main) {
                        if (strpos($key, $main) === 0) return 1;
                    }
                    // Alternative (B1, B2, ..., L1, L2, ..., D1, D2, ...)
                    if (preg_match('/^[BLD]\d+$/', $key)) return 2;
                    // Dessert
                    foreach ($item_type_order['Dessert'] as $dessert) {
                        if (strpos($key, $dessert) === 0) return 3;
                    }
                    // Fallback: treat as Main if not matched
                    return 1;
                };
            
                $typeA = $getTypeIndex($a);
                $typeB = $getTypeIndex($b);
                if ($typeA !== $typeB) return $typeA - $typeB;
            
                // If same type, sort alphabetically or numerically as appropriate
                // For Main, sort BA, LA, LB, DA, DB, etc.
                // For Alternative, sort B1, B2, ..., L1, L2, ..., D1, D2, ...
                // For Soup/Dessert, sort by key
            
                // If both are Alternative, sort numerically
                if ($typeA === 2 && $typeB === 2) {
                    $numA = intval(substr($a, 1));
                    $numB = intval(substr($b, 1));
                    return $numA - $numB;
                }
            
                // Otherwise, fallback to string comparison
                return strcmp($a, $b);
            });
        };

        // Apply to each row in $final_array
        foreach ($final_array as &$row) {
            $customSort($row);
        }
        unset($row); // break reference

        // turn $final_array into indexed array
        $final_array = array_values($final_array);
        
        // Apply to $total
        $customSort($total);

        // Only add columns for meal types that have items
        $breakfast_count = 0;
        $lunch_count = 0;
        $dinner_count = 0;

        foreach ($total as $key => $value) {
            if (strpos($key, 'B') === 0) $breakfast_count++;
            else if (strpos($key, 'L') === 0) $lunch_count++;
            else if (strpos($key, 'D') === 0) $dinner_count++;

            $table_column[2][] = $tooltips_array[$key] ?? [];
        }

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

        $menu_data = MenuDetail::select("date")
            ->orderBy("date", "desc")
            ->first();
        $last_date = $menu_data?->date;

        $finalData = [
            "result" => ["rows" => $final_array], 
            "columns" => $table_column, 
            "total" => empty($total) ? NULL : $total,
            "last_menu_date" => $last_date
        ];
        
        return $this->sendResultJSON('1', '', $finalData);
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
            return $this->sendResultJSON('0', 'Please provide either search_date or both start_date and end_date.', []);
        }   
    }
}