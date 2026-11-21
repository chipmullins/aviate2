<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\CountsController;
use App\Http\Controllers\Api\DemoController;
use App\Http\Controllers\Api\FriendController;
use App\Http\Controllers\Api\LikeController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\TrendController;
use App\Http\Middleware\EnsureAccountIsOpen;
use Illuminate\Support\Facades\Route;

// ---- Public --------------------------------------------------------------
Route::middleware('throttle:auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('demo/login', [DemoController::class, 'login']);
});

// Whether this server is a public demo, and which sample accounts to offer.
Route::get('demo', [DemoController::class, 'show']);

// Lets the sign-up form show which airlines are allowed.
Route::get('allowed-domains', fn () => ['data' => config('aviate.allowed_email_domains')]);

// The public Reverb key the browser needs to open a WebSocket (not a secret).
Route::get('realtime-config', fn () => ['key' => config('broadcasting.connections.reverb.key')]);

// ---- Signed in -----------------------------------------------------------
Route::middleware(['auth:sanctum', EnsureAccountIsOpen::class])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
    Route::get('me/counts', CountsController::class);

    // Settings
    Route::put('me/profile', [AccountController::class, 'updateProfile']);
    Route::put('me/password', [AccountController::class, 'updatePassword']);
    Route::post('me/avatar', [AccountController::class, 'updateAvatar'])->middleware('throttle:uploads');
    Route::delete('me/avatar', [AccountController::class, 'deleteAvatar']);
    Route::post('me/close', [AccountController::class, 'close']);

    // Feed and posts
    Route::get('feed', [PostController::class, 'feed']);
    Route::post('posts', [PostController::class, 'store'])->middleware('throttle:posting');
    Route::get('posts/{post}', [PostController::class, 'show'])->whereNumber('post');
    Route::delete('posts/{post}', [PostController::class, 'destroy']);

    Route::get('posts/{post}/comments', [CommentController::class, 'index'])->whereNumber('post');
    Route::post('posts/{post}/comments', [CommentController::class, 'store'])->whereNumber('post')->middleware('throttle:posting');
    Route::delete('comments/{comment}', [CommentController::class, 'destroy']);

    Route::post('posts/{post}/like', [LikeController::class, 'store'])->whereNumber('post');
    Route::delete('posts/{post}/like', [LikeController::class, 'destroy'])->whereNumber('post');

    // People
    Route::get('search/users', [SearchController::class, 'users']);
    Route::get('trends', [TrendController::class, 'index']);

    Route::get('users/{user:username}', [ProfileController::class, 'show']);
    Route::get('users/{user:username}/posts', [PostController::class, 'forUser']);
    Route::get('users/{user:username}/friends', [FriendController::class, 'index']);

    // Friends
    Route::get('friend-requests', [FriendController::class, 'requests']);
    Route::post('users/{user:username}/friend-request', [FriendController::class, 'send']);
    Route::post('users/{user:username}/friend-request/accept', [FriendController::class, 'accept']);
    Route::delete('users/{user:username}/friend-request', [FriendController::class, 'cancel']);
    Route::delete('users/{user:username}/friend', [FriendController::class, 'unfriend']);

    // Messages
    Route::get('conversations', [MessageController::class, 'conversations']);
    Route::get('conversations/{user:username}', [MessageController::class, 'thread']);
    Route::post('conversations/{user:username}', [MessageController::class, 'send'])->middleware('throttle:posting');
    Route::post('conversations/{user:username}/read', [MessageController::class, 'markRead']);
    Route::delete('messages/{message}', [MessageController::class, 'destroy']);

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->whereUuid('id');
});
