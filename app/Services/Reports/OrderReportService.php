<?php

namespace App\Services\Reports;

use App\Repositories\Contracts\CategoryDetailRepositoryInterface;
use App\Repositories\Contracts\ItemDetailRepositoryInterface;
use App\Repositories\Contracts\MenuDetailRepositoryInterface;
use App\Repositories\Contracts\OrderDetailRepositoryInterface;
use App\Repositories\Contracts\RoomDetailRepositoryInterface;

class OrderReportService
{
    // Each menu item belongs to a category (cat_id). The category determines the column
    // title shown in the report. Three mutually exclusive rules apply — checked in order:
    //
    //   alternative    — numeric suffix per meal. The Nth item of this type in a meal
    //                    gets that number: first Lunch Alternative in lunch → "L1", next → "L2".
    //                    Resolved by cat_name from DB: "Lunch Alternative", "Dinner Alternative".
    //
    //   abAlternative  — lettered suffix (lunch/dinner only). First item → "LA"/"DA",
    //                    second → "LB"/"DB". Breakfast items of this type are ignored.
    //                    Resolved by cat_name: "Western/Chinese Lunch/Dinner Entrée".
    //
    //   catId          — fixed two-letter code. Multiple items of the same code get a
    //                    count appended only when there is more than one: "BA", "BA2", …
    //                    Resolved by cat_name: "Western Breakfast"→"BA", "Chinese Breakfast"→"BB".
    //
    // The first character of every column title is always the meal prefix (B/L/D).
    // Items whose cat_id matches none of these roles produce an empty title and
    // are effectively ignored in the column output.
    //
    // IDs are resolved at runtime via getCategoryRoleMappings() so the logic stays
    // correct even when categories are recreated with new auto-increment IDs.

    private const PREFIX_MEAL = [
        'B' => 'breakfast',
        'L' => 'lunch',
        'D' => 'dinner',
    ];

    private ?array $categoryRoleMappings = null;

    private function categoryRoleMappings(): array
    {
        return $this->categoryRoleMappings ??= $this->categoryDetails->getCategoryRoleMappings();
    }

    public function __construct(
        private CategoryDetailRepositoryInterface $categoryDetails,
        private MenuDetailRepositoryInterface     $menuDetails,
        private OrderDetailRepositoryInterface    $orderDetails,
        private RoomDetailRepositoryInterface     $roomDetails,
        private ItemDetailRepositoryInterface     $itemDetails
    ) {}

