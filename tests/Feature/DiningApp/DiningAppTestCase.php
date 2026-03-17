<?php

namespace Tests\Feature\DiningApp;

use App\Models\CategoryDetail;
use App\Models\ItemDetail;
use App\Models\MenuDetail;
use App\Models\Role;
use App\Models\RoomDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Shared base for all dining-app feature tests.
 *
 * Provides token generation (matching APIToken middleware's decoding logic),
 * model factories, and the settings seed that DiningAppService requires.
 */
abstract class DiningAppTestCase extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // Token helpers  (must match generateAccessToken() in DiningAppService)
    // -----------------------------------------------------------------------

    protected function makeResidentToken(RoomDetail $room): string
    {
        return 'Bearer ' . base64_encode(base64_encode(json_encode([
            'user_id'   => $room->id,
            'timestamp' => time(),
            'role'      => 'user',
        ])));
    }

    protected function makeAdminToken(User $user, string $role = 'admin'): string
    {
        return 'Bearer ' . base64_encode(base64_encode(json_encode([
            'user_id'   => $user->id,
            'timestamp' => time(),
            'role'      => $role,
        ])));
    }

    /** Returns the Authorization header array expected by withHeaders(). */
    protected function residentHeaders(RoomDetail $room): array
    {
        return ['Authorization' => $this->makeResidentToken($room)];
    }

    protected function adminHeaders(User $user, string $role = 'admin'): array
    {
        return ['Authorization' => $this->makeAdminToken($user, $role)];
    }

    // -----------------------------------------------------------------------
    // Model factories
    // -----------------------------------------------------------------------

    protected function makeRoom(array $attrs = []): RoomDetail
    {
        return RoomDetail::create(array_merge([
            'room_name'     => '101',
            'password'      => 'secret',
            'is_active'     => 1,
            'occupancy'     => 2,
            'resident_name' => 'Jane Doe',
            'language'      => 0,
        ], $attrs));
    }

    protected function makeAdminUser(array $attrs = []): User
    {
        $role = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'api']
        );

        return User::create(array_merge([
            'name'      => 'Admin User',
            'user_name' => 'admin',
            'email'     => 'admin@example.com',
            'password'  => Hash::make('password'),
            'role_id'   => $role->id,
            'role'      => 'admin',
        ], $attrs));
    }

    /**
     * Create (or retrieve) a Role named 'user' and return a room whose role_id
     * points to it. getUserData() resolves the resident path via role name lookup,
     * so the DB role must exist and match the room's role_id.
     */
    protected function makeResidentRoom(array $attrs = []): RoomDetail
    {
        $role = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
        return $this->makeRoom(array_merge(['role_id' => $role->id], $attrs));
    }

    /**
     * Create a top-level category (parent_id = 0).
     * type: 1 = breakfast, 2 = lunch, 3 = dinner.
     */
    protected function makeCategory(array $attrs = []): CategoryDetail
    {
        return CategoryDetail::create(array_merge([
            'cat_name'               => 'Test Category',
            'category_chinese_name'  => '测试',
            'type'                   => 1,
            'parent_id'              => 0,
        ], $attrs));
    }

    protected function makeItem(CategoryDetail $category, array $attrs = []): ItemDetail
    {
        return ItemDetail::create(array_merge([
            'cat_id'            => $category->id,
            'item_name'         => 'Test Item',
            'item_chinese_name' => '测试项目',
            'is_allday'         => 0,
        ], $attrs));
    }

    /**
     * Create a menu_detail row for a given date containing the supplied item IDs.
     * Items is stored as a flat JSON array: [id1, id2, ...].
     */
    protected function makeMenu(string $date, array $itemIds): MenuDetail
    {
        return MenuDetail::create([
            'date'  => $date,
            'items' => $itemIds,   // cast to array; Eloquent serialises to JSON on save
        ]);
    }

    // -----------------------------------------------------------------------
    // Settings seed
    // -----------------------------------------------------------------------

    /**
     * Seed the settings rows that DiningAppService reads unconditionally.
     * Without these the service throws an undefined-index error.
     */
    protected function seedSettings(array $overrides = []): void
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
}
