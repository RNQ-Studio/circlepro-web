<?php

namespace Tests\Feature;

use App\Models\ScoringSessionGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 09 — Distribusi Link-First, Deep Link & QR (Phase 1, backend support).
 *
 * Covers the public invite landing page (rich preview + deferred install path,
 * tasks 9.1/9.2/9.4) and the App Links / Universal Links association files that
 * let the OS open the app directly from an HTTPS share link.
 */
class GroupScoringInviteTest extends TestCase
{
    use RefreshDatabase;

    private function makeGroup(array $overrides = []): ScoringSessionGroup
    {
        return ScoringSessionGroup::factory()->create(array_merge([
            'host_user_id' => User::factory()->create(['name' => 'Coach Hadi']),
            'title' => 'Latihan Sore Klub',
            'distance_m' => 50,
            'num_ends' => 6,
            'arrows_per_end' => 6,
            'join_code' => 'ABC234',
        ], $overrides));
    }

    public function test_invite_landing_shows_rich_session_preview(): void
    {
        $this->makeGroup();

        $this->get('/j/ABC234')
            ->assertOk()
            ->assertSee('Latihan Sore Klub')
            ->assertSee('Coach Hadi')
            ->assertSee('ABC234')
            ->assertSee('50 m')
            ->assertSee('Buka di Aplikasi ManahPro')
            ->assertSee(config('app.url').'/j/ABC234');
    }

    public function test_invite_landing_accepts_lowercase_code(): void
    {
        $this->makeGroup();

        $this->get('/j/abc234')
            ->assertOk()
            ->assertSee('ABC234');
    }

    public function test_invite_landing_via_query_string(): void
    {
        $this->makeGroup();

        $this->get('/group-scoring/join?code=ABC234')
            ->assertOk()
            ->assertSee('Latihan Sore Klub');
    }

    public function test_invite_landing_handles_unknown_code_gracefully(): void
    {
        $this->get('/j/NOPE99')
            ->assertOk()
            ->assertSee('Sesi tidak ditemukan')
            ->assertSee('Dapatkan ManahPro');
    }

    public function test_assetlinks_returns_android_statements(): void
    {
        $this->get('/.well-known/assetlinks.json')
            ->assertOk()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonStructure([
                ['relation', 'target' => ['namespace', 'package_name', 'sha256_cert_fingerprints']],
            ])
            ->assertJsonPath('0.target.namespace', 'android_app')
            ->assertJsonPath('0.target.package_name', config('deeplink.android.0.package_name'));
    }

    public function test_apple_app_site_association_returns_applinks(): void
    {
        $this->get('/.well-known/apple-app-site-association')
            ->assertOk()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonStructure([
                'applinks' => ['apps', 'details' => [['appIDs', 'components']]],
            ]);

        // Legacy root path must resolve too.
        $this->get('/apple-app-site-association')->assertOk();
    }
}
