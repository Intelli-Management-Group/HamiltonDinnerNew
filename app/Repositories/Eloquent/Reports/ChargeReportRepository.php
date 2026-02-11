<?php

namespace App\Repositories\Eloquent\Reports;

use App\Repositories\Contracts\Reports\ChargeReportRepositoryInterface;
use Illuminate\Support\Facades\DB;

class ChargeReportRepository implements ChargeReportRepositoryInterface
{
    public function runMealReport(string $start_date, string $end_date)
    {
        return DB::select(
            "SELECT
            	-- group keys
            	room_id                 AS room_id,
                is_for_guest            AS is_for_guest,
                meal                    AS meal,
                
                -- room name
                MAX(room_name)         AS room_name,
                
                -- sum outside subquery
                SUM(occupancy)          AS occupancy,
                SUM(is_tray_service)    AS is_tray_service,
                SUM(is_escort_service)  AS is_escort_service,
                SUM(is_takeout_service) AS is_takeout_service,
                
                -- concat items
            	GROUP_CONCAT(
            		DISTINCT items
                    SEPARATOR ';'
            	)                       AS items
                
            FROM (
            	SELECT 
            		-- grouping keys
            		od.room_id                      AS room_id,
                    od.date                         AS date,
                    CASE
            			WHEN cd.type = 1 THEN 'breakfast'
                        WHEN cd.type = 2 THEN 'lunch'
                        WHEN cd.type = 3 THEN 'dinner'
                        ELSE NULL
            		END                             AS meal,
                    od.is_for_guest                 AS is_for_guest,
                    
                    -- misc. details
                    rd.room_name                    AS room_name,
                    od.is_for_guest * dwo.occupancy AS occupancy,
                    
                    -- consolidate services
                    CASE
            			WHEN is_for_guest = 1 AND cd.type = 1 THEN od.is_brk_tray_service * dwo.occupancy
                        WHEN is_for_guest = 1 AND cd.type = 2 THEN od.is_lunch_tray_service * dwo.occupancy
                        WHEN is_for_guest = 1 AND cd.type = 3 THEN od.is_dinner_tray_service * dwo.occupancy
            			WHEN is_for_guest = 0 AND cd.type = 1 THEN od.is_brk_tray_service
                        WHEN is_for_guest = 0 AND cd.type = 2 THEN od.is_lunch_tray_service
                        WHEN is_for_guest = 0 AND cd.type = 3 THEN od.is_dinner_tray_service
            			ELSE NULL
            		END                             AS is_tray_service,
                    
                    CASE
            			WHEN is_for_guest = 1 AND cd.type = 1 THEN od.is_brk_escort_service * dwo.occupancy
                        WHEN is_for_guest = 1 AND cd.type = 2 THEN od.is_lunch_escort_service * dwo.occupancy
                        WHEN is_for_guest = 1 AND cd.type = 3 THEN od.is_dinner_escort_service * dwo.occupancy
                        WHEN is_for_guest = 0 AND cd.type = 1 THEN od.is_brk_escort_service
                        WHEN is_for_guest = 0 AND cd.type = 2 THEN od.is_lunch_escort_service
                        WHEN is_for_guest = 0 AND cd.type = 3 THEN od.is_dinner_escort_service
            			ELSE NULL
            		END                             AS is_escort_service,
                    
                    CASE
            			WHEN is_for_guest = 1 AND cd.type = 1 THEN od.is_brk_takeout_service * dwo.occupancy
                        WHEN is_for_guest = 1 AND cd.type = 2 THEN od.is_lunch_takeout_service * dwo.occupancy
                        WHEN is_for_guest = 1 AND cd.type = 3 THEN od.is_dinner_takeout_service * dwo.occupancy
                        WHEN is_for_guest = 0 AND cd.type = 1 THEN od.is_brk_takeout_service
                        WHEN is_for_guest = 0 AND cd.type = 2 THEN od.is_lunch_takeout_service
                        WHEN is_for_guest = 0 AND cd.type = 3 THEN od.is_dinner_takeout_service
            			ELSE NULL
            		END                             AS is_takeout_service,
                    
                    GROUP_CONCAT(
            			DISTINCT CONCAT(
            				TRIM(od.item_options), ':',
            				io.option_name, ':',
            				od.date, ':', 
            				id.item_name, ':',
                            od.quantity
            			) 
            			ORDER BY 
                            TRIM(od.item_options),
                            io.option_name,
                            od.date,
                            id.item_name,
                            od.quantity
            			SEPARATOR ';'
            		)                               AS items
                    
            	FROM order_details od
            	LEFT JOIN room_details rd           ON rd.id = od.room_id
                LEFT JOIN item_details id           ON id.id = od.item_id
                LEFT JOIN category_details cd       ON cd.id = id.cat_id
                LEFT JOIN date_wise_occupancies dwo ON dwo.room_id = rd.id 
                                                    AND dwo.date = od.date
                LEFT JOIN (
                    SELECT id, option_name, is_paid_item
                    FROM item_options
                    WHERE is_paid_item = '1'
                ) io                                ON io.id = cast(trim(od.item_options) AS SIGNED INTEGER)
                
            	WHERE od.date BETWEEN '$start_date' AND '$end_date'
            	GROUP BY 1,2,3,4
            ) sq
            GROUP BY 1,2,3;"
        );
    }
}