    /**
     * Build the order report for a single date or a date range.
     *
     * Pass exactly one of:
     *   $date                    — single-day mode
     *   $startDate + $endDate    — date-range mode
     *
     * The two modes share the same loop but differ in what each row contains:
     *   single-day: rows include has_breakfast_order / has_lunch_order / has_dinner_order /
     *               is_for_guest flags; column tooltips are plain item-name strings.
     *   range:      those per-day flags are absent; tooltips are date-keyed arrays
     *               {date → item_name} so the front end can show what each column meant
     *               on each day of the range.
     *
     * @return array{result: array, columns: array, total: array|null, last_menu_date: mixed}
     */
    public function getOrderReport(?string $date, ?string $startDate, ?string $endDate): array
    {
        $isSingleDay = ($date !== null);

        if ($isSingleDay) {
            $dates = [$date];
        } else {
            $period = new \DatePeriod(
                new \DateTime($startDate),
                new \DateInterval('P1D'),
                (new \DateTime($endDate))->modify('+1 day')
            );
            $dates = [];
            foreach ($period as $d) {
                $dates[] = $d->format('Y-m-d');
            }
        }

        $allRooms = $this->roomDetails->getAll(filters: ['is_active' => 1]);

        // Keyed by room_name so range iterations can accumulate per room
        $finalArray  = [];
        $total       = [];
        $tableColumn = [
            0 => [['title' => 'Room No', 'field' => 'room_id', 'rowspan' => 3]],
            1 => [],
            2 => [],
        ];

        // Column-structure arrays — rebuilt per date
        $soups = $mains = $alternatives = $desserts = [];

        foreach ($dates as $searchDate) {
            $menuRecord = $this->menuDetails->findByDate($searchDate);
            if (!$menuRecord) {
                continue;
            }

            $menuItems = $menuRecord->items ?? [];
            if (is_string($menuItems)) {
                $menuItems = json_decode($menuItems, true) ?? [];
            }
            $menuItems['breakfast'] ??= [];
            $menuItems['lunch']     ??= [];
            $menuItems['dinner']    ??= [];

            // Reset column structures for this date
            $soups = $mains = $alternatives = $desserts = [];

            $allItemIds = array_merge(
                $menuItems['breakfast'],
                $menuItems['lunch'],
                $menuItems['dinner']
            );

            $orderDataMap = [];
            if (!empty($allItemIds)) {
                $orders = $this->orderDetails->findOrderReportSummaries($searchDate, $allItemIds);
                foreach ($orders as $order) {
                    $orderDataMap[$order->room_id . ($order->is_for_guest ? ' G' : '')][$order->item_id] = $order->quantity;
                }
            }

            $breakfastItems = empty($menuItems['breakfast']) ? [] : $this->itemDetails->findOrderReportSummaries($menuItems['breakfast']);
            $lunchItems     = empty($menuItems['lunch'])     ? [] : $this->itemDetails->findOrderReportSummaries($menuItems['lunch']);
            $dinnerItems    = empty($menuItems['dinner'])    ? [] : $this->itemDetails->findOrderReportSummaries($menuItems['dinner']);

            // $isFirst gates column-header generation. We only build the $soups / $mains /
            // $alternatives / $desserts arrays from the very first room of each date —
            // after that, the column structure is fixed for this date's iteration.
            $isFirst = true;

            foreach ($allRooms as $r) {
                $roomId   = $r->id;
                // $addGuest tracks whether any guest order exists for this room.
                // Guest rows ("{room_name} G") are only written to $finalArray when this
                // is true, so empty guest rows don't clutter the report.
                $addGuest = false;

                $currItemArray = [];
                foreach ([false, true] as $isGuest) {
                    $roomKey  = $roomId . ($isGuest ? ' G' : '');
                    $roomName = $r->room_name . ($isGuest ? ' G' : '');

                    $entry = [
                        'room_id'         => $roomId,
                        'room_name'       => $roomName,
                        'has_special_ins' => $r->special_instrucations != null ? 1 : 0,
                    ];
                    if ($isSingleDay) {
                        $entry['has_breakfast_order'] = 0;
                        $entry['has_lunch_order']     = 0;
                        $entry['has_dinner_order']    = 0;
                        $entry['is_for_guest']        = $isGuest ? 1 : 0;
                    }
                    $currItemArray[$roomKey] = $entry;
                }

                // For each menu item this closure:
                //   1. Computes the column title from cat_id (see constant comments above).
                //   2. If $isFirst, registers the column definition (title, tooltip, field).
                //   3. Records the room's order quantity for that column (0 if no order).
                //   4. Accumulates the running total across all rooms.
                //   5. Increments the numeric / letter counters for the next item.
                //
                // $isFirst is captured by VALUE, not by reference. The closure is redefined
                // fresh for each room, so it always captures the value $isFirst had at that
                // point. Only the first room's closure sees $isFirst === true.
                $roleMappings  = $this->categoryRoleMappings();
                $catIdRoles    = $roleMappings['catId'];
                $alternative   = $roleMappings['alternative'];
                $abAlternative = $roleMappings['abAlternative'];

                $processMealItems = function (
                    $items,
                    $mealPrefix,
                    &$count,
                    &$abCount,
                    &$catIdMap,
                    $isGuest,
                    &$addGuest
                ) use (
                    $roomId,
                    &$currItemArray,
                    &$orderDataMap,
                    &$total,
                    &$soups,
                    &$mains,
                    &$alternatives,
                    &$desserts,
                    $isFirst,
                    $searchDate,
                    $isSingleDay,
                    $catIdRoles,
                    $alternative,
                    $abAlternative
                ) {
                    foreach ($items as $a) {
                        $roomKey = $roomId . ($isGuest ? ' G' : '');

                        if (array_key_exists($a->cat_id, $catIdRoles)) {
                            $catIdMap[$a->cat_id][$a->id] = true;
                        }

                        // Compute column title
                        if ($mealPrefix === 'B') {
                            $title = in_array($a->cat_id, $alternative)
                                ? $mealPrefix . $count
                                : (array_key_exists($a->cat_id, $catIdRoles)
                                    ? $catIdRoles[$a->cat_id] . (count($catIdMap[$a->cat_id]) > 1 ? count($catIdMap[$a->cat_id]) : '')
                                    : '');
                        } else {
                            $title = in_array($a->cat_id, $alternative)
                                ? $mealPrefix . $count
                                : (in_array($a->cat_id, $abAlternative)
                                    ? $mealPrefix . $abCount
                                    : (array_key_exists($a->cat_id, $catIdRoles)
                                        ? $catIdRoles[$a->cat_id] . (count($catIdMap[$a->cat_id]) > 1 ? count($catIdMap[$a->cat_id]) : '')
                                        : ''));
                        }

                        // Build column-header structure on first room of each date
                        if ($isFirst) {
                            $secondLetter = substr($title, 1, 1);
                            $tooltip      = $isSingleDay ? $a->item_name : [$searchDate => $a->item_name];

                            if ($secondLetter === 'S') {
                                if (!isset($soups[$title])) {
                                    $soups[$title] = ['title' => $title, 'tooltip' => $tooltip, 'field' => $title];
                                } elseif (!$isSingleDay) {
                                    $soups[$title]['tooltip'][$searchDate] = $a->item_name;
                                }
                            } elseif ($secondLetter === 'A' || $secondLetter === 'B') {
                                if (!isset($mains[$title])) {
                                    $mains[$title] = ['title' => $title, 'tooltip' => $tooltip, 'field' => $title];
                                } elseif (!$isSingleDay) {
                                    $mains[$title]['tooltip'][$searchDate] = $a->item_name;
                                }
                            } elseif ($secondLetter === 'D') {
                                if (!isset($desserts[$title])) {
                                    $desserts[$title] = ['title' => $title, 'tooltip' => $tooltip, 'field' => $title];
                                } elseif (!$isSingleDay) {
                                    $desserts[$title]['tooltip'][$searchDate] = $a->item_name;
                                }
                            } else {
                                // Numeric alternatives (B1, L2, D1, …)
                                if (!isset($alternatives[$title])) {
                                    $alternatives[$title] = ['title' => $title, 'tooltip' => $tooltip, 'field' => $title];
                                } elseif (!$isSingleDay) {
                                    $alternatives[$title]['tooltip'][$searchDate] = $a->item_name;
                                }
                            }
                        }

                        $currItemArray[$roomKey][$title] = $currItemArray[$roomKey][$title] ?? 0;

                        if (isset($orderDataMap[$roomKey][$a->id])) {
                            $currItemArray[$roomKey][$title] += intval($orderDataMap[$roomKey][$a->id]);

                            if ($isSingleDay) {
                                $currItemArray[$roomKey]['has_' . self::PREFIX_MEAL[$mealPrefix] . '_order'] = 1;
                            }
                            if ($isGuest) {
                                $addGuest = true;
                            }
                        }

                        $total[$title] = ($total[$title] ?? 0) + $currItemArray[$roomKey][$title];

                        if (in_array($a->cat_id, $alternative)) {
                            $count++;
                        }
                        if ($mealPrefix !== 'B' && in_array($a->cat_id, $abAlternative)) {
                            $abCount = 'B';
                        }
                    }
                };

                foreach ([false, true] as $isGuest) {
                    foreach (['B' => $breakfastItems, 'L' => $lunchItems, 'D' => $dinnerItems] as $mealPrefix => $items) {
                        $count    = 1;
                        $abCount  = 'A';
                        $catIdMap = array_fill_keys(array_keys($catIdRoles), []);
                        $processMealItems($items, $mealPrefix, $count, $abCount, $catIdMap, $isGuest, $addGuest);
                    }
                    $isFirst = false;
                }

                // Merge current room's entries into the accumulator keyed by room_name
                $metaKeys = ['room_id', 'room_name', 'has_special_ins',
                             'has_breakfast_order', 'has_lunch_order', 'has_dinner_order', 'is_for_guest'];

                foreach ($currItemArray as $row) {
                    $roomName   = $row['room_name'];
                    $isGuestRow = str_ends_with($roomName, ' G');

                    if (!isset($finalArray[$roomName])) {
                        if ($isGuestRow && !$addGuest) {
                            continue;
                        }
                        $finalArray[$roomName] = $row;
                    } else {
                        // Accumulate item quantities across dates (range only in practice)
                        foreach ($row as $key => $value) {
                            if (in_array($key, $metaKeys)) {
                                continue;
                            }
                            $finalArray[$roomName][$key] = ($finalArray[$roomName][$key] ?? 0) + $value;
                        }
                    }
                }
            }
        }

        // Sort column arrays: B before L before D within each category group
        $mealOrder = ['B', 'L', 'D'];
        $orderByMealPrefix = function (&$arr) use ($mealOrder) {
            uksort($arr, function ($a, $b) use ($mealOrder) {
                return array_search(substr($a, 0, 1), $mealOrder) - array_search(substr($b, 0, 1), $mealOrder);
            });
        };

        $orderByMealPrefix($soups);
        $orderByMealPrefix($mains);
        $orderByMealPrefix($alternatives);
        $orderByMealPrefix($desserts);

        // Establish column order: for each meal prefix, soups → mains → alternatives → desserts
        foreach (['B', 'L', 'D'] as $mealPrefix) {
            foreach ([$soups, $mains, $alternatives, $desserts] as $category) {
                foreach ($category as $col) {
                    if (strpos($col['title'], $mealPrefix) === 0) {
                        $tableColumn[2][$col['title']] = $col;
                    }
                }
            }
        }

        // Order $total to match column order
        $orderedTotal = [];
        foreach ($total as $key => $value) {
            foreach ($tableColumn[2] as $colKey => $col) {
                if (array_key_exists($colKey, $total)) {
                    $orderedTotal[$colKey] = $total[$colKey];
                }
            }
            $total = $orderedTotal;
        }

        // Order rows to match column order
        foreach ($finalArray as &$row) {
            $orderedRow = array_diff_key($row, $total);
            foreach ($total as $key => $col) {
                if (array_key_exists($key, $row)) {
                    $orderedRow[$key] = $row[$key];
                }
            }
            $row = $orderedRow;
        }
        unset($row);

        // Sort by room_id then room_name naturally (guest rows follow their base room)
        usort($finalArray, function ($a, $b) {
            if ($a['room_id'] != $b['room_id']) {
                return $a['room_id'] - $b['room_id'];
            }
            return strnatcmp($a['room_name'], $b['room_name']);
        });

        $finalArray = array_values($finalArray);

        // Build meal span headers
        $bCount = $lCount = $dCount = 0;
        foreach ($total as $key => $value) {
            if (strpos($key, 'B') === 0) $bCount++;
            elseif (strpos($key, 'L') === 0) $lCount++;
            elseif (strpos($key, 'D') === 0) $dCount++;
        }
        if ($bCount > 0) $tableColumn[0][] = ['title' => 'Breakfast', 'colspan' => $bCount];
        if ($lCount > 0) $tableColumn[0][] = ['title' => 'Lunch',     'colspan' => $lCount];
        if ($dCount > 0) $tableColumn[0][] = ['title' => 'Dinner',    'colspan' => $dCount];

        // Build totals row
        if (!empty($total)) {
            $tableColumn[1] = array_map(fn ($v) => ['title' => $v], $total);
        }

        // Order column[1] to match total order
        $orderedCol1 = [];
        foreach ($tableColumn[1] as $colKey => $col) {
            foreach ($total as $key => $value) {
                if (array_key_exists($key, $tableColumn[1])) {
                    $orderedCol1[$key] = $col;
                }
            }
        }
        $tableColumn[1] = $orderedCol1;

        $tableColumn[2] = array_values($tableColumn[2]);

        return [
            'result'         => ['rows' => $finalArray],
            'columns'        => $tableColumn,
            'total'          => empty($total) ? null : $total,
            'last_menu_date' => $this->menuDetails->findLatestDate(),
        ];
    }
}
