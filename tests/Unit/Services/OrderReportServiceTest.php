<?php

namespace Tests\Unit\Services;

use App\Models\MenuDetail;
use App\Repositories\Contracts\ItemDetailRepositoryInterface;
use App\Repositories\Contracts\MenuDetailRepositoryInterface;
use App\Repositories\Contracts\OrderDetailRepositoryInterface;
use App\Repositories\Contracts\RoomDetailRepositoryInterface;
use App\Services\Reports\OrderReportService;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderReportServiceTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeService(array $overrides = []): OrderReportService
    {
        $defaults = [
            'menuDetails'  => Mockery::mock(MenuDetailRepositoryInterface::class),
            'orderDetails' => Mockery::mock(OrderDetailRepositoryInterface::class),
            'roomDetails'  => Mockery::mock(RoomDetailRepositoryInterface::class),
            'itemDetails'  => Mockery::mock(ItemDetailRepositoryInterface::class),
        ];

        $deps = array_merge($defaults, $overrides);

        return new OrderReportService(
            $deps['menuDetails'],
            $deps['orderDetails'],
            $deps['roomDetails'],
            $deps['itemDetails'],
        );
    }

    private function makeRoom(int $id, string $name, ?string $specialInstructions = null): object
    {
        $r = new \stdClass();
        $r->id = $id;
        $r->room_name = $name;
        $r->special_instrucations = $specialInstructions;
        return $r;
    }

    private function makeItem(int $id, int $catId, string $name): object
    {
        $a = new \stdClass();
        $a->id = $id;
        $a->cat_id = $catId;
        $a->item_name = $name;
        return $a;
    }

    private function makeOrder(int $roomId, int $itemId, int $qty, int $isForGuest = 0): object
    {
        $o = new \stdClass();
        $o->room_id = $roomId;
        $o->item_id = $itemId;
        $o->quantity = $qty;
        $o->is_for_guest = $isForGuest;
        return $o;
    }

    private function makeMenu(array $items): MenuDetail
    {
        $m = new MenuDetail();
        $m->items = $items; // ['breakfast' => [...], 'lunch' => [...], 'dinner' => [...]]
        return $m;
    }

    // -----------------------------------------------------------------------
    // Single-day: structure
    // -----------------------------------------------------------------------

    #[Test]
    public function single_day_no_menu_returns_empty_rows_and_null_total(): void
    {
        $menuRepo  = Mockery::mock(MenuDetailRepositoryInterface::class);
        $menuRepo->shouldReceive('findByDate')->andReturn(null);
        $menuRepo->shouldReceive('findLatestDate')->andReturn('2026-01-15');

        $roomRepo = Mockery::mock(RoomDetailRepositoryInterface::class);
        $roomRepo->shouldReceive('getAll')->andReturn(new Collection([
            $this->makeRoom(1, '101'),
        ]));

        $service = $this->makeService([
            'menuDetails' => $menuRepo,
            'roomDetails' => $roomRepo,
        ]);

        $result = $service->getOrderReport('2026-01-01', null, null);

        $this->assertEmpty($result['result']['rows']);
        $this->assertNull($result['total']);
        $this->assertSame('2026-01-15', $result['last_menu_date']);
        // columns row 0 still has the Room No header
        $this->assertSame('Room No', $result['columns'][0][0]['title']);
    }

    #[Test]
    public function single_day_with_menu_but_no_orders_produces_zeroed_rows(): void
    {
        $room  = $this->makeRoom(1, '101');
        $item  = $this->makeItem(10, 1, 'Scrambled Eggs'); // cat_id=1 → BA

        $menuRepo = Mockery::mock(MenuDetailRepositoryInterface::class);
        $menuRepo->shouldReceive('findByDate')->andReturn(
            $this->makeMenu(['breakfast' => [10], 'lunch' => [], 'dinner' => []])
        );
        $menuRepo->shouldReceive('findLatestDate')->andReturn('2026-01-01');

        $roomRepo = Mockery::mock(RoomDetailRepositoryInterface::class);
        $roomRepo->shouldReceive('getAll')->andReturn(new Collection([$room]));

        $itemRepo = Mockery::mock(ItemDetailRepositoryInterface::class);
        $itemRepo->shouldReceive('findOrderReportSummaries')->andReturn(new Collection([$item]));

        $orderRepo = Mockery::mock(OrderDetailRepositoryInterface::class);
        $orderRepo->shouldReceive('findOrderReportSummaries')->andReturn(new Collection());

        $service = $this->makeService([
            'menuDetails'  => $menuRepo,
            'roomDetails'  => $roomRepo,
            'itemDetails'  => $itemRepo,
            'orderDetails' => $orderRepo,
        ]);

        $result = $service->getOrderReport('2026-01-01', null, null);

        $this->assertCount(1, $result['result']['rows']);
        $row = $result['result']['rows'][0];

        $this->assertSame(0, $row['BA']);
        $this->assertSame(0, $row['has_breakfast_order']);
        $this->assertSame(0, $row['has_lunch_order']);
        $this->assertSame(0, $row['has_dinner_order']);
        $this->assertSame(0, $row['is_for_guest']);
        // Total should also be zero
        $this->assertSame(0, $result['total']['BA']);
    }

    #[Test]
    public function single_day_with_order_populates_qty_flag_and_total(): void
    {
        $room  = $this->makeRoom(2, '102');
        $item  = $this->makeItem(10, 1, 'Scrambled Eggs'); // cat_id=1 → BA
        $order = $this->makeOrder(2, 10, 3); // qty=3, not guest

        $menuRepo = Mockery::mock(MenuDetailRepositoryInterface::class);
        $menuRepo->shouldReceive('findByDate')->andReturn(
            $this->makeMenu(['breakfast' => [10], 'lunch' => [], 'dinner' => []])
        );
        $menuRepo->shouldReceive('findLatestDate')->andReturn('2026-01-01');

        $roomRepo = Mockery::mock(RoomDetailRepositoryInterface::class);
        $roomRepo->shouldReceive('getAll')->andReturn(new Collection([$room]));

        $itemRepo = Mockery::mock(ItemDetailRepositoryInterface::class);
        $itemRepo->shouldReceive('findOrderReportSummaries')->andReturn(new Collection([$item]));

        $orderRepo = Mockery::mock(OrderDetailRepositoryInterface::class);
        $orderRepo->shouldReceive('findOrderReportSummaries')->andReturn(new Collection([$order]));

        $service = $this->makeService([
            'menuDetails'  => $menuRepo,
            'roomDetails'  => $roomRepo,
            'itemDetails'  => $itemRepo,
            'orderDetails' => $orderRepo,
        ]);

        $result = $service->getOrderReport('2026-01-01', null, null);
        $row = $result['result']['rows'][0];

        $this->assertSame(3, $row['BA']);
        $this->assertSame(1, $row['has_breakfast_order']);
        $this->assertSame(0, $row['has_lunch_order']);
        $this->assertSame(0, $row['has_dinner_order']);
        $this->assertSame(3, $result['total']['BA']);
    }

    #[Test]
    public function single_day_total_sums_across_all_rooms(): void
    {
        $room1 = $this->makeRoom(1, '101');
        $room2 = $this->makeRoom(2, '102');
        $item  = $this->makeItem(10, 1, 'Eggs'); // BA

        // Each room has qty=2
        $menuRepo = Mockery::mock(MenuDetailRepositoryInterface::class);
        $menuRepo->shouldReceive('findByDate')->andReturn(
            $this->makeMenu(['breakfast' => [10], 'lunch' => [], 'dinner' => []])
        );
        $menuRepo->shouldReceive('findLatestDate')->andReturn('2026-01-01');

        $roomRepo = Mockery::mock(RoomDetailRepositoryInterface::class);
        $roomRepo->shouldReceive('getAll')->andReturn(new Collection([$room1, $room2]));

        $itemRepo = Mockery::mock(ItemDetailRepositoryInterface::class);
        $itemRepo->shouldReceive('findOrderReportSummaries')->andReturn(new Collection([$item]));

        $orderRepo = Mockery::mock(OrderDetailRepositoryInterface::class);
        $orderRepo->shouldReceive('findOrderReportSummaries')->andReturn(new Collection([
            $this->makeOrder(1, 10, 2),
            $this->makeOrder(2, 10, 2),
        ]));

        $service = $this->makeService([
            'menuDetails'  => $menuRepo,
            'roomDetails'  => $roomRepo,
            'itemDetails'  => $itemRepo,
            'orderDetails' => $orderRepo,
        ]);

        $result = $service->getOrderReport('2026-01-01', null, null);

        $this->assertSame(4, $result['total']['BA']); // 2 + 2
    }

    #[Test]
    public function single_day_guest_order_appends_G_row(): void
    {
        $room  = $this->makeRoom(1, '101');
        $item  = $this->makeItem(10, 1, 'Eggs'); // BA
        $guestOrder = $this->makeOrder(1, 10, 2, isForGuest: 1);

        $menuRepo = Mockery::mock(MenuDetailRepositoryInterface::class);
        $menuRepo->shouldReceive('findByDate')->andReturn(
            $this->makeMenu(['breakfast' => [10], 'lunch' => [], 'dinner' => []])
        );
        $menuRepo->shouldReceive('findLatestDate')->andReturn('2026-01-01');

        $roomRepo = Mockery::mock(RoomDetailRepositoryInterface::class);
        $roomRepo->shouldReceive('getAll')->andReturn(new Collection([$room]));

        $itemRepo = Mockery::mock(ItemDetailRepositoryInterface::class);
        $itemRepo->shouldReceive('findOrderReportSummaries')->andReturn(new Collection([$item]));

        $orderRepo = Mockery::mock(OrderDetailRepositoryInterface::class);
        $orderRepo->shouldReceive('findOrderReportSummaries')->andReturn(new Collection([$guestOrder]));

        $service = $this->makeService([
            'menuDetails'  => $menuRepo,
            'roomDetails'  => $roomRepo,
            'itemDetails'  => $itemRepo,
            'orderDetails' => $orderRepo,
        ]);

        $result = $service->getOrderReport('2026-01-01', null, null);
        $rows = $result['result']['rows'];

        $this->assertCount(2, $rows);

        $regularRow = $rows[0];
        $guestRow   = $rows[1];

        $this->assertSame('101', $regularRow['room_name']);
        $this->assertSame(0, $regularRow['is_for_guest']);
        $this->assertSame('101 G', $guestRow['room_name']);
        $this->assertSame(1, $guestRow['is_for_guest']);
        $this->assertSame(2, $guestRow['BA']);
    }

    #[Test]
    public function single_day_no_guest_order_omits_G_row(): void
    {
        $room  = $this->makeRoom(1, '101');
        $item  = $this->makeItem(10, 1, 'Eggs');
        $order = $this->makeOrder(1, 10, 1, isForGuest: 0);

        $menuRepo = Mockery::mock(MenuDetailRepositoryInterface::class);
        $menuRepo->shouldReceive('findByDate')->andReturn(
            $this->makeMenu(['breakfast' => [10], 'lunch' => [], 'dinner' => []])
        );
        $menuRepo->shouldReceive('findLatestDate')->andReturn('2026-01-01');

        $roomRepo = Mockery::mock(RoomDetailRepositoryInterface::class);
        $roomRepo->shouldReceive('getAll')->andReturn(new Collection([$room]));

        $itemRepo = Mockery::mock(ItemDetailRepositoryInterface::class);
        $itemRepo->shouldReceive('findOrderReportSummaries')->andReturn(new Collection([$item]));

        $orderRepo = Mockery::mock(OrderDetailRepositoryInterface::class);
        $orderRepo->shouldReceive('findOrderReportSummaries')->andReturn(new Collection([$order]));

        $service = $this->makeService([
            'menuDetails'  => $menuRepo,
            'roomDetails'  => $roomRepo,
            'itemDetails'  => $itemRepo,
            'orderDetails' => $orderRepo,
        ]);

        $result = $service->getOrderReport('2026-01-01', null, null);

        $this->assertCount(1, $result['result']['rows']);
        $this->assertSame('101', $result['result']['rows'][0]['room_name']);
    }

    #[Test]
    public function single_day_column_tooltip_is_string_not_array(): void
    {
        $room = $this->makeRoom(1, '101');
        $item = $this->makeItem(10, 1, 'Scrambled Eggs'); // cat_id=1 → BA

        $menuRepo = Mockery::mock(MenuDetailRepositoryInterface::class);
        $menuRepo->shouldReceive('findByDate')->andReturn(
            $this->makeMenu(['breakfast' => [10], 'lunch' => [], 'dinner' => []])
        );
        $menuRepo->shouldReceive('findLatestDate')->andReturn('2026-01-01');

        $roomRepo = Mockery::mock(RoomDetailRepositoryInterface::class);
        $roomRepo->shouldReceive('getAll')->andReturn(new Collection([$room]));

        $itemRepo = Mockery::mock(ItemDetailRepositoryInterface::class);
        $itemRepo->shouldReceive('findOrderReportSummaries')->andReturn(new Collection([$item]));

        $orderRepo = Mockery::mock(OrderDetailRepositoryInterface::class);
        $orderRepo->shouldReceive('findOrderReportSummaries')->andReturn(new Collection());

        $service = $this->makeService([
            'menuDetails'  => $menuRepo,
            'roomDetails'  => $roomRepo,
            'itemDetails'  => $itemRepo,
            'orderDetails' => $orderRepo,
        ]);

        $result  = $service->getOrderReport('2026-01-01', null, null);
        $columns = $result['columns'][2];

        $baCols = array_filter($columns, fn ($c) => $c['title'] === 'BA');
        $baCol  = reset($baCols);

        $this->assertIsString($baCol['tooltip']);
        $this->assertSame('Scrambled Eggs', $baCol['tooltip']);
    }

    #[Test]
    public function single_day_column_headers_span_correct_meals(): void
    {
        $room       = $this->makeRoom(1, '101');
        $brkItem    = $this->makeItem(10, 1, 'Eggs');    // cat_id=1 → BA (breakfast)
        $dinnerItem = $this->makeItem(20, 13, 'Chicken'); // cat_id=13 → DD (dinner)

        $menuRepo = Mockery::mock(MenuDetailRepositoryInterface::class);
        $menuRepo->shouldReceive('findByDate')->andReturn(
            $this->makeMenu(['breakfast' => [10], 'lunch' => [], 'dinner' => [20]])
        );
        $menuRepo->shouldReceive('findLatestDate')->andReturn('2026-01-01');

        $roomRepo = Mockery::mock(RoomDetailRepositoryInterface::class);
        $roomRepo->shouldReceive('getAll')->andReturn(new Collection([$room]));

        $itemRepo = Mockery::mock(ItemDetailRepositoryInterface::class);
        $itemRepo->shouldReceive('findOrderReportSummaries')
            ->with([10])->andReturn(new Collection([$brkItem]));
        $itemRepo->shouldReceive('findOrderReportSummaries')
            ->with([20])->andReturn(new Collection([$dinnerItem]));

        $orderRepo = Mockery::mock(OrderDetailRepositoryInterface::class);
        $orderRepo->shouldReceive('findOrderReportSummaries')->andReturn(new Collection());

        $service = $this->makeService([
            'menuDetails'  => $menuRepo,
            'roomDetails'  => $roomRepo,
            'itemDetails'  => $itemRepo,
            'orderDetails' => $orderRepo,
        ]);

        $result = $service->getOrderReport('2026-01-01', null, null);
        $spans  = $result['columns'][0];

        // First span is always Room No
        $this->assertSame('Room No', $spans[0]['title']);

        // Find Breakfast and Dinner spans (no Lunch span since no lunch items)
        $titles = array_column($spans, 'title');
        $this->assertContains('Breakfast', $titles);
        $this->assertNotContains('Lunch', $titles);
        $this->assertContains('Dinner', $titles);

        $brkSpan = $spans[array_search('Breakfast', $titles)];
        $this->assertSame(1, $brkSpan['colspan']);

        $dinSpan = $spans[array_search('Dinner', $titles)];
        $this->assertSame(1, $dinSpan['colspan']);
    }

    #[Test]
    public function single_day_room_with_special_instructions_sets_flag(): void
    {
        $room = $this->makeRoom(1, '101', 'Low sodium');
        $item = $this->makeItem(10, 1, 'Eggs');

        $menuRepo = Mockery::mock(MenuDetailRepositoryInterface::class);
        $menuRepo->shouldReceive('findByDate')->andReturn(
            $this->makeMenu(['breakfast' => [10], 'lunch' => [], 'dinner' => []])
        );
        $menuRepo->shouldReceive('findLatestDate')->andReturn('2026-01-01');

        $roomRepo = Mockery::mock(RoomDetailRepositoryInterface::class);
        $roomRepo->shouldReceive('getAll')->andReturn(new Collection([$room]));

        $itemRepo = Mockery::mock(ItemDetailRepositoryInterface::class);
        $itemRepo->shouldReceive('findOrderReportSummaries')->andReturn(new Collection([$item]));

        $orderRepo = Mockery::mock(OrderDetailRepositoryInterface::class);
        $orderRepo->shouldReceive('findOrderReportSummaries')->andReturn(new Collection());

        $service = $this->makeService([
            'menuDetails'  => $menuRepo,
            'roomDetails'  => $roomRepo,
            'itemDetails'  => $itemRepo,
            'orderDetails' => $orderRepo,
        ]);

        $result = $service->getOrderReport('2026-01-01', null, null);

        $this->assertSame(1, $result['result']['rows'][0]['has_special_ins']);
    }

    // -----------------------------------------------------------------------
    // Date range: structure and accumulation
    // -----------------------------------------------------------------------

    #[Test]
    public function range_with_no_menus_returns_empty_rows_and_null_total(): void
    {
        $menuRepo = Mockery::mock(MenuDetailRepositoryInterface::class);
        $menuRepo->shouldReceive('findByDate')->andReturn(null);
        $menuRepo->shouldReceive('findLatestDate')->andReturn('2026-01-05');

        $roomRepo = Mockery::mock(RoomDetailRepositoryInterface::class);
        $roomRepo->shouldReceive('getAll')->andReturn(new Collection([
            $this->makeRoom(1, '101'),
        ]));

        $service = $this->makeService([
            'menuDetails' => $menuRepo,
            'roomDetails' => $roomRepo,
        ]);

        $result = $service->getOrderReport(null, '2026-01-01', '2026-01-03');

        $this->assertEmpty($result['result']['rows']);
        $this->assertNull($result['total']);
    }

    #[Test]
    public function range_quantities_accumulate_across_dates(): void
    {
        $room = $this->makeRoom(1, '101');
        $item = $this->makeItem(10, 1, 'Eggs'); // BA

        $menuRepo = Mockery::mock(MenuDetailRepositoryInterface::class);
        $menuRepo->shouldReceive('findByDate')->andReturn(
            $this->makeMenu(['breakfast' => [10], 'lunch' => [], 'dinner' => []])
        );
        $menuRepo->shouldReceive('findLatestDate')->andReturn('2026-01-02');

        $roomRepo = Mockery::mock(RoomDetailRepositoryInterface::class);
        $roomRepo->shouldReceive('getAll')->andReturn(new Collection([$room]));

        $itemRepo = Mockery::mock(ItemDetailRepositoryInterface::class);
        $itemRepo->shouldReceive('findOrderReportSummaries')->andReturn(new Collection([$item]));

        $orderRepo = Mockery::mock(OrderDetailRepositoryInterface::class);
        // Day 1: qty=1, Day 2: qty=2 (both returned by the same mock since params aren't constrained)
        $orderRepo->shouldReceive('findOrderReportSummaries')
            ->once()->with('2026-01-01', Mockery::any())
            ->andReturn(new Collection([$this->makeOrder(1, 10, 1)]));
        $orderRepo->shouldReceive('findOrderReportSummaries')
            ->once()->with('2026-01-02', Mockery::any())
            ->andReturn(new Collection([$this->makeOrder(1, 10, 2)]));

        $service = $this->makeService([
            'menuDetails'  => $menuRepo,
            'roomDetails'  => $roomRepo,
            'itemDetails'  => $itemRepo,
            'orderDetails' => $orderRepo,
        ]);

        $result = $service->getOrderReport(null, '2026-01-01', '2026-01-02');
        $row    = $result['result']['rows'][0];

        $this->assertSame(3, $row['BA']); // 1 + 2
        $this->assertSame(3, $result['total']['BA']);
    }

    #[Test]
    public function range_skips_dates_with_no_menu_and_still_accumulates_others(): void
    {
        $room = $this->makeRoom(1, '101');
        $item = $this->makeItem(10, 1, 'Eggs');
        $menu = $this->makeMenu(['breakfast' => [10], 'lunch' => [], 'dinner' => []]);

        $menuRepo = Mockery::mock(MenuDetailRepositoryInterface::class);
        $menuRepo->shouldReceive('findByDate')->with('2026-01-01')->andReturn($menu);
        $menuRepo->shouldReceive('findByDate')->with('2026-01-02')->andReturn(null); // no menu
        $menuRepo->shouldReceive('findByDate')->with('2026-01-03')->andReturn($menu);
        $menuRepo->shouldReceive('findLatestDate')->andReturn('2026-01-03');

        $roomRepo = Mockery::mock(RoomDetailRepositoryInterface::class);
        $roomRepo->shouldReceive('getAll')->andReturn(new Collection([$room]));

        $itemRepo = Mockery::mock(ItemDetailRepositoryInterface::class);
        $itemRepo->shouldReceive('findOrderReportSummaries')->andReturn(new Collection([$item]));

        $orderRepo = Mockery::mock(OrderDetailRepositoryInterface::class);
        $orderRepo->shouldReceive('findOrderReportSummaries')
            ->with('2026-01-01', Mockery::any())->andReturn(new Collection([$this->makeOrder(1, 10, 2)]));
        $orderRepo->shouldReceive('findOrderReportSummaries')
            ->with('2026-01-03', Mockery::any())->andReturn(new Collection([$this->makeOrder(1, 10, 3)]));

        $service = $this->makeService([
            'menuDetails'  => $menuRepo,
            'roomDetails'  => $roomRepo,
            'itemDetails'  => $itemRepo,
            'orderDetails' => $orderRepo,
        ]);

        $result = $service->getOrderReport(null, '2026-01-01', '2026-01-03');

        $this->assertSame(5, $result['result']['rows'][0]['BA']); // 2 + 3
    }

    #[Test]
    public function range_rows_sorted_by_room_id(): void
    {
        // Rooms returned in reverse order from the repo; usort should fix it
        $room2 = $this->makeRoom(2, '102');
        $room1 = $this->makeRoom(1, '101');
        $item  = $this->makeItem(10, 1, 'Eggs');
        $menu  = $this->makeMenu(['breakfast' => [10], 'lunch' => [], 'dinner' => []]);

        $menuRepo = Mockery::mock(MenuDetailRepositoryInterface::class);
        $menuRepo->shouldReceive('findByDate')->andReturn($menu);
        $menuRepo->shouldReceive('findLatestDate')->andReturn('2026-01-01');

        $roomRepo = Mockery::mock(RoomDetailRepositoryInterface::class);
        $roomRepo->shouldReceive('getAll')->andReturn(new Collection([$room2, $room1]));

        $itemRepo = Mockery::mock(ItemDetailRepositoryInterface::class);
        $itemRepo->shouldReceive('findOrderReportSummaries')->andReturn(new Collection([$item]));

        $orderRepo = Mockery::mock(OrderDetailRepositoryInterface::class);
        $orderRepo->shouldReceive('findOrderReportSummaries')->andReturn(new Collection());

        $service = $this->makeService([
            'menuDetails'  => $menuRepo,
            'roomDetails'  => $roomRepo,
            'itemDetails'  => $itemRepo,
            'orderDetails' => $orderRepo,
        ]);

        $result = $service->getOrderReport(null, '2026-01-01', '2026-01-01');
        $rows   = $result['result']['rows'];

        $this->assertSame(1, $rows[0]['room_id']);
        $this->assertSame(2, $rows[1]['room_id']);
    }

    #[Test]
    public function range_column_tooltip_is_date_indexed_array_not_string(): void
    {
        $room = $this->makeRoom(1, '101');
        $item = $this->makeItem(10, 1, 'Scrambled Eggs'); // BA
        $menu = $this->makeMenu(['breakfast' => [10], 'lunch' => [], 'dinner' => []]);

        $menuRepo = Mockery::mock(MenuDetailRepositoryInterface::class);
        $menuRepo->shouldReceive('findByDate')->andReturn($menu);
        $menuRepo->shouldReceive('findLatestDate')->andReturn('2026-01-01');

        $roomRepo = Mockery::mock(RoomDetailRepositoryInterface::class);
        $roomRepo->shouldReceive('getAll')->andReturn(new Collection([$room]));

        $itemRepo = Mockery::mock(ItemDetailRepositoryInterface::class);
        $itemRepo->shouldReceive('findOrderReportSummaries')->andReturn(new Collection([$item]));

        $orderRepo = Mockery::mock(OrderDetailRepositoryInterface::class);
        $orderRepo->shouldReceive('findOrderReportSummaries')->andReturn(new Collection());

        $service = $this->makeService([
            'menuDetails'  => $menuRepo,
            'roomDetails'  => $roomRepo,
            'itemDetails'  => $itemRepo,
            'orderDetails' => $orderRepo,
        ]);

        $result  = $service->getOrderReport(null, '2026-01-01', '2026-01-01');
        $columns = $result['columns'][2];

        $baCols = array_filter($columns, fn ($c) => $c['title'] === 'BA');
        $baCol  = reset($baCols);

        $this->assertIsArray($baCol['tooltip']);
        $this->assertArrayHasKey('2026-01-01', $baCol['tooltip']);
        $this->assertSame('Scrambled Eggs', $baCol['tooltip']['2026-01-01']);
    }

    #[Test]
    public function range_does_not_include_single_day_flags_in_rows(): void
    {
        $room = $this->makeRoom(1, '101');
        $item = $this->makeItem(10, 1, 'Eggs');

        $menuRepo = Mockery::mock(MenuDetailRepositoryInterface::class);
        $menuRepo->shouldReceive('findByDate')->andReturn(
            $this->makeMenu(['breakfast' => [10], 'lunch' => [], 'dinner' => []])
        );
        $menuRepo->shouldReceive('findLatestDate')->andReturn('2026-01-01');

        $roomRepo = Mockery::mock(RoomDetailRepositoryInterface::class);
        $roomRepo->shouldReceive('getAll')->andReturn(new Collection([$room]));

        $itemRepo = Mockery::mock(ItemDetailRepositoryInterface::class);
        $itemRepo->shouldReceive('findOrderReportSummaries')->andReturn(new Collection([$item]));

        $orderRepo = Mockery::mock(OrderDetailRepositoryInterface::class);
        $orderRepo->shouldReceive('findOrderReportSummaries')->andReturn(new Collection());

        $service = $this->makeService([
            'menuDetails'  => $menuRepo,
            'roomDetails'  => $roomRepo,
            'itemDetails'  => $itemRepo,
            'orderDetails' => $orderRepo,
        ]);

        $result = $service->getOrderReport(null, '2026-01-01', '2026-01-01');
        $row    = $result['result']['rows'][0];

        $this->assertArrayNotHasKey('has_breakfast_order', $row);
        $this->assertArrayNotHasKey('has_lunch_order',     $row);
        $this->assertArrayNotHasKey('has_dinner_order',    $row);
        $this->assertArrayNotHasKey('is_for_guest',        $row);
    }

    #[Test]
    public function range_guest_order_adds_G_row(): void
    {
        $room       = $this->makeRoom(1, '101');
        $item       = $this->makeItem(10, 1, 'Eggs');
        $guestOrder = $this->makeOrder(1, 10, 2, isForGuest: 1);

        $menuRepo = Mockery::mock(MenuDetailRepositoryInterface::class);
        $menuRepo->shouldReceive('findByDate')->andReturn(
            $this->makeMenu(['breakfast' => [10], 'lunch' => [], 'dinner' => []])
        );
        $menuRepo->shouldReceive('findLatestDate')->andReturn('2026-01-01');

        $roomRepo = Mockery::mock(RoomDetailRepositoryInterface::class);
        $roomRepo->shouldReceive('getAll')->andReturn(new Collection([$room]));

        $itemRepo = Mockery::mock(ItemDetailRepositoryInterface::class);
        $itemRepo->shouldReceive('findOrderReportSummaries')->andReturn(new Collection([$item]));

        $orderRepo = Mockery::mock(OrderDetailRepositoryInterface::class);
        $orderRepo->shouldReceive('findOrderReportSummaries')->andReturn(new Collection([$guestOrder]));

        $service = $this->makeService([
            'menuDetails'  => $menuRepo,
            'roomDetails'  => $roomRepo,
            'itemDetails'  => $itemRepo,
            'orderDetails' => $orderRepo,
        ]);

        $result = $service->getOrderReport(null, '2026-01-01', '2026-01-01');
        $rows   = $result['result']['rows'];

        $this->assertCount(2, $rows);
        $names = array_column($rows, 'room_name');
        $this->assertContains('101',   $names);
        $this->assertContains('101 G', $names);
    }

    // -----------------------------------------------------------------------
    // General / shared behaviour
    // -----------------------------------------------------------------------

    #[Test]
    public function last_menu_date_always_comes_from_repo(): void
    {
        $menuRepo = Mockery::mock(MenuDetailRepositoryInterface::class);
        $menuRepo->shouldReceive('findByDate')->andReturn(null);
        $menuRepo->shouldReceive('findLatestDate')->once()->andReturn('2026-03-01');

        $roomRepo = Mockery::mock(RoomDetailRepositoryInterface::class);
        $roomRepo->shouldReceive('getAll')->andReturn(new Collection());

        $service = $this->makeService([
            'menuDetails' => $menuRepo,
            'roomDetails' => $roomRepo,
        ]);

        $result = $service->getOrderReport('2026-01-01', null, null);

        $this->assertSame('2026-03-01', $result['last_menu_date']);
    }

    #[Test]
    public function column_order_is_breakfast_then_lunch_then_dinner(): void
    {
        $room       = $this->makeRoom(1, '101');
        $brkItem    = $this->makeItem(10, 1, 'Eggs');    // cat_id=1 → BA (breakfast)
        $lunchItem  = $this->makeItem(20, 2, 'Soup');    // cat_id=2 → LS (lunch)
        $dinnerItem = $this->makeItem(30, 13, 'Chicken'); // cat_id=13 → DD (dinner)

        $menuRepo = Mockery::mock(MenuDetailRepositoryInterface::class);
        $menuRepo->shouldReceive('findByDate')->andReturn(
            $this->makeMenu(['breakfast' => [10], 'lunch' => [20], 'dinner' => [30]])
        );
        $menuRepo->shouldReceive('findLatestDate')->andReturn('2026-01-01');

        $roomRepo = Mockery::mock(RoomDetailRepositoryInterface::class);
        $roomRepo->shouldReceive('getAll')->andReturn(new Collection([$room]));

        $itemRepo = Mockery::mock(ItemDetailRepositoryInterface::class);
        $itemRepo->shouldReceive('findOrderReportSummaries')->with([10])->andReturn(new Collection([$brkItem]));
        $itemRepo->shouldReceive('findOrderReportSummaries')->with([20])->andReturn(new Collection([$lunchItem]));
        $itemRepo->shouldReceive('findOrderReportSummaries')->with([30])->andReturn(new Collection([$dinnerItem]));

        $orderRepo = Mockery::mock(OrderDetailRepositoryInterface::class);
        $orderRepo->shouldReceive('findOrderReportSummaries')->andReturn(new Collection());

        $service = $this->makeService([
            'menuDetails'  => $menuRepo,
            'roomDetails'  => $roomRepo,
            'itemDetails'  => $itemRepo,
            'orderDetails' => $orderRepo,
        ]);

        $result  = $service->getOrderReport('2026-01-01', null, null);
        $columns = $result['columns'][2];
        $titles  = array_column($columns, 'title');

        $posBA = array_search('BA', $titles);
        $posLS = array_search('LS', $titles);
        $posDD = array_search('DD', $titles);

        $this->assertNotFalse($posBA);
        $this->assertNotFalse($posLS);
        $this->assertNotFalse($posDD);
        $this->assertLessThan($posLS, $posBA, 'BA (breakfast) should come before LS (lunch)');
        $this->assertLessThan($posDD, $posLS, 'LS (lunch) should come before DD (dinner)');
    }
}
