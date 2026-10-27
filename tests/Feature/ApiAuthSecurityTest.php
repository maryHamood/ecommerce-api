<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_login_route_is_named_for_auth_middleware(): void
    {
        $this->assertNotNull(route('login'));
    }

    public function test_products_route_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }
}
