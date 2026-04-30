<?php

namespace Tests\Unit\Services;

use App\Models\CategoryDetail;
use App\Models\ItemDetail;
use App\Models\ItemPreference;
use App\Models\OrderDetail;
use App\Models\RoomDetail;
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
use App\Services\DiningAppService;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DiningAppServiceTest extends TestCase
{
    private function makeService(array $overrides = []): DiningAppService
    {
        $defaults = [
            'categoryDetails'   => Mockery::mock(CategoryDetailRepositoryInterface::class),
            'dateWiseOccupancies' => Mockery::mock(DateWiseOccupancyRepositoryInterface::class),
            'formTypes'         => Mockery::mock(FormTypeRepositoryInterface::class),
            'itemDetails'       => Mockery::mock(ItemDetailRepositoryInterface::class),
            'itemOptions'       => Mockery::mock(ItemOptionRepositoryInterface::class),
            'itemPreferences'   => Mockery::mock(ItemPreferenceRepositoryInterface::class),
            'menuDetails'       => Mockery::mock(MenuDetailRepositoryInterface::class),
            'orderDetails'      => Mockery::mock(OrderDetailRepositoryInterface::class),
            'roles'             => Mockery::mock(RoleRepositoryInterface::class),
            'roomDetails'       => Mockery::mock(RoomDetailRepositoryInterface::class),
            'settings'          => Mockery::mock(SettingRepositoryInterface::class),
            'users'             => Mockery::mock(UserRepositoryInterface::class),
        ];

        $deps = array_merge($defaults, $overrides);

        return new DiningAppService(
            $deps['categoryDetails'],
            $deps['dateWiseOccupancies'],
            $deps['formTypes'],
            $deps['itemDetails'],
            $deps['itemOptions'],
            $deps['itemPreferences'],
            $deps['menuDetails'],
            $deps['orderDetails'],
            $deps['roles'],
            $deps['roomDetails'],
            $deps['settings'],
            $deps['users'],
        );
    }

    // -----------------------------------------------------------------------
    // printOrderData
    // -----------------------------------------------------------------------

    #[Test]
    public function print_order_data_returns_empty_payload_when_no_orders_exist(): void
    {
        // Regression: $finalData was never initialised before the foreach loop,
        // causing "Undefined variable $finalData" (HTTP 500) when the order list
        // is empty for the requested date.
        $prefRepo = Mockery::mock(ItemPreferenceRepositoryInterface::class);
        $prefRepo->shouldReceive('getAll')->andReturn(new Collection());

        $dinnerCat = new CategoryDetail();
        $dinnerCat->id = 5;
        $dinnerCat->type = 3;
        $catRepo = Mockery::mock(CategoryDetailRepositoryInterface::class);
        $catRepo->shouldReceive('getAll')->andReturn(new Collection([$dinnerCat]));
        $catRepo->shouldReceive('getCategoryRoleMappings')->andReturn(['catId' => [], 'alternative' => [], 'abAlternative' => [], 'excluded' => []]);

        $room = Mockery::mock();
        $room->id = 72;
        $room->room_name = '311';
        $roomRepo = Mockery::mock(RoomDetailRepositoryInterface::class);
        $roomRepo->shouldReceive('getAll')->andReturn(new Collection([$room]));

        $orderRepo = Mockery::mock(OrderDetailRepositoryInterface::class);
        $orderRepo->shouldReceive('getAll')->andReturn(new Collection());

        $service = $this->makeService([
            'itemPreferences' => $prefRepo,
            'categoryDetails' => $catRepo,
            'roomDetails'     => $roomRepo,
            'orderDetails'    => $orderRepo,
        ]);

        $response = $service->printOrderData(311, '2026-03-10', 'dinner');
        $data = $response->getData(true);

        $this->assertSame('1', $data['ResponseCode']);
        $this->assertEmpty($data['Data']);
    }

    #[Test]
    public function print_order_data_includes_order_matching_requested_meal_type(): void
    {
        // Regression: the meal-type filter compared $itemData->category->type (the
        // integer type enum, e.g. 3) against an array of category IDs (e.g. [5]).
        // in_array(3, [5]) === false caused all items to be skipped, which also
        // triggered the $finalData undefined-variable 500 above.
        // Fix: filter now uses ->category->id so in_array(5, [5]) === true.

        $prefRepo = Mockery::mock(ItemPreferenceRepositoryInterface::class);
        $prefRepo->shouldReceive('getAll')->andReturn(new Collection());

        // Dinner category: id=5, type=3. Before the fix, in_array(3, [5]) was false.
        $dinnerCat = new CategoryDetail();
        $dinnerCat->id = 5;
        $dinnerCat->cat_name = 'Dinner Entree';
        $dinnerCat->type = 3;
        $dinnerCat->parent_id = 0;

        $catRepo = Mockery::mock(CategoryDetailRepositoryInterface::class);
        $catRepo->shouldReceive('getAll')->andReturn(new Collection([$dinnerCat]));
        $catRepo->shouldReceive('getCategoryRoleMappings')->andReturn(['catId' => [], 'alternative' => [], 'abAlternative' => [], 'excluded' => []]);

        $item = new ItemDetail();
        $item->item_name = 'Roast Chicken';
        $item->setRelation('category', $dinnerCat);

        $order = Mockery::mock();
        $order->room_id = 72;
        $order->is_for_guest = 0;
        $order->item_options = '';
        $order->preference = '';
        $order->quantity = 1;
        $order->id = 100;
        $order->is_brk_tray_service = 0;
        $order->is_lunch_tray_service = 0;
        $order->is_dinner_tray_service = 0;
        $order->is_brk_escort_service = 0;
        $order->is_lunch_escort_service = 0;
        $order->is_dinner_escort_service = 0;
        $order->is_brk_takeout_service = 0;
        $order->is_lunch_takeout_service = 0;
        $order->is_dinner_takeout_service = 0;
        $order->itemData = $item;

        $roomForCollection = Mockery::mock();
        $roomForCollection->id = 72;
        $roomForCollection->room_name = '311';

        $spiData = new RoomDetail();
        $spiData->id = 72;
        $spiData->room_name = '311';
        $spiData->special_instrucations = '';
        $spiData->food_texture = '';
        $spiData->resident_name = 'John Doe';

        $roomRepo = Mockery::mock(RoomDetailRepositoryInterface::class);
        $roomRepo->shouldReceive('getAll')->andReturn(new Collection([$roomForCollection]));
        $roomRepo->shouldReceive('findById')->with(72)->andReturn($spiData);

        $orderRepo = Mockery::mock(OrderDetailRepositoryInterface::class);
        $orderRepo->shouldReceive('getAll')->andReturn(new Collection([$order]));

        $service = $this->makeService([
            'itemPreferences' => $prefRepo,
            'categoryDetails' => $catRepo,
            'roomDetails'     => $roomRepo,
            'orderDetails'    => $orderRepo,
        ]);

        $response = $service->printOrderData(311, '2026-03-10', 'dinner');
        $data = $response->getData(true);

        $this->assertSame('1', $data['ResponseCode']);
        $this->assertCount(1, $data['Data']);
        $this->assertSame('311', $data['Data'][0]['room_name']);
        $this->assertCount(1, $data['Data'][0]['Items']);
        $this->assertSame('Roast Chicken', $data['Data'][0]['Items'][0]['item_name']);
    }

    // -----------------------------------------------------------------------
    // getGuestOrderList
    // -----------------------------------------------------------------------

    #[Test]
    public function guest_order_list_returns_empty_meal_arrays_when_no_menu_for_date(): void
    {
        $menuRepo = Mockery::mock(MenuDetailRepositoryInterface::class);
        $menuRepo->shouldReceive('getAll')->andReturn(new Collection());

        $orderRepo = Mockery::mock(OrderDetailRepositoryInterface::class);
        $orderRepo->shouldReceive('getAll')->andReturn(new Collection());

        $occupancyRepo = Mockery::mock(DateWiseOccupancyRepositoryInterface::class);
        $occupancyRepo->shouldReceive('getAll')
            ->once()
            ->andReturn(new Collection());

        $service = $this->makeService([
            'menuDetails'        => $menuRepo,
            'orderDetails'       => $orderRepo,
            'dateWiseOccupancies' => $occupancyRepo,
        ]);

        $response = $service->getGuestOrderList(1, '2026-01-01');
        $data = $response->getData(true);

        $this->assertSame('1', $data['ResponseCode']);
        $this->assertEmpty($data['breakfast']);
        $this->assertEmpty($data['lunch']);
        $this->assertEmpty($data['dinner']);
        $this->assertSame(0, $data['occupancy']);
        $this->assertSame(0, $data['is_brk_tray_service']);
        $this->assertSame(0, $data['is_lunch_tray_service']);
        $this->assertSame(0, $data['is_dinner_tray_service']);
    }

    #[Test]
    public function guest_order_list_places_items_into_correct_meal_type(): void
    {
        $date = '2026-01-01';
        $roomId = 5;

        // A menu with two items: IDs 10 (breakfast) and 20 (dinner)
        $menuRecord = Mockery::mock();
        $menuRecord->items = [[10, 20]];

        $menuRepo = Mockery::mock(MenuDetailRepositoryInterface::class);
        $menuRepo->shouldReceive('getAll')
            ->andReturn(new Collection([$menuRecord]));

        // Options / preferences — none for these items
        $optionRepo = Mockery::mock(ItemOptionRepositoryInterface::class);
        $optionRepo->shouldReceive('getAll')->andReturn(new Collection());

        $prefRepo = Mockery::mock(ItemPreferenceRepositoryInterface::class);
        $prefRepo->shouldReceive('getAll')->andReturn(new Collection());

        // Category for item 10 → type 1 (breakfast)
        $brkCategory = new CategoryDetail();
        $brkCategory->id = 1;
        $brkCategory->cat_name = 'BREAKFAST DAILY';
        $brkCategory->category_chinese_name = '早餐';
        $brkCategory->type = 1; // breakfast
        $brkCategory->parent_id = 0;

        // Category for item 20 → type 3 (dinner)
        $dinCategory = new CategoryDetail();
        $dinCategory->id = 3;
        $dinCategory->cat_name = 'DINNER ENTREE';
        $dinCategory->category_chinese_name = '晚餐';
        $dinCategory->type = 3; // dinner
        $dinCategory->parent_id = 0;

        // Main-category items (parent_id == 0)
        $brkItem = new ItemDetail();
        $brkItem->id = 10;
        $brkItem->cat_id = 1;
        $brkItem->item_name = 'Scrambled Eggs';
        $brkItem->item_chinese_name = '炒蛋';
        $brkItem->item_image = null;
        $brkItem->options = null;
        $brkItem->preference = null;
        $brkItem->setRelation('category', $brkCategory);

        $dinItem = new ItemDetail();
        $dinItem->id = 20;
        $dinItem->cat_id = 3;
        $dinItem->item_name = 'Roast Chicken';
        $dinItem->item_chinese_name = '烤雞';
        $dinItem->item_image = null;
        $dinItem->options = null;
        $dinItem->preference = null;
        $dinItem->setRelation('category', $dinCategory);

        $itemDetailRepo = Mockery::mock(ItemDetailRepositoryInterface::class);
        $itemDetailRepo->shouldReceive('findByIdsAndParentFlag')
            ->with(Mockery::any(), true)   // main-category items
            ->andReturn(new Collection([$brkItem, $dinItem]));
        $itemDetailRepo->shouldReceive('findByIdsAndParentFlag')
            ->with(Mockery::any(), false)  // sub-category items
            ->andReturn(new Collection());

        // Order data for the room on this date (guest order, roomId != 0 path)
        $orderRepo = Mockery::mock(OrderDetailRepositoryInterface::class);
        $orderRepo->shouldReceive('getAll')
            ->andReturn(new Collection()); // no existing guest order for any item

        $occupancyRepo = Mockery::mock(DateWiseOccupancyRepositoryInterface::class);
        $occupancyRepo->shouldReceive('getAll')->andReturn(new Collection());

        $service = $this->makeService([
            'menuDetails'        => $menuRepo,
            'itemOptions'        => $optionRepo,
            'itemPreferences'    => $prefRepo,
            'itemDetails'        => $itemDetailRepo,
            'orderDetails'       => $orderRepo,
            'dateWiseOccupancies' => $occupancyRepo,
        ]);

        $response = $service->getGuestOrderList($roomId, $date);
        $data = $response->getData(true);

        $this->assertSame('1', $data['ResponseCode']);

        // Breakfast gets the item with category type=1
        $this->assertCount(1, $data['breakfast']);
        $this->assertSame('BREAKFAST DAILY', $data['breakfast'][0]['cat_name']);
        $this->assertCount(1, $data['breakfast'][0]['items']);
        $this->assertSame('Scrambled Eggs', $data['breakfast'][0]['items'][0]['item_name']);
        $this->assertSame('item', $data['breakfast'][0]['items'][0]['type']);
        $this->assertSame(0, $data['breakfast'][0]['items'][0]['qty']); // no guest order placed

        // Lunch empty
        $this->assertEmpty($data['lunch']);

        // Dinner gets the item with category type=3
        $this->assertCount(1, $data['dinner']);
        $this->assertSame('DINNER ENTREE', $data['dinner'][0]['cat_name']);
        $this->assertCount(1, $data['dinner'][0]['items']);
        $this->assertSame('Roast Chicken', $data['dinner'][0]['items'][0]['item_name']);
    }

    #[Test]
    public function guest_order_list_reflects_existing_guest_order_quantity(): void
    {
        $date = '2026-01-01';
        $roomId = 5;

        $menuRecord = Mockery::mock();
        $menuRecord->items = [[10]];

        $menuRepo = Mockery::mock(MenuDetailRepositoryInterface::class);
        $menuRepo->shouldReceive('getAll')->andReturn(new Collection([$menuRecord]));

        $optionRepo = Mockery::mock(ItemOptionRepositoryInterface::class);
        $optionRepo->shouldReceive('getAll')->andReturn(new Collection());

        $prefRepo = Mockery::mock(ItemPreferenceRepositoryInterface::class);
        $prefRepo->shouldReceive('getAll')->andReturn(new Collection());

        $category = new CategoryDetail();
        $category->id = 1;
        $category->cat_name = 'BREAKFAST DAILY';
        $category->category_chinese_name = '早餐';
        $category->type = 1;
        $category->parent_id = 0;

        $item = new ItemDetail();
        $item->id = 10;
        $item->cat_id = 1;
        $item->item_name = 'Scrambled Eggs';
        $item->item_chinese_name = null;
        $item->item_image = null;
        $item->options = null;
        $item->preference = null;
        $item->setRelation('category', $category);

        $itemDetailRepo = Mockery::mock(ItemDetailRepositoryInterface::class);
        $itemDetailRepo->shouldReceive('findByIdsAndParentFlag')->with(Mockery::any(), true)
            ->andReturn(new Collection([$item]));
        $itemDetailRepo->shouldReceive('findByIdsAndParentFlag')->with(Mockery::any(), false)
            ->andReturn(new Collection());

        // Existing guest order: quantity=2, is_for_guest=1
        $existingOrder = Mockery::mock();
        $existingOrder->id = 99;
        $existingOrder->quantity = 2;
        $existingOrder->is_for_guest = 1;
        $existingOrder->item_options = null;
        $existingOrder->preference = null;
        $existingOrder->is_brk_tray_service = 1;
        $existingOrder->is_lunch_tray_service = 0;
        $existingOrder->is_dinner_tray_service = 0;

        $orderRepo = Mockery::mock(OrderDetailRepositoryInterface::class);
        $orderRepo->shouldReceive('getAll')
            ->andReturn(new Collection([$existingOrder]));

        $occupancyRepo = Mockery::mock(DateWiseOccupancyRepositoryInterface::class);
        $occupancyRepo->shouldReceive('getAll')->andReturn(new Collection());

        $service = $this->makeService([
            'menuDetails'        => $menuRepo,
            'itemOptions'        => $optionRepo,
            'itemPreferences'    => $prefRepo,
            'itemDetails'        => $itemDetailRepo,
            'orderDetails'       => $orderRepo,
            'dateWiseOccupancies' => $occupancyRepo,
        ]);

        $response = $service->getGuestOrderList($roomId, $date);
        $data = $response->getData(true);

        $this->assertCount(1, $data['breakfast'][0]['items']);
        $item = $data['breakfast'][0]['items'][0];
        $this->assertSame(2, $item['qty']);   // quantity from guest order
        $this->assertSame(99, $item['order_id']);
        $this->assertSame(1, $data['is_brk_tray_service']);
        $this->assertSame(0, $data['is_lunch_tray_service']);
        $this->assertSame(0, $data['is_dinner_tray_service']);
    }

    // -----------------------------------------------------------------------
    // printOrderData — room data regression (findById fix)
    // -----------------------------------------------------------------------

    #[Test]
    public function print_order_data_room_fields_come_from_the_orders_room_not_a_random_one(): void
    {
        // Regression: spiData was fetched via getAll(filters: ['id' => room_id]), but
        // RoomDetailRepository::applyFilters has no 'id' handler, so the filter was
        // silently ignored and getAll() returned all rooms ordered by created_at DESC.
        // The fix uses findById() so the correct room's data is always returned.

        $prefRepo = Mockery::mock(ItemPreferenceRepositoryInterface::class);
        $prefRepo->shouldReceive('getAll')->andReturn(new Collection());

        $lunchCat = new CategoryDetail();
        $lunchCat->id = 3;
        $lunchCat->cat_name = 'Lunch Entree';
        $lunchCat->type = 2;
        $lunchCat->parent_id = 0;

        $catRepo = Mockery::mock(CategoryDetailRepositoryInterface::class);
        $catRepo->shouldReceive('getAll')->andReturn(new Collection([$lunchCat]));
        $catRepo->shouldReceive('getCategoryRoleMappings')->andReturn(['catId' => [], 'alternative' => [], 'abAlternative' => [], 'excluded' => []]);

        // The order belongs to room 72 (room "311").
        $correctRoomForCollection = Mockery::mock();
        $correctRoomForCollection->id = 72;
        $correctRoomForCollection->room_name = '311';

        $correctRoom = new RoomDetail();
        $correctRoom->id = 72;
        $correctRoom->room_name = '311';
        $correctRoom->special_instrucations = 'No salt';
        $correctRoom->food_texture = 'Soft';
        $correctRoom->allergy_info = 'Nut allergy';
        $correctRoom->resident_name = 'Jane Doe';

        $item = new ItemDetail();
        $item->item_name = 'Pasta';
        $item->setRelation('category', $lunchCat);

        $order = Mockery::mock();
        $order->room_id = 72;
        $order->is_for_guest = 0;
        $order->item_options = '';
        $order->preference = '';
        $order->quantity = 1;
        $order->id = 200;
        $order->is_brk_tray_service = 0;
        $order->is_lunch_tray_service = 1;
        $order->is_dinner_tray_service = 0;
        $order->is_brk_escort_service = 0;
        $order->is_lunch_escort_service = 0;
        $order->is_dinner_escort_service = 0;
        $order->is_brk_takeout_service = 0;
        $order->is_lunch_takeout_service = 0;
        $order->is_dinner_takeout_service = 0;
        $order->itemData = $item;

        $roomRepo = Mockery::mock(RoomDetailRepositoryInterface::class);
        // getAll is used to resolve the room_name → id mapping (initial filter step).
        $roomRepo->shouldReceive('getAll')->andReturn(new Collection([$correctRoomForCollection]));
        // findById must return the correct room, not the wrong one.
        $roomRepo->shouldReceive('findById')->with(72)->andReturn($correctRoom);

        $orderRepo = Mockery::mock(OrderDetailRepositoryInterface::class);
        $orderRepo->shouldReceive('getAll')->andReturn(new Collection([$order]));

        $service = $this->makeService([
            'itemPreferences' => $prefRepo,
            'categoryDetails' => $catRepo,
            'roomDetails'     => $roomRepo,
            'orderDetails'    => $orderRepo,
        ]);

        $response = $service->printOrderData(311, '2026-03-10', 'lunch');
        $data = $response->getData(true);

        $this->assertSame('1', $data['ResponseCode']);
        $this->assertCount(1, $data['Data']);
        $entry = $data['Data'][0];
        $this->assertSame('311', $entry['room_name']);
        $this->assertSame('No salt', $entry['special_instruction']);
        $this->assertSame('Soft', $entry['food_texture']);
        $this->assertSame('Nut allergy', $entry['allergy_info']);
        $this->assertSame('Jane Doe', $entry['resident_name']);
    }

    // -----------------------------------------------------------------------
    // printOrderData — preference strings regression
    // -----------------------------------------------------------------------

    #[Test]
    public function print_order_data_preferences_are_plain_strings_not_objects(): void
    {
        // Regression: $preferences_en mapped IDs to ['name' => ..., 'name_cn' => ...]
        // and the array was pushed whole into $preferenceArray, so the response
        // contained objects instead of the plain name strings the front-end expects.
        // Fix: only push $preferences_en[$id]['name'].

        $pref = new ItemPreference();
        $pref->id = 1;
        $pref->pname = 'Less oil';
        $pref->pname_cn = '少油';

        $prefRepo = Mockery::mock(ItemPreferenceRepositoryInterface::class);
        $prefRepo->shouldReceive('getAll')->andReturn(new Collection([$pref]));

        $lunchCat = new CategoryDetail();
        $lunchCat->id = 3;
        $lunchCat->cat_name = 'Lunch Entree';
        $lunchCat->type = 2;
        $lunchCat->parent_id = 0;

        $catRepo = Mockery::mock(CategoryDetailRepositoryInterface::class);
        $catRepo->shouldReceive('getAll')->andReturn(new Collection([$lunchCat]));
        $catRepo->shouldReceive('getCategoryRoleMappings')->andReturn(['catId' => [], 'alternative' => [], 'abAlternative' => [], 'excluded' => []]);

        $item = new ItemDetail();
        $item->item_name = 'Fried Rice';
        $item->setRelation('category', $lunchCat);

        $order = Mockery::mock();
        $order->room_id = 72;
        $order->is_for_guest = 0;
        $order->item_options = '';
        $order->preference = '1'; // preference ID 1 = "Less oil"
        $order->quantity = 2;
        $order->id = 300;
        $order->is_brk_tray_service = 0;
        $order->is_lunch_tray_service = 0;
        $order->is_dinner_tray_service = 0;
        $order->is_brk_escort_service = 0;
        $order->is_lunch_escort_service = 0;
        $order->is_dinner_escort_service = 0;
        $order->is_brk_takeout_service = 0;
        $order->is_lunch_takeout_service = 0;
        $order->is_dinner_takeout_service = 0;
        $order->itemData = $item;

        $roomForCollection = Mockery::mock();
        $roomForCollection->id = 72;
        $roomForCollection->room_name = '311';

        $spiData = new RoomDetail();
        $spiData->id = 72;
        $spiData->room_name = '311';
        $spiData->special_instrucations = '';
        $spiData->food_texture = '';
        $spiData->resident_name = 'Jane Doe';

        $roomRepo = Mockery::mock(RoomDetailRepositoryInterface::class);
        $roomRepo->shouldReceive('getAll')->andReturn(new Collection([$roomForCollection]));
        $roomRepo->shouldReceive('findById')->with(72)->andReturn($spiData);

        $orderRepo = Mockery::mock(OrderDetailRepositoryInterface::class);
        $orderRepo->shouldReceive('getAll')->andReturn(new Collection([$order]));

        $service = $this->makeService([
            'itemPreferences' => $prefRepo,
            'categoryDetails' => $catRepo,
            'roomDetails'     => $roomRepo,
            'orderDetails'    => $orderRepo,
        ]);

        $response = $service->printOrderData(311, '2026-03-10', 'lunch');
        $data = $response->getData(true);

        $this->assertSame('1', $data['ResponseCode']);
        $this->assertCount(1, $data['Data']);
        $preferences = $data['Data'][0]['Items'][0]['preference'];
        $this->assertCount(1, $preferences);
        // Must be a plain string, not an array/object like ['name' => 'Less oil', 'name_cn' => '少油']
        $this->assertSame('Less oil', $preferences[0]);
    }

    // -----------------------------------------------------------------------
    // printOrderData — service-flag guest-contamination regression
    // -----------------------------------------------------------------------

    #[Test]
    public function print_order_data_service_flags_not_contaminated_by_higher_id_guest_order(): void
    {
        // Regression: $lastOrder was fetched without an is_for_guest filter, so
        // a higher-ID guest order was returned by ORDER BY id DESC, causing
        // is_dinner_takeout_service / is_brk_escort_service etc. to read as 0.
        // Fix: always pass is_for_guest to the lastOrder query.

        $prefRepo = Mockery::mock(ItemPreferenceRepositoryInterface::class);
        $prefRepo->shouldReceive('getAll')->andReturn(new Collection());

        $dinnerCat = new CategoryDetail();
        $dinnerCat->id = 62;
        $dinnerCat->cat_name = 'Dinner Entree';
        $dinnerCat->type = 3;
        $dinnerCat->parent_id = 0;

        $catRepo = Mockery::mock(CategoryDetailRepositoryInterface::class);
        $catRepo->shouldReceive('getAll')->andReturn(new Collection([$dinnerCat]));
        $catRepo->shouldReceive('getCategoryRoleMappings')->andReturn(['catId' => [], 'alternative' => [], 'abAlternative' => [], 'excluded' => []]);

        $item = new ItemDetail();
        $item->item_name = 'Steak';
        $item->setRelation('category', $dinnerCat);

        // Regular order (id=200): takeout and breakfast escort both selected.
        $regularOrder = Mockery::mock();
        $regularOrder->room_id    = 72;
        $regularOrder->is_for_guest = 0;
        $regularOrder->item_options = '';
        $regularOrder->preference   = '';
        $regularOrder->quantity     = 1;
        $regularOrder->id           = 200;
        $regularOrder->is_brk_tray_service     = 0;
        $regularOrder->is_lunch_tray_service    = 0;
        $regularOrder->is_dinner_tray_service   = 0;
        $regularOrder->is_brk_escort_service    = 1;
        $regularOrder->is_lunch_escort_service  = 0;
        $regularOrder->is_dinner_escort_service = 0;
        $regularOrder->is_brk_takeout_service   = 0;
        $regularOrder->is_lunch_takeout_service = 0;
        $regularOrder->is_dinner_takeout_service = 1;
        $regularOrder->itemData = $item;

        // Guest order (id=201, higher ID): all service flags 0.
        // Without the fix, ORDER BY id DESC would return this row for a regular-order lookup.
        $guestOrder = Mockery::mock();
        $guestOrder->room_id    = 72;
        $guestOrder->is_for_guest = 1;
        $guestOrder->id           = 201;
        $guestOrder->is_brk_tray_service     = 0;
        $guestOrder->is_lunch_tray_service    = 0;
        $guestOrder->is_dinner_tray_service   = 0;
        $guestOrder->is_brk_escort_service    = 0;
        $guestOrder->is_lunch_escort_service  = 0;
        $guestOrder->is_dinner_escort_service = 0;
        $guestOrder->is_brk_takeout_service   = 0;
        $guestOrder->is_lunch_takeout_service = 0;
        $guestOrder->is_dinner_takeout_service = 0;

        $roomForCollection = Mockery::mock();
        $roomForCollection->id        = 72;
        $roomForCollection->room_name = '311';

        $roomData = new RoomDetail();
        $roomData->id                   = 72;
        $roomData->room_name            = '311';
        $roomData->special_instrucations = '';
        $roomData->food_texture         = '';
        $roomData->allergy_info         = '';
        $roomData->resident_name        = 'Jane Doe';

        $roomRepo = Mockery::mock(RoomDetailRepositoryInterface::class);
        $roomRepo->shouldReceive('getAll')->andReturn(new Collection([$roomForCollection]));
        $roomRepo->shouldReceive('findById')->with(72)->andReturn($roomData);

        $orderRepo = Mockery::mock(OrderDetailRepositoryInterface::class);
        $orderRepo->shouldReceive('getAll')->andReturnUsing(
            function (array $filters) use ($regularOrder, $guestOrder) {
                if (!isset($filters['room_id'])) {
                    // Initial orderData fetch (no room_id yet) — return the regular order.
                    return new Collection([$regularOrder]);
                }
                // lastOrder fetch: with the fix, is_for_guest=0 must be present.
                // Without the fix (no is_for_guest key), simulate the bug by returning
                // the higher-ID guest order so the assertion below would fail.
                if (($filters['is_for_guest'] ?? null) === 0) {
                    return new Collection([$regularOrder]);
                }
                return new Collection([$guestOrder]); // would be returned by buggy code
            }
        );

        $service = $this->makeService([
            'itemPreferences' => $prefRepo,
            'categoryDetails' => $catRepo,
            'roomDetails'     => $roomRepo,
            'orderDetails'    => $orderRepo,
        ]);

        $response = $service->printOrderData(311, '2026-04-15', 'dinner');
        $data     = $response->getData(true);

        $this->assertSame('1', $data['ResponseCode']);
        $this->assertCount(1, $data['Data']);
        $entry = $data['Data'][0];
        // Service flags must come from the regular order (id=200), not the guest order (id=201).
        $this->assertSame(1, $entry['is_dinner_takeout_service'], 'dinner takeout contaminated by guest order');
        $this->assertSame(1, $entry['is_brk_escort_service'],     'brk escort contaminated by guest order');
        $this->assertSame(0, $entry['is_dinner_tray_service']);
    }

    #[Test]
    public function print_order_data_guest_order_service_flags_scoped_to_guest_orders(): void
    {
        // Symmetric case: when the order being processed is a guest order,
        // the lastOrder query must filter is_for_guest=1 so that a lower-ID
        // regular order with different flags does not contaminate the result.

        $prefRepo = Mockery::mock(ItemPreferenceRepositoryInterface::class);
        $prefRepo->shouldReceive('getAll')->andReturn(new Collection());

        $dinnerCat = new CategoryDetail();
        $dinnerCat->id = 62;
        $dinnerCat->cat_name = 'Dinner Entree';
        $dinnerCat->type = 3;
        $dinnerCat->parent_id = 0;

        $catRepo = Mockery::mock(CategoryDetailRepositoryInterface::class);
        $catRepo->shouldReceive('getAll')->andReturn(new Collection([$dinnerCat]));
        $catRepo->shouldReceive('getCategoryRoleMappings')->andReturn(['catId' => [], 'alternative' => [], 'abAlternative' => [], 'excluded' => []]);

        $item = new ItemDetail();
        $item->item_name = 'Pasta';
        $item->setRelation('category', $dinnerCat);

        // Regular order (id=200): all service flags 0 for this guest visit.
        $regularOrder = Mockery::mock();
        $regularOrder->room_id    = 72;
        $regularOrder->is_for_guest = 0;
        $regularOrder->id           = 200;
        $regularOrder->is_brk_tray_service     = 0;
        $regularOrder->is_lunch_tray_service    = 0;
        $regularOrder->is_dinner_tray_service   = 0;
        $regularOrder->is_brk_escort_service    = 0;
        $regularOrder->is_lunch_escort_service  = 0;
        $regularOrder->is_dinner_escort_service = 0;
        $regularOrder->is_brk_takeout_service   = 0;
        $regularOrder->is_lunch_takeout_service = 0;
        $regularOrder->is_dinner_takeout_service = 0;

        // Guest order (id=201): dinner tray selected.
        $guestOrder = Mockery::mock();
        $guestOrder->room_id    = 72;
        $guestOrder->is_for_guest = 1;
        $guestOrder->item_options = '';
        $guestOrder->preference   = '';
        $guestOrder->quantity     = 1;
        $guestOrder->id           = 201;
        $guestOrder->is_brk_tray_service     = 0;
        $guestOrder->is_lunch_tray_service    = 0;
        $guestOrder->is_dinner_tray_service   = 1;
        $guestOrder->is_brk_escort_service    = 0;
        $guestOrder->is_lunch_escort_service  = 0;
        $guestOrder->is_dinner_escort_service = 0;
        $guestOrder->is_brk_takeout_service   = 0;
        $guestOrder->is_lunch_takeout_service = 0;
        $guestOrder->is_dinner_takeout_service = 0;
        $guestOrder->itemData = $item;

        $roomForCollection = Mockery::mock();
        $roomForCollection->id        = 72;
        $roomForCollection->room_name = '311';

        $roomData = new RoomDetail();
        $roomData->id                   = 72;
        $roomData->room_name            = '311';
        $roomData->special_instrucations = '';
        $roomData->food_texture         = '';
        $roomData->allergy_info         = '';
        $roomData->resident_name        = 'Jane Doe';

        $roomRepo = Mockery::mock(RoomDetailRepositoryInterface::class);
        $roomRepo->shouldReceive('getAll')->andReturn(new Collection([$roomForCollection]));
        $roomRepo->shouldReceive('findById')->with(72)->andReturn($roomData);

        $orderRepo = Mockery::mock(OrderDetailRepositoryInterface::class);
        $orderRepo->shouldReceive('getAll')->andReturnUsing(
            function (array $filters) use ($regularOrder, $guestOrder) {
                if (!isset($filters['room_id'])) {
                    // Initial orderData fetch — return only the guest order.
                    return new Collection([$guestOrder]);
                }
                // lastOrder fetch: is_for_guest=1 must be set for guest-order lookups.
                if (($filters['is_for_guest'] ?? null) === 1) {
                    return new Collection([$guestOrder]);
                }
                return new Collection([$regularOrder]); // would be returned by buggy code
            }
        );

        $service = $this->makeService([
            'itemPreferences' => $prefRepo,
            'categoryDetails' => $catRepo,
            'roomDetails'     => $roomRepo,
            'orderDetails'    => $orderRepo,
        ]);

        $response = $service->printOrderData(311, '2026-04-15', 'dinner');
        $data     = $response->getData(true);

        $this->assertSame('1', $data['ResponseCode']);
        $this->assertCount(1, $data['Data']);
        $entry = $data['Data'][0];
        // Service flags must come from the guest order (id=201), not the regular order (id=200).
        $this->assertSame(1, $entry['is_dinner_tray_service'],    'dinner tray contaminated by regular order');
        $this->assertSame(0, $entry['is_dinner_takeout_service']);
    }
}
