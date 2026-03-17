<?php

namespace App\Services\Reports;

use App\Repositories\Contracts\Reports\ChargeReportRepositoryInterface;

class ChargeReportService
{
    public function __construct(
        protected ChargeReportRepositoryInterface $chargeReportRepository
    ) {}

    // MEAL_LOOKUP maps the numeric meal value stored in the DB (1/2/3) to a short string
    // used as part of variable names below (e.g. "brk" → $report_brk_list). It must match
    // the values the SQL query returns in the `meal` column.
    private const MEAL_LOOKUP = [
        1 => 'brk',
        2 => 'lunch',
        3 => 'dinner'
    ];

    // MEAL_ALIASES_REV maps those short strings to the human-readable meal name used in
    // the final response keys (e.g. "brk" → "breakfast").
    private const MEAL_ALIASES_REV = [
        'brk' => 'breakfast',
        'lunch' => 'lunch',
        'dinner' => 'dinner'
    ];

    // SERVICE_LOOKUP defines the fixed columns that appear in every meal table before any
    // item-option columns. T/E/TO/G are always present; item options (O1, O2, …) are added
    // dynamically based on what the SQL query returns.
    private const SERVICE_LOOKUP = [
        'T' => 'Tray Service',
        'E' => 'Escort Service',
        'TO' => 'TakeOut',
        'G' => 'No. Of Guests'
    ];

