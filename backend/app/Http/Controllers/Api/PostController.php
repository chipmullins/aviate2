<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\User;
use App\Notifications\WallPostCreated;
use App\Services\ImageService;
use App\Services\TrendService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PostController extends Controller
{
    public function __construct(private ImageService $images, private TrendService $trends)
    {
    }

    /** News feed: your posts and your friends' posts, plus anything written on your profile. */
    public function feed(Request $request): AnonymousResourceCollection
    {
        $me = $request->user();
        $ids = [...$me->friendIds(), $me->id];

        $posts = Post::query()
            ->visible()
            ->withCardData($me)
            ->where(fn (Builder $q) => $q->whereIn('user_id', $ids)->orWhere('wall_user_id', $me->id))
            ->orderByDesc('id')
            ->cursorPaginate(10);

        return PostResource::collection($posts);
    }

    /** Posts shown on someone's profile: what they posted to their own profile, and what others posted there. */
    public function forUser(Request $request, User $user): AnonymousResourceCollection
    {
        $this->abortIfClosed($request, $user);

        $posts = Post::query()
            ->visible()
            ->withCardData($request->user())
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $own) => $own->where('user_id', $user->id)->whereNull('wall_user_id'))
                ->orWhere('wall_user_id', $user->id))
            ->orderByDesc('id')
            ->cursorPaginate(10);

        return PostResource::collection($posts);
    }

    public function show(Request $request, int $post): PostResource
    {
        $post = Post::query()->visible()->withCardData($request->user())->findOrFail($post);

        return new PostResource($post);
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $me = $request->user();
        $wallUser = null;

        if ($request->filled('wall') && $request->input('wall') !== $me->username) {
            $wallUser = User::active()->where('username', $request->input('wall'))->firstOrFail();

            abort_unless($me->isFriendsWith($wallUser), 403, 'You can only post on your friends\' profiles.');
        }

        $body = $request->filled('body') ? trim($request->input('body')) : null;
        $imagePath = $request->hasFile('image') ? $this->images->storePostImage($request->file('image')) : null;

        abort_if($body === null && $imagePath === null, 422, 'Write something or add a photo.');

        $post = Post::create([
            'user_id' => $me->id,
            'wall_user_id' => $wallUser?->id,
            'body' => $body,
            'image_path' => $imagePath,
        ]);

        $this->trends->record($body);
        $wallUser?->notify(new WallPostCreated($me, $post->id));

        $post = Post::query()->withCardData($me)->findOrFail($post->id);

        return (new PostResource($post))->response()->setStatusCode(201);
    }

    public function destroy(Post $post): Response
    {
        Gate::authorize('delete', $post);

        $this->images->delete($post->image_path);
        $post->forceFill(['image_path' => null])->save();
        $post->delete();

        return response()->noContent();
    }

    private function abortIfClosed(Request $request, User $user): void
    {
        abort_if($user->isClosed() && ! $request->user()->is($user), 404);
    }
}
