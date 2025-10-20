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
                $all_order_data = OrderDetail::select("room_id", "item_id", "quantity")
                    ->where("date", $search_date)
                    ->whereIn("item_id", $item_ids)
                    ->get();
                
                foreach ($all_order_data as $order) {
                    $order_data_map[$order->room_id][$order->item_id] = $order->quantity;
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
                $item_array[$r->id] = ["room_id" => $r->room_name];
                $room_id = $r->id;

                    // DRY: process all meal items with a helper
                    $processMealItems = function($items, $mealPrefix, &$count, &$ab_count, &$cat_id_map) use (
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
                                $table_column[2][] = ["title" => $title, "tooltip" => $a->item_name, "field" => $title];
                            }
                            $item_array[$room_id][$title] = 0;
                            if (isset($order_data_map[$room_id][$a->id])) {
                                $item_array[$room_id][$title] = intval($order_data_map[$room_id][$a->id]);
                            }
                            $total[$title] = ($total[$title] ?? 0) + $item_array[$room_id][$title];
                            if (in_array($a->cat_id, self::ALTERNATIVE)) $count++;
                            if ($mealPrefix !== 'B' && in_array($a->cat_id, self::AB_ALTERNATIVE)) $ab_count = 'B';
                        }
                    };

                    // Process breakfast
                    $count = 1;
                    $ab_count = 'A';
                    $cat_id_map = array_fill_keys(array_keys(self::CAT_ID), []);
                    $processMealItems($breakfast_items, 'B', $count, $ab_count, $cat_id_map);

                    // Process lunch
                    $count = 1;
                    $ab_count = 'A';
                    $cat_id_map = array_fill_keys(array_keys(self::CAT_ID), []);
                    $processMealItems($lunch_items, 'L', $count, $ab_count, $cat_id_map);

                    // Process dinner
                    $count = 1;
                    $ab_count = 'A';
                    $cat_id_map = array_fill_keys(array_keys(self::CAT_ID), []);
                    $processMealItems($dinner_items, 'D', $count, $ab_count, $cat_id_map);

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
                    $all_order_data = OrderDetail::select("room_id", "item_id", "quantity")
                        ->where("date", $search_date)
                        ->whereIn("item_id", $item_ids)
                        ->get();

                    foreach ($all_order_data as $order) {
                        $order_data_map[$order->room_id][$order->item_id] = $order->quantity;
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
                    $curr_item_array[$r->id] = ["room_id" => $r->room_name];
                    $room_id = $r->id;

                    // DRY: process all meal items with a helper
                    $processMealItemsRange = function($items, $mealPrefix, &$count, &$ab_count, &$cat_id_map) use (
                        $room_id,
                        &$curr_item_array,
                        &$order_data_map,
                        &$total,
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

                            $curr_item_array[$room_id][$title] = ($curr_item_array[$room_id][$title] ?? 0);
                            if (isset($order_data_map[$room_id][$a->id])) {
                                $curr_item_array[$room_id][$title] += intval($order_data_map[$room_id][$a->id]);
                            }
                            $total[$title] = ($total[$title] ?? 0) + $curr_item_array[$room_id][$title];
                            if (in_array($a->cat_id, self::ALTERNATIVE)) $count++;
                            if ($mealPrefix !== 'B' && in_array($a->cat_id, self::AB_ALTERNATIVE)) $ab_count = 'B';
                        }
                    };

                    // Process breakfast
                    $count = 1;
                    $ab_count = 'A';
                    $cat_id_map = array_fill_keys(array_keys(self::CAT_ID), []);
                    $processMealItemsRange($breakfast_items, 'B', $count, $ab_count, $cat_id_map);

                    // Process lunch
                    $count = 1;
                    $ab_count = 'A';
                    $cat_id_map = array_fill_keys(array_keys(self::CAT_ID), []);
                    $processMealItemsRange($lunch_items, 'L', $count, $ab_count, $cat_id_map);

                    // Process dinner
                    $count = 1;
                    $ab_count = 'A';
                    $cat_id_map = array_fill_keys(array_keys(self::CAT_ID), []);
                    $processMealItemsRange($dinner_items, 'D', $count, $ab_count, $cat_id_map);

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
                    $curr_item_array = [];
                }
            }
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

            // We have determined that dish names are not needed when considering date range reports
            $table_column[2][] = ["title" => $key, "tooltip" => "Total", "field" => $key];
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