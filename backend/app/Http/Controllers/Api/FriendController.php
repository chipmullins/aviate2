<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FriendRequestResource;
use App\Http\Resources\UserResource;
use App\Models\FriendRequest;
use App\Models\User;
use App\Notifications\FriendRequestAccepted;
use App\Notifications\FriendRequestReceived;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class FriendController extends Controller
{
    /** A user's friends (open accounts only). */
    public function index(Request $request, User $user): AnonymousResourceCollection
    {
        abort_if($user->isClosed() && ! $request->user()->is($user), 404);

        $friends = $user->friends()->active()->orderBy('first_name')->orderBy('last_name')->paginate(30);

        return UserResource::collection($friends);
    }

    /** Friend requests waiting for me to answer. */
    public function requests(Request $request): AnonymousResourceCollection
    {
        $requests = $request->user()->receivedFriendRequests()
            ->whereHas('sender', fn ($q) => $q->whereNull('closed_at'))
            ->with('sender')
            ->latest('id')
            ->get();

        return FriendRequestResource::collection($requests);
    }

    /** Send a request. If they already sent me one, this accepts it instead. */
    public function send(Request $request, User $user): JsonResponse
    {
        $me = $request->user();
        $this->ensureCanInteract($me, $user);

        if ($me->isFriendsWith($user)) {
            return $this->status($me, $user);
        }

        $theirs = FriendRequest::where('sender_id', $user->id)->where('recipient_id', $me->id)->first();
        if ($theirs) {
            return $this->acceptRequest($me, $user, $theirs);
        }

        $sent = FriendRequest::firstOrCreate(['sender_id' => $me->id, 'recipient_id' => $user->id]);
        if ($sent->wasRecentlyCreated) {
            $user->notify(new FriendRequestReceived($me));
        }

        return $this->status($me, $user);
    }

    public function accept(Request $request, User $user): JsonResponse
    {
        $me = $request->user();
        $this->ensureCanInteract($me, $user);

        $theirs = FriendRequest::where('sender_id', $user->id)->where('recipient_id', $me->id)->firstOrFail();

        return $this->acceptRequest($me, $user, $theirs);
    }

    /** Cancels a request I sent, or declines one I received. */
    public function cancel(Request $request, User $user): JsonResponse
    {
        $me = $request->user();

        FriendRequest::query()
            ->where(fn ($q) => $q->where('sender_id', $me->id)->where('recipient_id', $user->id))
            ->orWhere(fn ($q) => $q->where('sender_id', $user->id)->where('recipient_id', $me->id))
            ->delete();

        return $this->status($me, $user);
    }

    public function unfriend(Request $request, User $user): JsonResponse
    {
        $me = $request->user();

        DB::table('friendships')
            ->where(fn ($q) => $q->where('user_id', $me->id)->where('friend_id', $user->id))
            ->orWhere(fn ($q) => $q->where('user_id', $user->id)->where('friend_id', $me->id))
            ->delete();

        return $this->status($me, $user);
    }

    private function acceptRequest(User $me, User $sender, FriendRequest $friendRequest): JsonResponse
    {
        DB::transaction(function () use ($me, $sender, $friendRequest) {
            $now = now();
            DB::table('friendships')->insertOrIgnore([
                ['user_id' => $me->id, 'friend_id' => $sender->id, 'created_at' => $now, 'updated_at' => $now],
                ['user_id' => $sender->id, 'friend_id' => $me->id, 'created_at' => $now, 'updated_at' => $now],
            ]);
            $friendRequest->delete();
            // Clear a crossed request in the other direction, if any.
            FriendRequest::where('sender_id', $me->id)->where('recipient_id', $sender->id)->delete();
        });

        $sender->notify(new FriendRequestAccepted($me));

        return $this->status($me, $sender);
    }

    private function ensureCanInteract(User $me, User $other): void
    {
        abort_if($me->is($other), 422, 'You can\'t friend yourself.');
        abort_if($other->isClosed(), 404);
    }

    private function status(User $me, User $other): JsonResponse
    {
        return response()->json([
            'friendship_status' => $me->friendshipStatusWith($other),
        ]);
    }
}
