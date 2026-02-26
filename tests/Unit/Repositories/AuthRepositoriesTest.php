<?php

namespace Tests\Unit\Repositories;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Eloquent\PermissionRepository;
use App\Repositories\Eloquent\RoleRepository;
use App\Repositories\Eloquent\UserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRepositoriesTest extends TestCase
{
    use RefreshDatabase;

    private PermissionRepository $permissions;
    private RoleRepository $roles;
    private UserRepository $users;

    protected function setUp(): void
    {
        parent::setUp();
        $this->permissions = new PermissionRepository(new Permission());
        $this->roles = new RoleRepository(new Role());
        $this->users = new UserRepository(new User());
    }

    /** @test */
    public function it_lists_permission_names()
    {
        $this->permissions->create([
            'name' => 'manage-rooms',
            'display_name' => 'Manage Rooms',
            'guard_name' => 'api',
        ]);

        $names = $this->permissions->getAllNames();

        $this->assertContains('manage-rooms', $names);
    }

    /** @test */
    public function it_filters_roles_by_search()
    {
        $this->roles->create(['name' => 'Admin', 'guard_name' => 'api']);
        $this->roles->create(['name' => 'User', 'guard_name' => 'api']);

    $results = $this->roles->getAll(['search' => 'Admin']);

        $this->assertCount(1, $results);
        $this->assertSame('Admin', $results->first()->name);
    }

    /** @test */
    public function it_filters_users_by_username()
    {
        $this->users->create([
            'name' => 'Alice',
            'user_name' => 'alice',
            'email' => 'alice@example.com',
            'password' => bcrypt('secret'),
        ]);

        $results = $this->users->getAll(['user_name' => 'alice']);

        $this->assertCount(1, $results);
        $this->assertSame('alice@example.com', $results->first()->email);
    }

    /** @test */
    public function it_checks_name_conflict_with_deleted_roles()
    {
        $role = $this->roles->create(['name' => 'Temp', 'guard_name' => 'api']);
        $role->delete();

        $this->assertTrue($this->roles->nameConflictWithDeleted('Temp'));
    }
}
