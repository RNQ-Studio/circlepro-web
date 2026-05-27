<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Article;
use App\Support\Enums\ArticleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $article = $this->route('article');

        return $article instanceof Article
            && ($this->user()?->can('update', $article) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('title') && ! $this->filled('slug')) {
            $this->merge([
                'slug' => Str::slug($this->string('title')->toString()),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Article $article */
        $article = $this->route('article');

        return [
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'author_id' => ['sometimes', 'integer', Rule::exists('users', 'id')],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('articles', 'slug')->ignore($article->getKey()),
            ],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['sometimes', 'required', 'string'],
            'featured_image' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(ArticleStatus::class)],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['integer', Rule::exists('tags', 'id')],
        ];
    }
}
