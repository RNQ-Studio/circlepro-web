<?php

namespace Tests\Feature\BackOffice;

use App\Filament\Resources\TargetFaces\TargetFaceResource;
use App\Models\TargetFace;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TargetFaceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_open_target_face_management_pages(): void
    {
        $admin = $this->userWithRole('admin');
        $targetFace = TargetFace::create([
            'code' => 'test_face_target',
            'name' => 'Test Face Target',
            'image_path' => 'assets/images/targets/target_fita_10_ring.png',
            'scoring_rules' => [
                ['value' => 10, 'label' => '10', 'color' => '#FFC107'],
            ],
            'used_count' => 0,
        ]);

        $this->actingAs($admin)
            ->get(TargetFaceResource::getUrl('index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(TargetFaceResource::getUrl('create'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(TargetFaceResource::getUrl('edit', ['record' => $targetFace]))
            ->assertOk();
    }

    public function test_user_without_target_face_permission_cannot_access_target_face_management(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(TargetFaceResource::getUrl('index'))
            ->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
