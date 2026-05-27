<?php

namespace Tests\Feature\BackOffice;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Tags\TagResource;
use App\Models\Article;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_open_article_management_pages(): void
    {
        $admin = $this->userWithRole('admin');
        $article = Article::factory()->create();

        $this->actingAs($admin)
            ->get(ArticleResource::getUrl('index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(ArticleResource::getUrl('create'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(ArticleResource::getUrl('edit', ['record' => $article]))
            ->assertOk();
    }

    public function test_admin_can_open_tag_management_pages(): void
    {
        $admin = $this->userWithRole('admin');
        $tag = Tag::factory()->create();

        $this->actingAs($admin)
            ->get(TagResource::getUrl('index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(TagResource::getUrl('create'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(TagResource::getUrl('edit', ['record' => $tag]))
            ->assertOk();
    }

    public function test_user_without_article_permission_cannot_access_article_management(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(ArticleResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_user_without_tag_permission_cannot_access_tag_management(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(TagResource::getUrl('index'))
            ->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
