<?php

namespace Tests\Unit\Models;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthModelsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function role_search_scope_filters_by_name()
    {
        Role::create(['name' => 'Admin', 'guard_name' => 'api']);
        Role::create(['name' => 'User', 'guard_name' => 'api']);

        $results = Role::search('Admin')->get();

        $this->assertCount(1, $results);
        $this->assertSame('Admin', $results->first()->name);
    }

    /** @test */
    public function role_relations_are_configured()
    {
        $role = new Role();

        $this->assertInstanceOf(BelongsToMany::class, $role->permissionList());
    }

    /** @test */
    public function permission_search_scope_filters_by_name_and_display_name()
    {
        Permission::create([
            'name' => 'manage-rooms',
            'display_name' => 'Manage Rooms',
            'guard_name' => 'api',
        ]);

        Permission::create([
            'name' => 'view-reports',
            'display_name' => 'View Reports',
            'guard_name' => 'api',
        ]);

        $results = Permission::search('Rooms')->get();

        $this->assertCount(1, $results);
        $this->assertSame('manage-rooms', $results->first()->name);
    }

    /** @test */
    public function permission_relations_are_configured()
    {
        $permission = new Permission();

        $this->assertInstanceOf(BelongsToMany::class, $permission->rolesList());
    }

    /** @test */
    public function user_returns_default_avatar_when_empty()
    {
        $user = new User();

        $this->assertStringContainsString('/images/user.webp', $user->avatar);
    }

    /** @test */
    public function user_relations_are_configured()
    {
        $user = new User();

        $this->assertInstanceOf(HasManyThrough::class, $user->permissionList());
        $this->assertInstanceOf(HasOne::class, $user->role());
    }

    /** @test */
    public function user_search_scope_filters_by_name_email_or_username()
    {
        User::create([
            'name' => 'Alice',
            'user_name' => 'alice',
            'email' => 'alice@example.com',
            'password' => bcrypt('password'),
        ]);

        User::create([
            'name' => 'Bob',
            'user_name' => 'bobby',
            'email' => 'bob@example.com',
            'password' => bcrypt('password'),
        ]);

        $results = User::search('alice')->get();

        $this->assertCount(1, $results);
        $this->assertSame('Alice', $results->first()->name);
    }
}
