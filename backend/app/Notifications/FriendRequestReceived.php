<?php

namespace App\Notifications;

class FriendRequestReceived extends SocialNotification
{
    protected function kind(): string
    {
        return 'friend_request_received';
    }

    protected function text(): string
    {
        return $this->actor->full_name.' sent you a friend request';
    }
}
