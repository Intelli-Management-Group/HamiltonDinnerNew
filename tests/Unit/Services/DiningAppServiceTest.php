<?php

namespace Tests\Unit\Services;

use App\Models\CategoryDetail;
use App\Models\ItemDetail;
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
}
