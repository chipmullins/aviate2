<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Post;
use App\Notifications\CommentedOnWallPost;
use App\Notifications\PostCommented;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function index(int $post): AnonymousResourceCollection
    {
        $post = Post::query()->visible()->findOrFail($post);

        $comments = $post->comments()
            ->with(['author', 'post'])
            ->whereHas('author', fn ($q) => $q->whereNull('closed_at'))
            ->orderBy('id')
            ->cursorPaginate(20);

        return CommentResource::collection($comments);
    }

    public function store(Request $request, int $post): JsonResponse
    {
        $post = Post::query()->visible()->findOrFail($post);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $me = $request->user();

        $comment = $post->comments()->create([
            'user_id' => $me->id,
            'body' => trim($data['body']),
        ]);

        if ($post->user_id !== $me->id) {
            $post->author->notify(new PostCommented($me, $post->id));
        }
        if ($post->wall_user_id && $post->wall_user_id !== $me->id && $post->wall_user_id !== $post->user_id) {
            $post->wallUser->notify(new CommentedOnWallPost($me, $post->id));
        }

        return (new CommentResource($comment->load('author', 'post')))->response()->setStatusCode(201);
    }

    public function destroy(Comment $comment): Response
    {
        Gate::authorize('delete', $comment);
        $comment->delete();

        return response()->noContent();
    }
}
