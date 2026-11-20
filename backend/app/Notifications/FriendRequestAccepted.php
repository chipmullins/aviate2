<?php

namespace App\Notifications;

class FriendRequestAccepted extends SocialNotification
{
    protected function kind(): string
    {
        return 'friend_request_accepted';
    }

    protected function text(): string
    {
        return $this->actor->full_name.' accepted your friend request';
    }
}
