<?php

namespace Tests\Unit\Repositories;

use App\Models\OrderDetail;
use App\Models\RoomDetail;
use App\Repositories\Eloquent\OrderDetailRepository;
use App\Repositories\Eloquent\RoomDetailRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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

    /** @test */
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

    /** @test */
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

        $this->orders->updateByFilters([
            'room_id' => 3,
            'date' => $date,
        ], [
            'quantity' => 5,
        ]);

        $updated = $this->orders->getAll(['room_id' => 3])->first();

        $this->assertSame(5, $updated->quantity);
    }

    /** @test */
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

    /** @test */
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
