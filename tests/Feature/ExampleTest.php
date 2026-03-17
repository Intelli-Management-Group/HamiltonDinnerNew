<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_api_login_endpoint_exists(): void
    {
        $response = $this->postJson('/api/login', []);

        // The app always returns HTTP 200; errors are signalled via ResponseCode in the body.
        // An empty request gets ResponseCode "2" (user not found) — confirms the route is reachable.
        $response->assertStatus(200)->assertJsonPath('ResponseCode', '2');
    }
}
