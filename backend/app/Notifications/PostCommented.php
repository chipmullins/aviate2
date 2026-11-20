<?php

namespace App\Notifications;

class PostCommented extends SocialNotification
{
    protected function kind(): string
    {
        return 'post_commented';
    }

    protected function text(): string
    {
        return $this->actor->full_name.' commented on your post';
    }
}
