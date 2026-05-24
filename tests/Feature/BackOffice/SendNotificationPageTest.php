<?php

namespace Tests\Feature\BackOffice;

use App\Filament\Pages\SendNotificationPage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SendNotificationPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_access_send_notification_page(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(SendNotificationPage::getUrl())
            ->assertOk();
    }

    public function test_unauthorized_user_cannot_access_send_notification_page(): void
    {
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)
            ->get(SendNotificationPage::getUrl())
            ->assertForbidden();
    }

    public function test_admin_can_submit_notification_form_successfully(): void
    {
        $admin = $this->userWithRole('admin');
        User::factory()->count(3)->create(); // Create some targets

        Livewire::actingAs($admin)
            ->test(SendNotificationPage::class)
            ->set('title_input', 'Test Broadcast Title')
            ->set('body', 'Test Broadcast Body Message')
            ->set('type', 'system')
            ->set('sendToAll', true)
            ->call('send')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notifications', [
            'title' => 'Test Broadcast Title',
            'body' => 'Test Broadcast Body Message',
            'type' => 'system',
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
