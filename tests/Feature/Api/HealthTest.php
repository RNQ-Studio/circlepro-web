<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_returns_standard_success_envelope(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Service is healthy',
                'data' => ['status' => 'ok'],
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['status', 'app', 'time'],
            ]);
    }

    public function test_unknown_api_route_returns_standard_error_envelope(): void
    {
        $response = $this->getJson('/api/v1/does-not-exist');

        $response
            ->assertNotFound()
            ->assertJson([
                'success' => false,
                'code' => 'NOT_FOUND',
            ]);
    }
}
