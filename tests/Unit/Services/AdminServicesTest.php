<?php

namespace Tests\Unit\Services;

use App\Models\CategoryDetail;
use App\Models\ItemDetail;
use App\Models\ItemOption;
use App\Models\ItemPreference;
use App\Models\MenuDetail;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoomDetail;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\Eloquent\CategoryDetailRepository;
use App\Repositories\Eloquent\ItemDetailRepository;
use App\Repositories\Eloquent\ItemOptionRepository;
use App\Repositories\Eloquent\ItemPreferenceRepository;
use App\Repositories\Eloquent\MenuDetailRepository;
use App\Repositories\Eloquent\PermissionRepository;
use App\Repositories\Eloquent\RoleRepository;
use App\Repositories\Eloquent\RoomDetailRepository;
use App\Repositories\Eloquent\SettingRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Services\Auth\AdminAuthService;
use App\Services\CategoryDetailService;
use App\Services\ItemDetailService;
use App\Services\ItemOptionService;
use App\Services\ItemPreferenceService;
use App\Services\MenuDetailService;
use App\Services\PermissionService;
use App\Services\RoleService;
use App\Services\RoomDetailService;
use App\Services\SettingService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminServicesTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_and_finds_category_with_service()
    {
        $service = new CategoryDetailService(
            new CategoryDetailRepository(new CategoryDetail())
        );

        $create = $service->store([
            'cat_name' => 'Breakfast',
            'category_chinese_name' => '早餐',
            'type' => '1',
            'parent_id' => 0,
        ]);

        $this->assertSame(201, $create['statusCode']);

        $categoryId = $create['payload']['data']->id;
        $found = $service->findCategoryById($categoryId);

        $this->assertSame(200, $found['statusCode']);
        $this->assertSame('Breakfast', $found['payload']['data']->cat_name);
    }

    /** @test */
    public function it_lists_items_with_item_detail_service()
    {
        $category = CategoryDetail::create([
            'cat_name' => 'Entree',
            'category_chinese_name' => '主菜',
            'type' => '2',
            'parent_id' => 0,
        ]);

        $service = new ItemDetailService(
            new ItemDetailRepository(new ItemDetail())
        );

        $service->store([
            'cat_id' => $category->id,
            'item_name' => 'Salad',
            'item_chinese_name' => '沙拉',
            'is_allday' => 0,
        ]);

        $results = $service->list(['cat_id' => $category->id]);

        $this->assertSame(200, $results['statusCode']);
        $this->assertCount(1, $results['payload']['data']);
    }

    /** @test */
    public function it_creates_item_option_with_service()
    {
        $service = new ItemOptionService(
            new ItemOptionRepository(new ItemOption())
        );

        $created = $service->store([
            'option_name' => 'Extra Sauce',
            'option_name_cn' => '额外酱',
            'is_paid_item' => 0,
        ]);

        $this->assertSame(201, $created['statusCode']);

        $optionId = $created['payload']['data']->id;
        $found = $service->findItemOptionById($optionId);

        $this->assertSame(200, $found['statusCode']);
        $this->assertSame('Extra Sauce', $found['payload']['data']->option_name);
    }

    /** @test */
    public function it_creates_item_preference_with_service()
    {
        $service = new ItemPreferenceService(
            new ItemPreferenceRepository(new ItemPreference())
        );

        $created = $service->store([
            'pname' => 'No Salt',
            'pname_cn' => '无盐',
        ]);

        $this->assertSame(201, $created['statusCode']);

        $results = $service->list([]);

        $this->assertSame(200, $results['statusCode']);
        $this->assertCount(1, $results['payload']['data']);
    }

    /** @test */
    public function it_creates_menu_with_service()
    {
        $service = new MenuDetailService(
            new MenuDetailRepository(new MenuDetail())
        );

        $created = $service->store([
            'date' => now()->toDateString(),
            'items' => ['1', '2'],
            'is_allday' => false,
        ]);

        $this->assertSame(201, $created['statusCode']);

        $menuId = $created['payload']['data']->id;
        $found = $service->findMenuById($menuId);

        $this->assertSame(200, $found['statusCode']);
        $this->assertSame($menuId, $found['payload']['data']->id);
    }

    /** @test */
    public function it_lists_permissions_with_service()
    {
        Permission::create([
            'name' => 'manage-rooms',
            'display_name' => 'Manage Rooms',
            'guard_name' => 'api',
        ]);

        $service = new PermissionService(
            new PermissionRepository(new Permission())
        );

        $results = $service->list([]);

        $this->assertSame(200, $results['statusCode']);
        $this->assertCount(1, $results['payload']['data']);
    }

    /** @test */
    public function it_creates_role_with_service()
    {
        $permission = Permission::create([
            'name' => 'manage-rooms',
            'display_name' => 'Manage Rooms',
            'guard_name' => 'api',
        ]);

        $service = new RoleService(
            new RoleRepository(new Role())
        );

        $created = $service->store([
            'name' => 'Admin',
            'permissions' => [$permission->name],
        ]);

        $this->assertSame(201, $created['statusCode']);
        $this->assertTrue($created['payload']['data']->hasPermissionTo($permission->name));
    }

    /** @test */
    public function it_creates_room_with_service()
    {
        $service = new RoomDetailService(
            new RoomDetailRepository(new RoomDetail())
        );

        $created = $service->store([
            'room_name' => '101',
            'is_active' => 1,
        ]);

        $this->assertSame(201, $created['statusCode']);

        $results = $service->list(['is_active' => 1]);

        $this->assertSame(200, $results['statusCode']);
        $this->assertCount(1, $results['payload']['data']);
    }

    /** @test */
    public function it_creates_setting_with_service()
    {
        $service = new SettingService(
            new SettingRepository(new Setting())
        );

        $created = $service->store([
            'key' => 'site.name',
            'display_name' => 'Site Name',
            'value' => 'Hamilton',
            'type' => 'string',
            'order' => 1,
            'group' => 'site',
        ]);

        $this->assertSame(201, $created['statusCode']);

        $results = $service->list(['group' => 'site']);

        $this->assertSame(200, $results['statusCode']);
        $this->assertCount(1, $results['payload']['data']);
    }

    /** @test */
    public function it_creates_user_with_service()
    {
        $role = Role::create([
            'name' => 'Admin',
            'guard_name' => 'api',
        ]);

        $service = new UserService(
            new UserRepository(new User()),
            new RoleRepository(new Role())
        );

        $created = $service->store([
            'name' => 'Admin User',
            'user_name' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'secret',
            'role_id' => $role->id,
            'role' => $role->name,
        ]);

        $this->assertSame(201, $created['statusCode']);

        $results = $service->list([]);

        $this->assertSame(200, $results['statusCode']);
        $this->assertCount(1, $results['payload']['data']);
    }

    /** @test */
    public function it_registers_admin_user_with_auth_service()
    {
        $service = new AdminAuthService(
            new UserRepository(new User()),
            new PermissionRepository(new Permission())
        );

        $created = $service->register([
            'name' => 'Auth Admin',
            'user_name' => 'auth-admin',
            'email' => 'auth-admin@example.com',
            'password' => 'secret',
        ]);

        $this->assertSame(201, $created['statusCode']);
        $this->assertSame('auth-admin@example.com', $created['payload']['user']->email);
    }
}
