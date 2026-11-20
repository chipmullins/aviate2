<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    /** The author can delete a post, and so can the person whose profile it was written on. */
    public function delete(User $user, Post $post): bool
    {
        return $user->id === $post->user_id || $user->id === $post->wall_user_id;
    }
}
