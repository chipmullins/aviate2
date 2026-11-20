<?php

namespace App\Notifications;

class CommentedOnWallPost extends SocialNotification
{
    protected function kind(): string
    {
        return 'wall_post_commented';
    }

    protected function text(): string
    {
        return $this->actor->full_name.' commented on a post on your profile';
    }
}
