<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Support\Enums\ArticleStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicArticleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_articles_index_page(): void
    {
        $category = Category::factory()->create();
        $article = Article::factory()->published()->create([
            'title' => 'Important AI News',
            'category_id' => $category->id,
        ]);

        // Artikel draft tidak boleh muncul
        $draftArticle = Article::factory()->create([
            'title' => 'Secret Draft News',
        ]);

        $this->get(route('public.articles.index'))
            ->assertOk()
            ->assertSee('Important AI News')
            ->assertDontSee('Secret Draft News');
    }

    public function test_guest_can_view_article_detail_page(): void
    {
        $article = Article::factory()->published()->create([
            'title' => 'AI Breakthrough',
            'content' => '<p>Fascinating breakthrough details.</p>',
        ]);

        $this->get(route('public.articles.show', $article->slug))
            ->assertOk()
            ->assertSee('AI Breakthrough')
            ->assertSee('Fascinating breakthrough details.');
    }

    public function test_guest_cannot_view_draft_article_detail_page(): void
    {
        $article = Article::factory()->create([
            'title' => 'Secret Draft News',
            'status' => ArticleStatus::Draft,
        ]);

        $this->get(route('public.articles.show', $article->slug))
            ->assertNotFound();
    }
}
