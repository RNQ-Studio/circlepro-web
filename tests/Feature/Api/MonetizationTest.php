<?php

namespace Tests\Feature\Api;

use App\Models\Ad;
use App\Models\AdCampaign;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\Enums\AdStatus;
use App\Support\Enums\PlanAudience;
use App\Support\Enums\PlanInterval;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class MonetizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->seed(RolePermissionSeeder::class);

        // Seed initial plans
        SubscriptionPlan::create([
            'code' => 'free',
            'name' => 'Free Tier',
            'audience' => PlanAudience::User->value,
            'price' => 0,
            'interval' => PlanInterval::Monthly->value,
            'features' => ['3 sessions/week'],
            'limits' => ['scoring_per_week' => 3],
        ]);

        SubscriptionPlan::create([
            'code' => 'pro',
            'name' => 'Pro Archer',
            'audience' => PlanAudience::User->value,
            'price' => 49000,
            'interval' => PlanInterval::Monthly->value,
            'features' => ['unlimited sessions'],
            'limits' => ['scoring_per_week' => -1],
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    public function test_get_plans(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->getJson('/api/v1/monetization/plans')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', 'free')
            ->assertJsonPath('data.1.code', 'pro');
    }

    public function test_get_default_free_subscription(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $this->getJson('/api/v1/monetization/subscription')
            ->assertOk()
            ->assertJsonPath('data.subscription', null)
            ->assertJsonPath('data.plan_details.code', 'free')
            ->assertJsonPath('data.usage.scoring_sessions_limit', 3)
            ->assertJsonPath('data.usage.scoring_sessions_this_week', 0)
            ->assertJsonPath('data.usage.is_gated', false);
    }

    public function test_subscribe_via_google_play(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $this->postJson('/api/v1/monetization/subscribe/google', [
            'plan_code' => 'pro',
            'purchase_token' => 'mock-token-123',
        ])
            ->assertOk()
            ->assertJsonPath('data.subscription.status', 'active')
            ->assertJsonPath('data.subscription.plan.code', 'pro');

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'status' => 'active',
            'provider' => 'google_play',
        ]);
    }

    public function test_gating_free_user_scoring_sessions(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        // Free user creates 3 sessions -> Allowed
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/scoring/sessions', [
                'title' => "Session {$i}",
                'bow_class' => 'recurve',
                'distance_category' => '30m',
                'distance_m' => 18,
                'num_ends' => 6,
                'arrows_per_end' => 3,
                'status' => 'completed',
                'started_at' => now()->toIso8601String(),
            ])->assertCreated();
        }

        // 4th session creation -> Gated (402 Payment Required)
        $this->postJson('/api/v1/scoring/sessions', [
            'title' => 'Gated Session',
            'bow_class' => 'recurve',
            'distance_category' => '30m',
            'distance_m' => 18,
            'num_ends' => 6,
            'arrows_per_end' => 3,
            'status' => 'completed',
            'started_at' => now()->toIso8601String(),
        ])->assertStatus(402);

        // Upgrade to Pro
        $this->postJson('/api/v1/monetization/subscribe/google', [
            'plan_code' => 'pro',
            'purchase_token' => 'pro-token',
        ])->assertOk();

        // 4th session should now be allowed
        $this->postJson('/api/v1/scoring/sessions', [
            'title' => 'Pro Session',
            'bow_class' => 'recurve',
            'distance_category' => '30m',
            'distance_m' => 18,
            'num_ends' => 6,
            'arrows_per_end' => 3,
            'status' => 'completed',
            'started_at' => now()->toIso8601String(),
        ])->assertCreated();
    }

    public function test_ads_served_only_to_free_tier(): void
    {
        // 1. Create an active ad campaign and ad
        $campaign = AdCampaign::create([
            'name' => 'Promo',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'status' => AdStatus::Active->value,
        ]);
        $ad = Ad::create([
            'ad_campaign_id' => $campaign->id,
            'placement' => 'feed',
            'title' => 'Ad Title',
            'click_url' => 'https://example.com',
        ]);

        $freeUser = User::factory()->create();
        Passport::actingAs($freeUser);

        // Free user gets ads
        $this->getJson('/api/v1/ads?placement=feed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Ad Title');

        // Premium user gets NO ads
        $proUser = User::factory()->create();
        Passport::actingAs($proUser);

        $this->postJson('/api/v1/monetization/subscribe/google', [
            'plan_code' => 'pro',
            'purchase_token' => 'pro-token',
        ])->assertOk();

        $this->getJson('/api/v1/ads?placement=feed')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_admin_revenue_dashboard(): void
    {
        $admin = $this->userWithRole('admin');
        $regularUser = User::factory()->create();

        // Regular user access -> Forbidden (403)
        Passport::actingAs($regularUser);
        $this->getJson('/api/v1/admin/revenue')->assertStatus(403);

        // Admin access -> Success
        Passport::actingAs($admin);
        $this->getJson('/api/v1/admin/revenue')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'mrr',
                    'active_subscribers',
                    'plans_breakdown',
                    'revenue'
                ]
            ]);
    }
}
