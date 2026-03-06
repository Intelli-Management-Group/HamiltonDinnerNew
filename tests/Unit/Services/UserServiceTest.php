<?php

namespace Tests\Unit\Services;

use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class UserServiceTest extends TestCase
{
    // RefreshDatabase is required for the password-hashing test, which calls
    // Eloquent model methods (assignRole, fresh) that must run against the DB.
    use RefreshDatabase;

    #[Test]
    public function it_returns_not_found_for_missing_user()
    {
        $mockUsers = Mockery::mock(UserRepositoryInterface::class);
        $mockUsers->shouldReceive('findById')->with(999)->andReturn(null);

        $result = (new UserService($mockUsers, Mockery::mock(RoleRepositoryInterface::class)))
            ->findUserById(999);

        $this->assertSame(404, $result['statusCode']);
        $this->assertFalse($result['payload']['success']);
    }

    #[Test]
    public function it_returns_paginated_users_when_requested()
    {
        $paginator = new LengthAwarePaginator(
            items: collect([new User(), new User()])->take(1),
            total: 2,
            perPage: 1,
            currentPage: 1,
        );

        $mockUsers = Mockery::mock(UserRepositoryInterface::class);
        $mockUsers->shouldReceive('paginate')->andReturn($paginator);

        $result = (new UserService($mockUsers, Mockery::mock(RoleRepositoryInterface::class)))
            ->list(['pagesize' => 1, 'pagenumber' => 1]);

        $this->assertSame(200, $result['statusCode']);
        $this->assertCount(1, $result['payload']['data']);
        $this->assertSame(2, $result['payload']['pagination']['total']);
    }

    #[Test]
    public function it_hashes_password_when_updating_user()
    {
        $role = Role::create(['name' => 'Admin', 'guard_name' => 'api']);
        $user = User::create([
            'name'      => 'Admin User',
            'user_name' => 'admin',
            'email'     => 'admin@example.com',
            'password'  => bcrypt('secret'),
            'role_id'   => $role->id,
        ]);

        $mockUsers = Mockery::mock(UserRepositoryInterface::class);
        // Delegate to model's own save() so fresh() can read the hashed password.
        $mockUsers->shouldReceive('save')->once()->andReturnUsing(fn($u) => tap($u, fn($u) => $u->save()));

        $mockRoles = Mockery::mock(RoleRepositoryInterface::class);
        $mockRoles->shouldReceive('findById')->with($role->id)->andReturn($role);

        $result = (new UserService($mockUsers, $mockRoles))->update($user, [
            'password' => 'new-secret',
            'role_id'  => $role->id,
        ]);

        $this->assertSame(200, $result['statusCode']);
        $this->assertTrue(Hash::check('new-secret', $result['payload']['data']->password));
    }
}
