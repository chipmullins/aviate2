<?php

namespace App\Notifications;

class WallPostCreated extends SocialNotification
{
    protected function kind(): string
    {
        return 'wall_post_created';
    }

    protected function text(): string
    {
        return $this->actor->full_name.' posted on your profile';
    }
}
