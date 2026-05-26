<?php

namespace Tests\Feature\DiningApp;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for POST /api/get-user-data.
 *
 * Refreshes user data using the session set by the APIToken middleware.
 * The resident path is triggered when the session user's role (looked up by role_id)
 * has name "user"; otherwise the admin/kitchen path runs.
 */
class GetUserDataTest extends DiningAppTestCase
{
    // -----------------------------------------------------------------------
    // Resident (room login)
    // -----------------------------------------------------------------------

    #[Test]
    public function resident_gets_their_own_data(): void
    {
        $this->seedSettings();
        // makeResidentRoom() creates a "user" Role and sets room.role_id accordingly,
        // which is required for getUserData() to enter the resident response path.
        $room = $this->makeResidentRoom(['room_name' => '205', 'occupancy' => 3, 'language' => 1]);

        $response = $this->withHeaders($this->residentHeaders($room))
            ->postJson('/api/get-user-data');

        $response->assertStatus(200)->assertJson([
            'ResponseCode' => '1',
            'ResponseText' => '',
            'role'         => 'user',
            'occupancy'    => 3,
            'language'     => 1,
        ]);

        $data = $response->json();
        $this->assertArrayHasKey('last_menu_date', $data);
        $this->assertArrayHasKey('breakfast_guideline', $data);
        $this->assertArrayHasKey('lunch_guideline', $data);
        $this->assertArrayHasKey('dinner_guideline', $data);
        // Resident should NOT see admin-only fields
        $this->assertArrayNotHasKey('user_id', $data);
        $this->assertArrayNotHasKey('form_types', $data);
        $this->assertArrayNotHasKey('user_list', $data);
    }

    #[Test]
    public function resident_response_includes_rooms_list(): void
    {
        $this->seedSettings();
        $room  = $this->makeResidentRoom(['room_name' => '101']);
        $room2 = $this->makeRoom(['room_name' => '102', 'is_active' => 1]);
        // Inactive room should be excluded from the rooms list
        $this->makeRoom(['room_name' => '103', 'is_active' => 0]);

        $data = $this->withHeaders($this->residentHeaders($room))
            ->postJson('/api/get-user-data')
            ->assertStatus(200)
            ->json();

        $this->assertIsArray($data['rooms']);
        $ids = array_column($data['rooms'], 'id');
        $this->assertContains($room->id, $ids);
        $this->assertContains($room2->id, $ids);
        // Inactive room must not appear
        $names = array_column($data['rooms'], 'name');
        $this->assertNotContains('103', $names);
    }

    #[Test]
    public function resident_sees_last_menu_date_when_menu_exists(): void
    {
        $this->seedSettings();
        $room = $this->makeResidentRoom();
        DB::table('menu_details')->insert(['date' => '2026-03-01', 'items' => '[]']);

        $data = $this->withHeaders($this->residentHeaders($room))
            ->postJson('/api/get-user-data')
            ->assertStatus(200)
            ->json();

        $this->assertSame('2026-03-01', $data['last_menu_date']);
    }

    #[Test]
    public function resident_last_menu_date_is_empty_when_no_menus(): void
    {
        $this->seedSettings();
        $room = $this->makeResidentRoom();

        $data = $this->withHeaders($this->residentHeaders($room))
            ->postJson('/api/get-user-data')
            ->assertStatus(200)
            ->json();

        $this->assertSame('', $data['last_menu_date']);
    }

    // -----------------------------------------------------------------------
    // Admin
    // -----------------------------------------------------------------------

    #[Test]
    public function admin_gets_extra_fields_in_response(): void
    {
        $this->seedSettings();
        $admin = $this->makeAdminUser();

        $data = $this->withHeaders($this->adminHeaders($admin, 'admin'))
            ->postJson('/api/get-user-data')
            ->assertStatus(200)
            ->json();

        $this->assertSame('1', $data['ResponseCode']);
        $this->assertSame($admin->id, $data['user_id']);
        $this->assertArrayHasKey('form_types', $data);
        $this->assertArrayHasKey('user_list', $data);
        $this->assertArrayHasKey('show_incident', $data);
        $this->assertArrayHasKey('show_dining', $data);
    }

    // -----------------------------------------------------------------------
    // Unauthenticated
    // -----------------------------------------------------------------------

    #[Test]
    public function unauthenticated_request_is_rejected_by_middleware(): void
    {
        // No Authorization header → APIToken middleware returns ResponseCode 11
        $this->postJson('/api/get-user-data')
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '11']);
    }
}
