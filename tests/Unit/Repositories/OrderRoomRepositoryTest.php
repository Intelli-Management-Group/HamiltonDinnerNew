<?php

namespace Tests\Unit\Repositories;

use App\Models\OrderDetail;
use App\Models\RoomDetail;
use App\Repositories\Eloquent\OrderDetailRepository;
use App\Repositories\Eloquent\RoomDetailRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class OrderRoomRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private OrderDetailRepository $orders;
    private RoomDetailRepository $rooms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orders = new OrderDetailRepository(new OrderDetail());
        $this->rooms = new RoomDetailRepository(new RoomDetail());
    }

    #[Test]
    public function it_filters_orders_by_room_and_guest_status()
    {
        $date = now()->toDateString();

        OrderDetail::forceCreate([
            'room_id' => 1,
            'date' => $date,
            'item_id' => 10,
            'quantity' => 1,
            'status' => 0,
            'is_for_guest' => 0,
        ]);

        OrderDetail::forceCreate([
            'room_id' => 2,
            'date' => $date,
            'item_id' => 11,
            'quantity' => 2,
            'status' => 0,
            'is_for_guest' => 1,
        ]);

        $results = $this->orders->getAll([
            'room_id' => 2,
            'is_for_guest' => 1,
        ]);

        $this->assertCount(1, $results);
        $this->assertSame(2, $results->first()->room_id);
    }

    #[Test]
    public function it_updates_orders_by_filters()
    {
        $date = now()->toDateString();

        OrderDetail::forceCreate([
            'room_id' => 3,
            'date' => $date,
            'item_id' => 20,
            'quantity' => 1,
            'status' => 0,
            'is_for_guest' => 0,
        ]);

        $this->orders->upsertByFilters([
            'room_id' => 3,
            'date' => $date,
        ], [
            'quantity' => 5,
        ]);

        $updated = $this->orders->getAll(['room_id' => 3])->first();

        $this->assertSame(5, $updated->quantity);
    }

    #[Test]
    public function it_returns_order_report_summaries()
    {
        $date = now()->toDateString();

        OrderDetail::forceCreate([
            'room_id' => 1,
            'date' => $date,
            'item_id' => 30,
            'quantity' => 1,
            'status' => 0,
            'is_for_guest' => 0,
        ]);

        $results = $this->orders->findOrderReportSummaries($date, [30]);

        $this->assertCount(1, $results);
        $this->assertSame(30, $results->first()->item_id);
    }

    #[Test]
    public function it_sums_quantities_by_date_and_item()
    {
        $date = now()->toDateString();

        OrderDetail::forceCreate([
            'room_id' => 1,
            'date' => $date,
            'item_id' => 40,
            'quantity' => 2,
            'status' => 0,
            'is_for_guest' => 0,
        ]);

        OrderDetail::forceCreate([
            'room_id' => 2,
            'date' => $date,
            'item_id' => 40,
            'quantity' => 3,
            'status' => 0,
            'is_for_guest' => 1,
        ]);

        OrderDetail::forceCreate([
            'room_id' => 3,
            'date' => $date,
            'item_id' => 41,
            'quantity' => 5,
            'status' => 0,
            'is_for_guest' => 0,
        ]);

        $sum = $this->orders->sumQuantityByDateAndItem($date, 40);

        $this->assertSame(5, $sum);
    }

    #[Test]
    public function it_filters_orders_by_item_options()
    {
        $date = now()->toDateString();

        OrderDetail::forceCreate([
            'room_id' => 1, 'date' => $date, 'item_id' => 50,
            'quantity' => 1, 'status' => 0, 'is_for_guest' => 0,
            'item_options' => 3,
        ]);
        OrderDetail::forceCreate([
            'room_id' => 2, 'date' => $date, 'item_id' => 50,
            'quantity' => 1, 'status' => 0, 'is_for_guest' => 0,
            'item_options' => 7,
        ]);

        $results = $this->orders->getAll([
            'date' => $date,
            'item_id' => 50,
            'item_options' => 3,
        ]);

        $this->assertCount(1, $results);
        $this->assertEquals(3, $results->first()->item_options);
    }

    #[Test]
    public function it_filters_rooms_by_name_and_active_flag()
    {
        RoomDetail::create([
            'room_name' => 'A1',
            'is_active' => 1,
        ]);

        RoomDetail::create([
            'room_name' => 'B2',
            'is_active' => 0,
        ]);

        $results = $this->rooms->getAll([
            'room_name' => 'A1',
            'is_active' => 1,
        ]);

        $this->assertCount(1, $results);
        $this->assertSame('A1', $results->first()->room_name);
    }
}
