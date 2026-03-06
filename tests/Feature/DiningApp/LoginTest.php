<?php

namespace Tests\Feature\DiningApp;

use App\Models\Role;
use App\Models\RoomDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for POST /api/login — the dining-app resident/admin login endpoint.
 *
 * Authentication paths:
 *   1. Resident  — matches room_details.room_name + plain-text password
 *   2. Admin/kitchen — falls through to users.user_name + Hash::check()
 *
 * All responses use HTTP 200; success/failure is indicated by ResponseCode:
 *   '1' = success, '2' = wrong credentials / not found, '3' = account inactive
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // Seed helpers
    // -----------------------------------------------------------------------

    /**
     * Insert the settings keys that DiningAppService::login() reads unconditionally.
     * Without these the service throws an undefined-index error.
     */
    private function seedSettings(array $overrides = []): void
    {
        $defaults = [
            'site.app_breakfast_msg'    => 'Enjoy your breakfast.',
            'site.app_breakfast_msg_cn' => '',
            'site.app_lunch_msg'        => 'Enjoy your lunch.',
            'site.app_lunch_msg_cn'     => '',
            'site.app_dinner_msg'       => 'Enjoy your dinner.',
            'site.app_dinner_msg_cn'    => '',
            'show_incident'             => '1',
            'show_dining'               => '1',
        ];

        foreach (array_merge($defaults, $overrides) as $key => $value) {
            DB::table('settings')->insert([
                'key'          => $key,
                'display_name' => $key,
                'value'        => $value,
                'type'         => 'text',
                'order'        => 1,
            ]);
        }
    }

    private function makeRoom(array $attrs = []): RoomDetail
    {
        return RoomDetail::create(array_merge([
            'room_name'      => '101',
            'password'       => 'secret',
            'is_active'      => 1,
            'occupancy'      => 2,
            'resident_name'  => 'Jane Doe',
            'language'       => 0,
        ], $attrs));
    }

    /**
     * Create a role + admin user and return the user.
     * role_id=1 → 'admin' role; any other id → 'kitchen'.
     */
    private function makeAdminUser(array $userAttrs = []): User
    {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'api']);

        return User::create(array_merge([
            'name'      => 'Admin User',
            'user_name' => 'admin',
            'email'     => 'admin@example.com',
            'password'  => Hash::make('23100'),
            'role_id'   => $role->id,
            'role'      => 'admin',
        ], $userAttrs));
    }

    // -----------------------------------------------------------------------
    // Resident login
    // -----------------------------------------------------------------------

    #[Test]
    public function resident_login_succeeds_with_valid_credentials(): void
    {
        $this->seedSettings();
        $room = $this->makeRoom(['room_name' => '101', 'password' => 'secret']);

        $response = $this->postJson('/api/login', [
            'room_no'  => '101',
            'password' => 'secret',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'ResponseCode' => '1',
                'ResponseText' => 'Successfully Login',
                'role'         => 'user',
                'room_id'      => $room->id,
                'room_number'  => '101',
                'occupancy'    => 2,
            ]);

        // Token must be present and follow the "Bearer <base64>" format.
        $data = $response->json();
        $this->assertStringStartsWith('Bearer ', $data['authentication_token']);

        // Guideline fields should be populated from seeded settings.
        $this->assertSame('Enjoy your breakfast.', $data['breakfast_guideline']);
        $this->assertSame('Enjoy your lunch.', $data['lunch_guideline']);
        $this->assertSame('Enjoy your dinner.', $data['dinner_guideline']);

        // Rooms list must include the logged-in room.
        $this->assertIsArray($data['rooms']);
        $roomIds = array_column($data['rooms'], 'id');
        $this->assertContains($room->id, $roomIds);
    }

    #[Test]
    public function resident_login_returns_chinese_guideline_when_set(): void
    {
        $this->seedSettings([
            'site.app_breakfast_msg'    => 'Enjoy breakfast.',
            'site.app_breakfast_msg_cn' => '早餐愉快。',
        ]);
        $this->makeRoom(['room_name' => '101', 'password' => 'secret']);

        $data = $this->postJson('/api/login', [
            'room_no'  => '101',
            'password' => 'secret',
        ])->assertStatus(200)->json();

        // When a CN variant is set, it should be returned rather than the English fallback.
        $this->assertSame('早餐愉快。', $data['breakfast_guideline_cn']);
    }

    #[Test]
    public function resident_login_falls_back_to_english_guideline_when_cn_is_empty(): void
    {
        $this->seedSettings([
            'site.app_breakfast_msg'    => 'Enjoy breakfast.',
            'site.app_breakfast_msg_cn' => '',
        ]);
        $this->makeRoom(['room_name' => '101', 'password' => 'secret']);

        $data = $this->postJson('/api/login', [
            'room_no'  => '101',
            'password' => 'secret',
        ])->assertStatus(200)->json();

        // Empty CN → fall back to English value.
        $this->assertSame('Enjoy breakfast.', $data['breakfast_guideline_cn']);
    }

    #[Test]
    public function resident_login_fails_when_password_is_wrong(): void
    {
        $this->seedSettings();
        $this->makeRoom(['room_name' => '101', 'password' => 'correct']);

        $response = $this->postJson('/api/login', [
            'room_no'  => '101',
            'password' => 'wrong',
        ]);

        $response->assertStatus(200)->assertJson([
            'ResponseCode' => '2',
            'ResponseText' => 'Room Number or Password is incorrect',
        ]);
    }

    #[Test]
    public function resident_login_fails_when_room_is_inactive(): void
    {
        $this->seedSettings();
        $this->makeRoom(['room_name' => '101', 'password' => 'secret', 'is_active' => 0]);

        $response = $this->postJson('/api/login', [
            'room_no'  => '101',
            'password' => 'secret',
        ]);

        $response->assertStatus(200)->assertJson([
            'ResponseCode' => '3',
            'ResponseText' => 'User not active',
        ]);
    }

    #[Test]
    public function resident_login_includes_last_menu_date_when_menu_exists(): void
    {
        $this->seedSettings();
        $this->makeRoom(['room_name' => '101', 'password' => 'secret']);

        // Insert a menu_detail row directly so we control the exact date.
        DB::table('menu_details')->insert([
            'date'  => '2026-03-01',
            'items' => '[]',
        ]);

        $data = $this->postJson('/api/login', [
            'room_no'  => '101',
            'password' => 'secret',
        ])->assertStatus(200)->json();

        $this->assertSame('2026-03-01', $data['last_menu_date']);
    }

    #[Test]
    public function resident_login_has_empty_last_menu_date_when_no_menus_exist(): void
    {
        $this->seedSettings();
        $this->makeRoom(['room_name' => '101', 'password' => 'secret']);

        $data = $this->postJson('/api/login', [
            'room_no'  => '101',
            'password' => 'secret',
        ])->assertStatus(200)->json();

        $this->assertSame('', $data['last_menu_date']);
    }

    // -----------------------------------------------------------------------
    // Admin / kitchen login
    // -----------------------------------------------------------------------

    #[Test]
    public function admin_login_succeeds_with_valid_credentials(): void
    {
        $this->seedSettings();
        $user = $this->makeAdminUser(['user_name' => 'admin', 'password' => Hash::make('23100')]);

        $response = $this->postJson('/api/login', [
            'room_no'  => 'admin',
            'password' => '23100',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'ResponseCode' => '1',
                'ResponseText' => 'Successfully Login',
                'user_id'      => $user->id,
                'room_id'      => 0,
            ]);

        $data = $response->json();
        // role is resolved from role_id: 1 → 'admin'
        $this->assertSame('admin', $data['role']);
        $this->assertStringStartsWith('Bearer ', $data['authentication_token']);
        // Admin response includes these extra fields
        $this->assertArrayHasKey('form_types', $data);
        $this->assertArrayHasKey('user_list', $data);
        $this->assertArrayHasKey('show_incident', $data);
        $this->assertArrayHasKey('show_dining', $data);
    }

    #[Test]
    public function kitchen_login_gets_kitchen_role(): void
    {
        $this->seedSettings();

        $kitchenRole = Role::create(['name' => 'kitchen', 'guard_name' => 'api']);
        User::create([
            'name'      => 'Kitchen Staff',
            'user_name' => 'kitchen1',
            'email'     => 'kitchen@example.com',
            'password'  => Hash::make('pass123'),
            'role_id'   => $kitchenRole->id, // not 1, so maps to 'kitchen'
            'role'      => 'kitchen',
        ]);

        $data = $this->postJson('/api/login', [
            'room_no'  => 'kitchen1',
            'password' => 'pass123',
        ])->assertStatus(200)->json();

        $this->assertSame('1', $data['ResponseCode']);
        $this->assertSame('kitchen', $data['role']);
    }

    #[Test]
    public function admin_login_fails_when_password_is_wrong(): void
    {
        $this->seedSettings();
        $this->makeAdminUser(['user_name' => 'admin']);

        $response = $this->postJson('/api/login', [
            'room_no'  => 'admin',
            'password' => 'wrong',
        ]);

        $response->assertStatus(200)->assertJson([
            'ResponseCode' => '2',
            'ResponseText' => 'User not Found',
        ]);
    }

    // -----------------------------------------------------------------------
    // Not-found cases
    // -----------------------------------------------------------------------

    #[Test]
    public function login_returns_not_found_when_credentials_match_nothing(): void
    {
        $this->seedSettings();

        // No rooms, no users seeded.
        $response = $this->postJson('/api/login', [
            'room_no'  => 'ghost',
            'password' => 'irrelevant',
        ]);

        $response->assertStatus(200)->assertJson([
            'ResponseCode' => '2',
            'ResponseText' => 'User not Found',
        ]);
    }

    // -----------------------------------------------------------------------
    // Validation
    // -----------------------------------------------------------------------

    #[Test]
    public function login_fails_validation_when_room_no_is_missing(): void
    {
        $response = $this->postJson('/api/login', ['password' => 'secret']);

        $response->assertStatus(200)->assertJson(['ResponseCode' => '2']);
    }

    #[Test]
    public function login_fails_validation_when_password_is_missing(): void
    {
        $response = $this->postJson('/api/login', ['room_no' => '101']);

        $response->assertStatus(200)->assertJson(['ResponseCode' => '2']);
    }
}
