<?php

namespace App\Http\Resources\Api\V1;

use App\Models\PollVote;
use App\Models\Post;
use App\Models\ScoringSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Post
 */
class PostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author' => [
                'id' => $this->author?->id,
                'full_name' => $this->author->full_name ?? $this->author->name,
                'username' => $this->author?->username,
                'avatar_url' => $this->author?->profile?->avatar_url,
            ],
            'organization_id' => $this->organization_id,
            'body' => $this->body,
            'visibility' => $this->visibility->value,
            'shared_type' => $this->shared_type,
            'shared_id' => $this->shared_id,
            'shared' => $this->sharedSnapshot(),
            'like_count' => $this->like_count,
            'comment_count' => $this->comment_count,
            'is_liked' => (bool) ($this->is_liked ?? false),
            'is_pinned' => $this->is_pinned,
            'media' => $this->media->map(fn ($m) => [
                'id' => $m->id,
                'url' => $m->url,
                'type' => $m->type->value,
                'position' => $m->position,
            ]),
            'poll' => $this->pollSnapshot($request),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Lightweight snapshot of shared content (currently scoring sessions).
     * MVP resolves per-post; batch-load if feeds grow large.
     *
     * @return array<string, mixed>|null
     */
    private function sharedSnapshot(): ?array
    {
        if ($this->shared_type !== 'scoring_session' || $this->shared_id === null) {
            return null;
        }

        $session = ScoringSession::query()->find($this->shared_id);
        if ($session === null) {
            return null;
        }

        return [
            'type' => 'scoring_session',
            'total_score' => $session->total_score,
            'max_possible_score' => $session->max_possible_score,
            'bow_class' => $session->bow_class->value,
            'distance_category' => $session->distance_category->value,
            'is_personal_best' => $session->is_personal_best,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pollSnapshot(Request $request): ?array
    {
        $poll = $this->poll;
        if ($poll === null) {
            return null;
        }

        $userId = $request->user()?->id;
        $userVote = $userId ? PollVote::query()->where('poll_id', $poll->id)->where('user_id', $userId)->first() : null;
        $userVotedOptionId = $userVote?->poll_option_id;

        $options = $poll->options()->withCount('votes')->get()->map(function ($option) {
            return [
                'id' => $option->id,
                'option_text' => $option->option_text,
                'votes_count' => (int) $option->votes_count,
            ];
        });

        $totalVotes = $options->sum('votes_count');

        return [
            'id' => $poll->id,
            'question' => $poll->question,
            'expires_at' => $poll->expires_at?->toIso8601String(),
            'is_expired' => $poll->isExpired(),
            'options' => $options,
            'total_votes' => $totalVotes,
            'user_voted_option_id' => $userVotedOptionId,
        ];
    }
}
