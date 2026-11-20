<?php

namespace App\Notifications;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Base class for Aviate's in-app notifications. Each one is saved to the
 * database (for the notifications page) and pushed over WebSockets
 * (so the bell updates instantly).
 */
abstract class SocialNotification extends Notification
{
    public function __construct(public User $actor, public ?int $postId = null)
    {
    }

    /** Machine name the front end uses to pick an icon and a link. */
    abstract protected function kind(): string;

    /** Plain-text sentence, e.g. "Jane Pilot liked your post". The front end escapes it. */
    abstract protected function text(): string;

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind(),
            'text' => $this->text(),
            'actor' => (new UserResource($this->actor))->resolve(),
            'post_id' => $this->postId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            ...$this->toArray($notifiable),
            'read_at' => null,
            'created_at' => now()->toIso8601String(),
        ]);
    }

    public function broadcastType(): string
    {
        return 'notification.created';
    }
}
