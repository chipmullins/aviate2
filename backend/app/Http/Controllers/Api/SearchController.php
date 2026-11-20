<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\UserResource;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'max:100'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:30'],
        ]);
        $me = $request->user();

        $words = collect(preg_split('/\s+/', Str::lower(trim($data['q']))))->filter()->take(3);

        $users = User::active()
            ->whereKeyNot($me->id)
            ->where(function (Builder $query) use ($words) {
                // Every word must match the start of a first name, last name or username.
                foreach ($words as $word) {
                    $like = $this->escapeLike($word).'%';
                    $query->where(fn (Builder $q) => $q
                        ->whereRaw("LOWER(first_name) LIKE ? ESCAPE '\\'", [$like])
                        ->orWhereRaw("LOWER(last_name) LIKE ? ESCAPE '\\'", [$like])
                        ->orWhereRaw("LOWER(username) LIKE ? ESCAPE '\\'", [$like]));
                }
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit($data['limit'] ?? 20)
            ->get();

        return response()->json([
            'data' => $users->map(fn (User $u) => [
                ...(new UserResource($u))->resolve($request),
                'mutual_friends_count' => $me->mutualFriendsCount($u),
                'friendship_status' => $me->friendshipStatusWith($u),
            ]),
        ]);
    }

    /** Stops someone searching for "%" from matching every user. */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
