<?php

namespace Tests\Feature;

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(RestrictedDocsAccess::class);
    }

    public function test_api_documentation_ui_is_available(): void
    {
        $this->get('/docs/api')
            ->assertOk()
            ->assertSee('Laravel Starter API');
    }

    public function test_openapi_json_documents_api_v1_with_bearer_auth(): void
    {
        $this->getJson('/docs/api.json')
            ->assertOk()
            ->assertJsonPath('openapi', '3.1.0')
            ->assertJsonPath('info.title', 'Laravel Starter API')
            ->assertJsonPath('servers.0.url', 'http://localhost/api/v1')
            ->assertJsonPath('components.securitySchemes.http.type', 'http')
            ->assertJsonPath('components.securitySchemes.http.scheme', 'bearer')
            ->assertJsonPath('paths./health.get.security', [])
            ->assertJsonStructure([
                'paths' => [
                    '/health',
                    '/auth/login',
                    '/auth/me',
                    '/categories',
                ],
                'components' => [
                    'schemas' => [
                        'LoginRequest',
                        'StoreCategoryRequest',
                    ],
                ],
            ]);
    }
}
