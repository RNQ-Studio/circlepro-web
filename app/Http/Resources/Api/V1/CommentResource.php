<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Comment
 */
class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'author' => [
                'id' => $this->author?->id,
                'full_name' => $this->author->full_name ?? $this->author->name,
                'username' => $this->author?->username,
                'avatar_url' => $this->author?->profile?->avatar_url,
            ],
            'body' => $this->body,
            'like_count' => $this->like_count,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
