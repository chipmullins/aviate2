<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Notifications\PostLiked;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function store(Request $request, int $post): JsonResponse
    {
        $post = Post::query()->visible()->findOrFail($post);
        $me = $request->user();

        $like = $post->likes()->firstOrCreate(['user_id' => $me->id]);

        if ($like->wasRecentlyCreated && $post->user_id !== $me->id) {
            $post->author->notify(new PostLiked($me, $post->id));
        }

        return $this->state($post, true);
    }

    public function destroy(Request $request, int $post): JsonResponse
    {
        $post = Post::query()->visible()->findOrFail($post);
        $post->likes()->where('user_id', $request->user()->id)->delete();

        return $this->state($post, false);
    }

    private function state(Post $post, bool $liked): JsonResponse
    {
        return response()->json(['liked' => $liked, 'likes_count' => $post->likes()->count()]);
    }
}
