<?php

namespace Tests\Feature\DiningApp;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for POST /api/update-order and POST /api/multi-order-update.
 *
 * update-order:    single-date order save (resident or guest, with service flags)
 * multi-order-update: bulk save across multiple dates
 */
class UpdateOrderTest extends DiningAppTestCase
{
    // -----------------------------------------------------------------------
    // update-order: validation
    // -----------------------------------------------------------------------

    #[Test]
    public function update_order_requires_room_id(): void
    {
        $room = $this->makeRoom();

        $this->withHeaders($this->residentHeaders($room))
            ->postJson('/api/update-order', ['date' => '2026-03-01'])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '2', 'ResponseText' => 'Room id or date is missing']);
    }

    #[Test]
    public function update_order_requires_date(): void
    {
        $room = $this->makeRoom();

        $this->withHeaders($this->residentHeaders($room))
            ->postJson('/api/update-order', ['room_id' => $room->id])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '2', 'ResponseText' => 'Room id or date is missing']);
    }

    #[Test]
    public function update_order_returns_not_found_for_unknown_room(): void
    {
        $room = $this->makeRoom();

        $this->withHeaders($this->residentHeaders($room))
            ->postJson('/api/update-order', ['room_id' => 9999, 'date' => '2026-03-01'])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '2', 'ResponseText' => 'Room Not Found']);
    }

    #[Test]
    public function update_order_requires_authentication(): void
    {
        $this->postJson('/api/update-order', ['room_id' => 1, 'date' => '2026-03-01'])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '11']);
    }

    // -----------------------------------------------------------------------
    // update-order: service flags persist
    // -----------------------------------------------------------------------

    #[Test]
    public function update_order_saves_tray_service_flags(): void
    {
        $room = $this->makeRoom();

        // Pre-seed a row so upsertByFilters finds an existing record to UPDATE.
        // Without this, it would try to INSERT without required columns (item_id, status),
        // which SQLite would reject with a NOT NULL constraint violation.
        DB::table('order_details')->insert([
            'room_id'             => $room->id,
            'date'                => '2026-03-01',
            'item_id'             => 0,
            'quantity'            => 0,
            'status'              => 0,
            'is_for_guest'        => 0,
            'is_brk_tray_service' => 0,
        ]);

        $this->withHeaders($this->residentHeaders($room))
            ->postJson('/api/update-order', [
                'room_id'              => $room->id,
                'date'                 => '2026-03-01',
                'is_brk_tray_service'  => 1,
                'is_lunch_tray_service' => 0,
                'is_dinner_tray_service' => 0,
            ])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '1']);

        $this->assertDatabaseHas('order_details', [
            'room_id'             => $room->id,
            'date'                => '2026-03-01',
            'is_brk_tray_service' => 1,
        ]);
    }

    #[Test]
    public function update_order_returns_item_and_order_id_arrays(): void
    {
        $room = $this->makeRoom();

        $data = $this->withHeaders($this->residentHeaders($room))
            ->postJson('/api/update-order', [
                'room_id' => $room->id,
                'date'    => '2026-03-01',
            ])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '1', 'ResponseText' => 'success'])
            ->json();

        $this->assertIsArray($data['item_id']);
        $this->assertIsArray($data['order_id']);
    }

    // -----------------------------------------------------------------------
    // update-order: guest orders and occupancy
    // -----------------------------------------------------------------------

    #[Test]
    public function update_order_records_guest_occupancy(): void
    {
        $room = $this->makeRoom();

        $this->withHeaders($this->residentHeaders($room))
            ->postJson('/api/update-order', [
                'room_id'      => $room->id,
                'date'         => '2026-03-01',
                'is_for_guest' => 1,
                'occupancy'    => 3,
            ])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '1']);

        $this->assertDatabaseHas('date_wise_occupancies', [
            'room_id'   => $room->id,
            'date'      => '2026-03-01',
            'occupancy' => 3,
        ]);
    }

    // -----------------------------------------------------------------------
    // multi-order-update: validation
    // -----------------------------------------------------------------------

    #[Test]
    public function multi_order_update_returns_not_found_for_unknown_room(): void
    {
        $room = $this->makeRoom();
        $payload = json_encode([[
            'date'                 => '2026-03-01',
            'is_brk_tray_service'  => 0,
            'is_lunch_tray_service' => 0,
            'is_dinner_tray_service' => 0,
        ]]);

        $this->withHeaders($this->residentHeaders($room))
            ->postJson('/api/multi-order-update', [
                'room_id'          => 9999,
                'current_date'     => '2026-03-01',
                'orders_to_change' => $payload,
            ])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '2', 'ResponseText' => 'Room not found']);
    }

    #[Test]
    public function multi_order_update_saves_service_flags_for_multiple_dates(): void
    {
        $room = $this->makeRoom();

        // Pre-seed rows so upsertByFilters updates them rather than trying to INSERT
        // without required columns (item_id, status have no default and are NOT NULL).
        foreach (['2026-03-01', '2026-03-02'] as $date) {
            DB::table('order_details')->insert([
                'room_id' => $room->id, 'date' => $date,
                'item_id' => 0, 'quantity' => 0, 'status' => 0, 'is_for_guest' => 0,
            ]);
        }

        $payload = json_encode([
            [
                'date'                 => '2026-03-01',
                'is_brk_tray_service'  => 1,
                'is_lunch_tray_service' => 0,
                'is_dinner_tray_service' => 0,
                'is_brk_escort_service' => 0,
                'is_lunch_escort_service' => 0,
                'is_dinner_escort_service' => 0,
                'is_brk_takeout_service' => 0,
                'is_lunch_takeout_service' => 0,
                'is_dinner_takeout_service' => 0,
            ],
            [
                'date'                 => '2026-03-02',
                'is_brk_tray_service'  => 0,
                'is_lunch_tray_service' => 1,
                'is_dinner_tray_service' => 0,
                'is_brk_escort_service' => 0,
                'is_lunch_escort_service' => 0,
                'is_dinner_escort_service' => 0,
                'is_brk_takeout_service' => 0,
                'is_lunch_takeout_service' => 0,
                'is_dinner_takeout_service' => 0,
            ],
        ]);

        $this->withHeaders($this->residentHeaders($room))
            ->postJson('/api/multi-order-update', [
                'room_id'          => $room->id,
                'current_date'     => '2026-03-01',
                'orders_to_change' => $payload,
            ])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '1']);

        $this->assertDatabaseHas('order_details', [
            'room_id'             => $room->id,
            'date'                => '2026-03-01',
            'is_brk_tray_service' => 1,
        ]);
        $this->assertDatabaseHas('order_details', [
            'room_id'               => $room->id,
            'date'                  => '2026-03-02',
            'is_lunch_tray_service' => 1,
        ]);
    }
}
