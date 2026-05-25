<?php

namespace Tests\Feature\BackOffice;

use App\Filament\Resources\Quotes\QuoteResource;
use App\Models\Quote;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_open_quote_management_pages(): void
    {
        $admin = $this->userWithRole('admin');
        $quote = Quote::factory()->create();

        $this->actingAs($admin)
            ->get(QuoteResource::getUrl('index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(QuoteResource::getUrl('create'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(QuoteResource::getUrl('edit', ['record' => $quote]))
            ->assertOk();
    }

    public function test_user_without_quote_permission_cannot_access_quote_management(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(QuoteResource::getUrl('index'))
            ->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
