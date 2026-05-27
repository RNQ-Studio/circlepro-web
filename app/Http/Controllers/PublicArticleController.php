<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Support\Enums\ArticleStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicArticleController extends Controller
{
    public function index(Request $request): View
    {
        $articles = Article::query()
            ->with(['category', 'author', 'tags'])
            ->where('status', ArticleStatus::Published)
            ->latest('published_at')
            ->paginate(9);

        return view('articles.index', compact('articles'));
    }

    public function show(string $slug): View
    {
        $article = Article::query()
            ->with(['category', 'author', 'tags'])
            ->where('status', ArticleStatus::Published)
            ->where('slug', $slug)
            ->firstOrFail();

        // Cari artikel terkait di kategori yang sama (opsional untuk Medium-style feel!)
        $relatedArticles = Article::query()
            ->with(['category', 'author'])
            ->where('status', ArticleStatus::Published)
            ->where('id', '!=', $article->id)
            ->where('category_id', $article->category_id)
            ->latest('published_at')
            ->limit(3)
            ->get();

        return view('articles.show', compact('article', 'relatedArticles'));
    }
}
