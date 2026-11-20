<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** A profile page, with stats and how the viewer relates to this person. */
class ProfileResource extends UserResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();

        return parent::toArray($request) + [
            'joined_at' => $this->created_at?->toIso8601String(),
            'posts_count' => $this->posts()->count(),
            'friends_count' => $this->friends()->whereNull('closed_at')->count(),
            'likes_received' => \App\Models\Like::whereHas('post', fn ($q) => $q->where('user_id', $this->id))->count(),
            'mutual_friends_count' => $viewer->mutualFriendsCount($this->resource),
            'friendship_status' => $viewer->friendshipStatusWith($this->resource),
        ];
    }
}
