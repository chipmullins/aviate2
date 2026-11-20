<?php

namespace App\Notifications;

class PostLiked extends SocialNotification
{
    protected function kind(): string
    {
        return 'post_liked';
    }

    protected function text(): string
    {
        return $this->actor->full_name.' liked your post';
    }
}
