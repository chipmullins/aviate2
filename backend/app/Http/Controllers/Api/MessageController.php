<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Http\Resources\UserResource;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MessageController extends Controller
{
    /** My conversations, newest first, each with its latest message and unread count. */
    public function conversations(Request $request): JsonResponse
    {
        $meId = $request->user()->id;

        $latest = DB::query()
            ->fromSub(
                Message::query()
                    ->selectRaw('CASE WHEN sender_id = ? THEN recipient_id ELSE sender_id END AS partner_id, id', [$meId])
                    ->where(fn ($q) => $q->where('sender_id', $meId)->orWhere('recipient_id', $meId)),
                'm'
            )
            ->selectRaw('partner_id, MAX(id) AS last_id')
            ->groupBy('partner_id')
            ->orderByDesc('last_id')
            ->limit(50)
            ->get();

        $partners = User::active()->whereIn('id', $latest->pluck('partner_id'))->get()->keyBy('id');
        $messages = Message::whereIn('id', $latest->pluck('last_id'))->get()->keyBy('id');
        $unread = Message::query()
            ->where('recipient_id', $meId)
            ->whereNull('read_at')
            ->selectRaw('sender_id, COUNT(*) AS c')
            ->groupBy('sender_id')
            ->pluck('c', 'sender_id');

        $data = $latest
            ->filter(fn ($row) => $partners->has($row->partner_id))
            ->map(fn ($row) => [
                'user' => (new UserResource($partners[$row->partner_id]))->resolve($request),
                'last_message' => (new MessageResource($messages[$row->last_id]))->resolve($request),
                'unread_count' => (int) ($unread[$row->partner_id] ?? 0),
            ])
            ->values();

        return response()->json(['data' => $data]);
    }

    /** One conversation, newest first (the app reverses it for display). Opening it marks their messages read. */
    public function thread(Request $request, User $user): AnonymousResourceCollection
    {
        $me = $request->user();
        abort_if($user->isClosed() || $me->is($user), 404);

        Message::where('sender_id', $user->id)
            ->where('recipient_id', $me->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = Message::between($me->id, $user->id)->orderByDesc('id')->cursorPaginate(30);

        return MessageResource::collection($messages);
    }

    public function send(Request $request, User $user): JsonResponse
    {
        $me = $request->user();
        abort_if($user->isClosed() || $me->is($user), 404);
        abort_unless($me->isFriendsWith($user), 403, 'You can only message your friends.');

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $message = Message::create([
            'sender_id' => $me->id,
            'recipient_id' => $user->id,
            'body' => trim($data['body']),
        ]);

        MessageSent::dispatch($message->load('sender'));

        return (new MessageResource($message))->response()->setStatusCode(201);
    }

    public function markRead(Request $request, User $user): Response
    {
        Message::where('sender_id', $user->id)
            ->where('recipient_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->noContent();
    }

    public function destroy(Message $message): Response
    {
        Gate::authorize('delete', $message);
        $message->delete();

        return response()->noContent();
    }
}