    public function getChargeReport(string $start_date, string $end_date)
    {
        $is_single_day = ($start_date === $end_date);

        // hint on usage of service class
        // $chargeReportService = new ChargeReportService();

        // populate base items
        $baseItemList = [];
        $baseData = [];
        $baseOptions = [];
        foreach (self::SERVICE_LOOKUP as $key => $value) {
            $baseItemList[$key] = [
                'item_name' => $key,
                'real_item_name' => $value,
            ];

            $baseData[$key] = 0;
            $baseOptions[$key] = [];
        }

        $report_breakfast_list = $report_lunch_list = $report_dinner_list = [];
        $breakfastOptionsList = $lunchOptionsList = $dinnerOptionsList = [];
        $should_show_breakfast_items = $should_show_lunch_items = $should_show_dinner_items = false;

        $meal_rows = $this->chargeReportRepository->runMealReport($start_date, $end_date);

        foreach ($meal_rows as $meal_row) {

            // determine meal
            $meal = $meal_row->meal;
            if (!$meal) continue;

            // to check: T, E, TO, G, itemOptionId
            $to_check = [
                $meal_row->is_tray_service,
                $meal_row->is_escort_service,
                $meal_row->is_takeout_service,
                $meal_row->is_for_guest ? $meal_row->occupancy : 0,
                $meal_row->items
            ];

            // skip if all zero/empty
            if (count(array_filter($to_check, fn($v) => !empty($v))) === 0) continue;
            ${'should_show_'.$meal.'_items'} = true;

            // make new room entry if not exists
            $guest_suffix = $meal_row->is_for_guest ? ' G' : '';
            // Variable-variable syntax: ${'report_'.$meal.'_list'} resolves at runtime to one of
            // $report_breakfast_list, $report_lunch_list, or $report_dinner_list depending on $meal.
            // The same pattern is used throughout this method to avoid repeating identical logic
            // three times. If you need to trace what's happening, substitute the meal name mentally.
            $report_meal_list = &${'report_'.$meal.'_list'};
            $room_name = $meal_row->room_name . $guest_suffix;
            if (!isset($report_meal_list[$room_name])) {
                $report_meal_list[$room_name] = [
                    'room_no' => $room_name,
                    'room_id' => $meal_row->room_id,
                    'is_for_guest' => $meal_row->is_for_guest,
                    'data' => $baseData,
                    'option' => $baseOptions
                ];
            }

            // set up reference
            $meal_data = &$report_meal_list[$room_name]['data'];
            $meal_options = &$report_meal_list[$room_name]['option'];

            // populate quantities
            $meal_data['T'] += $meal_row->is_tray_service;
            $meal_data['E'] += $meal_row->is_escort_service;
            $meal_data['TO'] += $meal_row->is_takeout_service;
            if ( $meal_row->is_for_guest ) {
                $meal_data['G'] += $meal_row->occupancy;
            }

            // options
            if ($meal_row->items) {
                $meal_options_list = &${$meal.'OptionsList'};

                // The SQL query concatenates item-option selections into a single string:
                //   "optionId:optionName:date:itemName:quantity;optionId:optionName:…"
                // e.g. "12:Extra Sauce:2026-02-15:Chicken:1;14:No Salt:2026-02-15:Chicken:2"
                // Each semicolon-separated segment is one item-option for this room/meal.
                foreach (explode(';', $meal_row->items) as $item_entry) {
                    [$option_id, $option_name, $item_date, $item_name, $option_quantity] = explode(':', $item_entry);

                    $item_option_title = 'O'.$option_id;

                    // meal_options_list
                    if (!isset($meal_options_list[$item_option_title])) {
                        $meal_options_list[$item_option_title] = [
                            'item_name' => $item_option_title,
                            'real_item_name' => $option_name,
                            'item_option_id' => $option_id
                        ];
                    }

                    // report_meal_list data
                    // $option_count = $meal_row->is_for_guest ? $meal_row->occupancy : 1;
                    $meal_data[$item_option_title] = ($meal_data[$item_option_title] ?? 0) + $option_quantity;

                    // report_meal_list option
                    if (!isset($meal_options[$item_option_title])) {
                        $meal_options[$item_option_title] = [];
                    }

                    if ($is_single_day) {
                        $meal_options[$item_option_title][$item_name] = [
                            'itemName' => $item_name,
                        ];
                    } else {
                        if (!isset($meal_options[$item_option_title][$item_date])) {
                            $meal_options[$item_option_title][$item_date] = [
                                'date' => $item_date,
                                'items' => [
                                    $item_name => [
                                        'itemName' => $item_name,
                                    ]
                                ]
                            ];
                        } else {
                            $meal_options[$item_option_title][$item_date]['items'][$item_name] = [
                                'itemName' => $item_name
                            ];
                        }
                    }
                }
            }
        }

        // sort options list by keys
        ksort($breakfastOptionsList);
        ksort($lunchOptionsList);
        ksort($dinnerOptionsList);

        $breakfastItemList = $should_show_breakfast_items ? $baseItemList + $breakfastOptionsList : [];
        $lunchItemList = $should_show_lunch_items ? $baseItemList + $lunchOptionsList : [];
        $dinnerItemList = $should_show_dinner_items ? $baseItemList + $dinnerOptionsList : [];

        // backfill all missing options from item list to report list with zero quantity
        foreach (['breakfast', 'lunch', 'dinner'] as $meal) {
            $meal_item_list = &${$meal.'ItemList'};
            $report_meal_list = &${'report_'.$meal.'_list'};

            foreach ($meal_item_list as $item_key => $item_value) {
                foreach ($report_meal_list as &$room_entry) {
                    if (!array_key_exists($item_key, $room_entry['data'])) {
                        $room_entry['data'][$item_key] = 0;
                    }
                    if (!array_key_exists($item_key, $room_entry['option'])) {
                        $room_entry['option'][$item_key] = [];
                    }
                }
            }
        }

        $service_keys = ['T', 'E', 'TO', 'G'];
        $sortOptions = function(&$optionArr) use ($service_keys) {
            uksort($optionArr, function($a, $b) use ($service_keys) {
                $isAService = in_array($a, $service_keys);
                $isBService = in_array($b, $service_keys);
            
                // Service keys always come first, keep their order
                if ($isAService && $isBService) {
                    return array_search($a, $service_keys) - array_search($b, $service_keys);
                }
                if ($isAService) return -1;
                if ($isBService) return 1;
            
                // Both are O keys, sort numerically
                if ($a[0] === 'O' && $b[0] === 'O') {
                    return intval(substr($a, 1)) - intval(substr($b, 1));
                }
            
                // If only one is O, O comes after service keys
                if ($a[0] === 'O') return 1;
                if ($b[0] === 'O') return -1;
            
                // Otherwise, keep original order (or sort alphabetically if needed)
                return 0;
            });
        };

        // Usage: after building each room_entry['option'] array
        foreach (['breakfast', 'lunch', 'dinner'] as $meal) {
            $report_meal_list = &${'report_'.$meal.'_list'};
            foreach ($report_meal_list as &$room_entry) {
                $sortOptions($room_entry['data']);
                $sortOptions($room_entry['option']);
            }
        }

        foreach (['breakfast', 'lunch', 'dinner'] as $meal) {
            // convert item list to indexed array
            $meal_item_list = &${$meal.'ItemList'};
            $meal_item_list = array_values($meal_item_list);

            // convert report meal list to indexed array
            $report_meal_list = &${'report_'.$meal.'_list'};
            $report_meal_list = array_values($report_meal_list);

            foreach ($report_meal_list as &$room_entry) {
                // convert options to indexed array
                foreach ($room_entry['option'] as $option_key => $option_value) {
                    // only process option keys
                    if ($option_key[0] !== 'O') continue;

                    // if not single day, convert date items to indexed array first
                    if (!$is_single_day) {
                        foreach ($option_value as $date_key => $date_value) {
                            $option_value[$date_key]['items'] = array_values($date_value['items']);
                        }
                    }

                    // finally convert option to indexed array
                    $room_entry['option'][$option_key] = array_values($option_value);
                }
            }  
        }

        $finalData = [
            'breakfast_item_list' => array_values($breakfastItemList),
            'report_breakfast_list' => array_values($report_breakfast_list ?? []),
            'lunch_item_list' =>   array_values($lunchItemList),
            'report_lunch_list' => array_values($report_lunch_list ?? []),
            'dinner_item_list' => array_values($dinnerItemList),
            'report_dinner_list' => array_values($report_dinner_list ?? [])
        ];
        return $finalData;
    }

    public function getOrderReport(?string $date, ?int $roomName): array
    {
        $results = $this->chargeReportRepository->runOrderSummaryReport($date, $roomName);

        $data = [];
        foreach ($results as $result) {
            $data[] = [
                'room_number'              => $result->room_name,
                'resident_name'            => $result->resident_name,
                'order_date'               => $result->date,
                'is_for_guest'             => $result->is_for_guest,
                'is_brk_tray_service'      => $result->is_brk_tray_service,
                'is_lunch_tray_service'    => $result->is_lunch_tray_service,
                'is_dinner_tray_service'   => $result->is_dinner_tray_service,
                'is_brk_escort_service'    => $result->is_brk_escort_service,
                'is_lunch_escort_service'  => $result->is_lunch_escort_service,
                'is_dinner_escort_service' => $result->is_dinner_escort_service,
                'is_extra_item'            => empty($result->item_options) ? 0 : 1,
                'room_id'                  => $result->room_id,
                'item_name'                => empty($result->item_options) ? '' : $result->item_name,
                'item_options'             => empty($result->item_options) ? '' : $result->option_name,
                'item_quantity'            => empty($result->is_for_guest) ? 0 : $result->quantity,
            ];
        }

        return ['Data' => $data];
    }
}