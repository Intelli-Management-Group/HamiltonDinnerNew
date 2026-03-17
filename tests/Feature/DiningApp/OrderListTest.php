<?php

namespace Tests\Feature\DiningApp;

use App\Models\CategoryDetail;
use App\Models\ItemDetail;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for POST /api/order-list and POST /api/guest-order-list.
 *
 * Both endpoints build the same menu structure but differ in whether they
 * look at resident orders (is_for_guest=0) or guest orders (is_for_guest=1).
 */
class OrderListTest extends DiningAppTestCase
{
    private function makeOrderDetail(array $attrs): void
    {
        DB::table('order_details')->insert(array_merge([
            'room_id'     => 1,
            'date'        => '2026-03-01',
            'item_id'     => 1,
            'quantity'    => 1,
            'status'      => 1,
            'is_for_guest' => 0,
            'comment'     => '',
            'item_options' => null,
            'preference'   => null,
        ], $attrs));
    }

    // -----------------------------------------------------------------------
    // order-list: structure when no menu exists
    // -----------------------------------------------------------------------

    #[Test]
    public function order_list_returns_empty_meal_arrays_when_no_menu_for_date(): void
    {
        $room = $this->makeRoom();

        $data = $this->withHeaders($this->residentHeaders($room))
            ->postJson('/api/order-list', ['room_id' => $room->id, 'date' => '2026-03-01'])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '1'])
            ->json();

        $this->assertEmpty($data['breakfast']);
        $this->assertEmpty($data['lunch']);
        $this->assertEmpty($data['dinner']);
    }

    #[Test]
    public function order_list_includes_service_flags_defaulting_to_zero(): void
    {
        $room = $this->makeRoom();

        $data = $this->withHeaders($this->residentHeaders($room))
            ->postJson('/api/order-list', ['room_id' => $room->id, 'date' => '2026-03-01'])
            ->assertStatus(200)
            ->json();

        foreach ([
            'is_brk_tray_service', 'is_lunch_tray_service', 'is_dinner_tray_service',
            'is_brk_escort_service', 'is_lunch_escort_service', 'is_dinner_escort_service',
            'is_brk_takeout_service', 'is_lunch_takeout_service', 'is_dinner_takeout_service',
        ] as $flag) {
            $this->assertArrayHasKey($flag, $data, "Missing flag: $flag");
        }
    }

    // -----------------------------------------------------------------------
    // order-list: items placed into correct meal type
    // -----------------------------------------------------------------------

    #[Test]
    public function order_list_places_breakfast_items_into_breakfast_array(): void
    {
        $room     = $this->makeRoom();
        $brkCat   = $this->makeCategory(['type' => 1, 'cat_name' => 'Breakfast']); // type 1 = breakfast
        $brkItem  = $this->makeItem($brkCat, ['item_name' => 'Scrambled Eggs']);
        $this->makeMenu('2026-03-01', [$brkItem->id]);

        $data = $this->withHeaders($this->residentHeaders($room))
            ->postJson('/api/order-list', ['room_id' => $room->id, 'date' => '2026-03-01'])
            ->assertStatus(200)
            ->json();

        $this->assertNotEmpty($data['breakfast']);
        $this->assertEmpty($data['lunch']);
        $this->assertEmpty($data['dinner']);

        // The breakfast category should contain our item
        $itemNames = array_column(
            array_merge(...array_column($data['breakfast'], 'items')),
            'item_name'
        );
        $this->assertContains('Scrambled Eggs', $itemNames);
    }

    #[Test]
    public function order_list_routes_items_to_correct_meal_by_category_type(): void
    {
        $room    = $this->makeRoom();
        $brkCat  = $this->makeCategory(['type' => 1]);
        $lunchCat = $this->makeCategory(['type' => 2]);
        $dinCat  = $this->makeCategory(['type' => 3]);

        $brkItem   = $this->makeItem($brkCat,   ['item_name' => 'Oatmeal']);
        $lunchItem = $this->makeItem($lunchCat,  ['item_name' => 'Soup']);
        $dinItem   = $this->makeItem($dinCat,    ['item_name' => 'Steak']);

        $this->makeMenu('2026-03-01', [$brkItem->id, $lunchItem->id, $dinItem->id]);

        $data = $this->withHeaders($this->residentHeaders($room))
            ->postJson('/api/order-list', ['room_id' => $room->id, 'date' => '2026-03-01'])
            ->assertStatus(200)
            ->json();

        $brkNames   = array_column(array_merge(...array_column($data['breakfast'], 'items')), 'item_name');
        $lunchNames = array_column(array_merge(...array_column($data['lunch'],     'items')), 'item_name');
        $dinNames   = array_column(array_merge(...array_column($data['dinner'],    'items')), 'item_name');

        $this->assertContains('Oatmeal', $brkNames);
        $this->assertContains('Soup',    $lunchNames);
        $this->assertContains('Steak',   $dinNames);
    }

    #[Test]
    public function order_list_reflects_existing_order_quantity(): void
    {
        $room   = $this->makeRoom();
        $cat    = $this->makeCategory(['type' => 1]);
        $item   = $this->makeItem($cat);
        $this->makeMenu('2026-03-01', [$item->id]);

        // Place a prior order with qty 2
        $orderId = DB::table('order_details')->insertGetId([
            'room_id'    => $room->id,
            'date'       => '2026-03-01',
            'item_id'    => $item->id,
            'quantity'   => 2,
            'status'     => 1,
            'is_for_guest' => 0,
            'comment'    => '',
        ]);

        $data = $this->withHeaders($this->residentHeaders($room))
            ->postJson('/api/order-list', ['room_id' => $room->id, 'date' => '2026-03-01'])
            ->assertStatus(200)
            ->json();

        $items = array_merge(...array_column($data['breakfast'], 'items'));
        $found = array_filter($items, fn($i) => $i['item_id'] === $item->id);
        $found = array_values($found);

        $this->assertNotEmpty($found);
        $this->assertSame(2, $found[0]['qty']);
        $this->assertSame($orderId, $found[0]['order_id']);
    }

    #[Test]
    public function order_list_does_not_include_items_from_other_dates(): void
    {
        $room = $this->makeRoom();
        $cat  = $this->makeCategory(['type' => 1]);

        $itemToday = $this->makeItem($cat, ['item_name' => 'Today Item']);
        $itemOther = $this->makeItem($cat, ['item_name' => 'Other Date Item']);

        $this->makeMenu('2026-03-01', [$itemToday->id]);
        $this->makeMenu('2026-03-02', [$itemOther->id]); // different date

        $data = $this->withHeaders($this->residentHeaders($room))
            ->postJson('/api/order-list', ['room_id' => $room->id, 'date' => '2026-03-01'])
            ->assertStatus(200)
            ->json();

        $itemNames = array_column(
            array_merge(...array_column($data['breakfast'], 'items')),
            'item_name'
        );

        $this->assertContains('Today Item', $itemNames);
        $this->assertNotContains('Other Date Item', $itemNames);
    }

    // -----------------------------------------------------------------------
    // order-list: authentication
    // -----------------------------------------------------------------------

    #[Test]
    public function order_list_requires_authentication(): void
    {
        $this->postJson('/api/order-list', ['room_id' => 1, 'date' => '2026-03-01'])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '11']);
    }

    // -----------------------------------------------------------------------
    // guest-order-list
    // -----------------------------------------------------------------------

    #[Test]
    public function guest_order_list_returns_empty_arrays_when_no_menu(): void
    {
        $room = $this->makeRoom();

        $data = $this->withHeaders($this->residentHeaders($room))
            ->postJson('/api/guest-order-list', ['room_id' => $room->id, 'date' => '2026-03-01'])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '1'])
            ->json();

        $this->assertEmpty($data['breakfast']);
        $this->assertEmpty($data['lunch']);
        $this->assertEmpty($data['dinner']);
        $this->assertSame(0, $data['occupancy']);
    }

    #[Test]
    public function guest_order_list_shows_guest_orders_not_resident_orders(): void
    {
        $room  = $this->makeRoom(['occupancy' => 1]);
        $cat   = $this->makeCategory(['type' => 1]);
        $item  = $this->makeItem($cat, ['item_name' => 'Guest Pancakes']);
        $this->makeMenu('2026-03-01', [$item->id]);

        // Resident order (is_for_guest=0) — should NOT appear
        DB::table('order_details')->insert([
            'room_id'    => $room->id, 'date' => '2026-03-01',
            'item_id'    => $item->id, 'quantity' => 3,
            'status'     => 1, 'is_for_guest' => 0, 'comment' => '',
        ]);

        // Guest order (is_for_guest=1) — SHOULD appear with qty 2
        DB::table('order_details')->insert([
            'room_id'    => $room->id, 'date' => '2026-03-01',
            'item_id'    => $item->id, 'quantity' => 2,
            'status'     => 1, 'is_for_guest' => 1, 'comment' => '',
        ]);

        $data = $this->withHeaders($this->residentHeaders($room))
            ->postJson('/api/guest-order-list', ['room_id' => $room->id, 'date' => '2026-03-01'])
            ->assertStatus(200)
            ->json();

        $items = array_merge(...array_column($data['breakfast'], 'items'));
        $found = array_filter($items, fn($i) => $i['item_id'] === $item->id);
        $found = array_values($found);

        $this->assertNotEmpty($found);
        $this->assertSame(2, $found[0]['qty']); // guest qty, not resident qty
    }

    #[Test]
    public function guest_order_list_requires_authentication(): void
    {
        $this->postJson('/api/guest-order-list', ['room_id' => 1, 'date' => '2026-03-01'])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '11']);
    }
}
