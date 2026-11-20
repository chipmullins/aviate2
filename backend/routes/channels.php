<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Each user gets one private channel for their messages and notifications.
// Only that user can subscribe to it.
Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return $user->id === $id;
});
