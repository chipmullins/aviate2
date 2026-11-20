<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_friends_can_message_and_it_is_broadcast_to_the_recipient_only(): void
    {
        Event::fake([MessageSent::class]);
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $this->makeFriends($me, $friend);

        $this->actingAs($me)->postJson("/api/conversations/{$friend->username}", ['body' => 'Dinner on the layover?'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Dinner on the layover?');

        Event::assertDispatched(MessageSent::class, function (MessageSent $e) use ($friend) {
            $channels = $e->broadcastOn();

            return count($channels) === 1
                && $channels[0] instanceof PrivateChannel
                && $channels[0]->name === 'private-App.Models.User.'.$friend->id;
        });
    }

    public function test_cannot_message_non_friends(): void
    {
        $me = User::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($me)->postJson("/api/conversations/{$stranger->username}", ['body' => 'hi'])->assertForbidden();
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_opening_a_thread_marks_it_read_and_lists_conversations(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $this->makeFriends($me, $friend);
        Message::create(['sender_id' => $friend->id, 'recipient_id' => $me->id, 'body' => 'one']);
        Message::create(['sender_id' => $friend->id, 'recipient_id' => $me->id, 'body' => 'two']);

        $this->actingAs($me)->getJson('/api/me/counts')->assertJsonPath('messages', 1);
        $this->actingAs($me)->getJson('/api/conversations')
            ->assertJsonPath('data.0.user.id', $friend->id)
            ->assertJsonPath('data.0.unread_count', 2)
            ->assertJsonPath('data.0.last_message.body', 'two');

        $this->actingAs($me)->getJson("/api/conversations/{$friend->username}")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.body', 'two');

        $this->actingAs($me)->getJson('/api/me/counts')->assertJsonPath('messages', 0);
    }

    public function test_cannot_read_someone_elses_conversation(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $snoop = User::factory()->create();
        Message::create(['sender_id' => $a->id, 'recipient_id' => $b->id, 'body' => 'private']);

        // The snoop only ever sees their own thread with each user, which is empty.
        $this->actingAs($snoop)->getJson("/api/conversations/{$a->username}")->assertJsonCount(0, 'data');
        $this->actingAs($snoop)->getJson("/api/conversations/{$b->username}")->assertJsonCount(0, 'data');
    }

    public function test_only_sender_can_delete_a_message(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $msg = Message::create(['sender_id' => $a->id, 'recipient_id' => $b->id, 'body' => 'oops']);

        $this->actingAs($b)->deleteJson("/api/messages/{$msg->id}")->assertForbidden();
        $this->actingAs($a)->deleteJson("/api/messages/{$msg->id}")->assertNoContent();
        $this->assertSoftDeleted($msg);
    }

    public function test_private_channel_only_authorizes_its_owner(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $callback = app(\Illuminate\Contracts\Broadcasting\Broadcaster::class)->getChannels()['App.Models.User.{id}'];

        $this->assertTrue($callback($me, $me->id));
        $this->assertFalse($callback($me, $other->id));
    }
}
