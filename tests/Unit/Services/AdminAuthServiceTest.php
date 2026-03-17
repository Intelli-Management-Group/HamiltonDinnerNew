<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Auth\AdminAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AdminAuthServiceTest extends TestCase
{
    // RefreshDatabase is required because auth()->attempt() queries the users
    // table, which only exists once migrations have run.
    use RefreshDatabase;

    #[Test]
    public function it_returns_error_payload_for_invalid_login()
    {
        // Repos are not reached when auth()->attempt() fails; they only need to exist.
        $service = new AdminAuthService(
            Mockery::mock(UserRepositoryInterface::class),
            Mockery::mock(PermissionRepositoryInterface::class),
        );

        $result = $service->login([
            'email'    => 'missing@example.com',
            'password' => 'secret',
        ]);

        $this->assertSame(201, $result['statusCode']);
        $this->assertSame('Email or Password is incorrect', $result['payload']['error']);
    }

    #[Test]
    public function it_registers_admin_user()
    {
        $fakeUser = new User();
        $fakeUser->email = 'auth-admin@example.com';

        $mockUsers = Mockery::mock(UserRepositoryInterface::class);
        $mockUsers->shouldReceive('create')->once()->andReturn($fakeUser);

        $service = new AdminAuthService(
            $mockUsers,
            Mockery::mock(PermissionRepositoryInterface::class),
        );

        $created = $service->register([
            'name'      => 'Auth Admin',
            'user_name' => 'auth-admin',
            'email'     => 'auth-admin@example.com',
            'password'  => 'secret',
        ]);

        $this->assertSame(201, $created['statusCode']);
        $this->assertSame('auth-admin@example.com', $created['payload']['user']->email);
    }
}
