<?php

namespace Tests\Unit\Admin;

use Tests\TestCase;
use App\Http\Controllers\Api\Admin\AuthController;
use App\Models\User;

class AuthControllerHelpersTest extends TestCase
{
    protected $controller;
    protected $reflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new AuthController();
        $this->reflection = new \ReflectionClass($this->controller);
    }

    /** @test */
    public function respond_with_token_returns_expected_structure()
    {
        // Mock an authenticated user for the controller using the User model
        $user = new User([
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        auth()->setUser($user);

    $token = 'testtoken';
    $permissions = ['perm1' => 1, 'perm2' => 0];

    $method = $this->reflection->getMethod('respondWithToken');
    $method->setAccessible(true);
    $response = $method->invoke($this->controller, $token, $permissions);
    $data = $response->getData(true);

    $this->assertEquals('testtoken', $data['access_token']);
    $this->assertEquals('bearer', $data['token_type']);
    $this->assertArrayHasKey('expires_in', $data);
    $this->assertArrayHasKey('user', $data);
    $this->assertEquals($permissions, $data['permissions']);
    $this->assertEquals('1', $data['ResponseCode']);
    $this->assertEquals('success', $data['ResponseText']);
    }

    /** @test */
    public function error_response_returns_expected_structure()
    {
        $data = ['error' => 'fail'];

        $method = $this->reflection->getMethod('errorResponse');
        $method->setAccessible(true);
        $response = $method->invoke($this->controller, $data, 400);
        $json = $response->getData(true);

        $this->assertEquals('fail', $json['error']);
        $this->assertEquals('11', $json['ResponseCode']);
        $this->assertEquals('Error', $json['ResponseText']);
    }
}
