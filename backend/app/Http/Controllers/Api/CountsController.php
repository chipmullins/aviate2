<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The badge numbers in the top bar. */
class CountsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $me = $request->user();

        return response()->json([
            'friend_requests' => $me->receivedFriendRequests()->whereHas('sender', fn ($q) => $q->whereNull('closed_at'))->count(),
            'messages' => Message::where('recipient_id', $me->id)->whereNull('read_at')->distinct()->count('sender_id'),
            'notifications' => $me->unreadNotifications()->count(),
        ]);
    }
}
