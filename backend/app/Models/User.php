<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['first_name', 'last_name', 'username', 'email', 'password', 'avatar_path', 'closed_at'])]
#[Hidden(['password', 'remember_token', 'email'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'closed_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    // ---- Relationships -------------------------------------------------

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function friends(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'friendships', 'user_id', 'friend_id')->withTimestamps();
    }

    public function sentFriendRequests(): HasMany
    {
        return $this->hasMany(FriendRequest::class, 'sender_id');
    }

    public function receivedFriendRequests(): HasMany
    {
        return $this->hasMany(FriendRequest::class, 'recipient_id');
    }

    // ---- Scopes --------------------------------------------------------

    /** Users whose accounts are open. */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('closed_at');
    }

    // ---- Accessors -----------------------------------------------------

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim($this->first_name.' '.$this->last_name));
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(fn () => $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null);
    }

    /** The airline name, worked out from the email domain. */
    protected function airline(): Attribute
    {
        return Attribute::get(function () {
            $domain = Str::lower(Str::afterLast((string) $this->email, '@'));

            return config("aviate.airlines.$domain");
        });
    }

    // ---- Friendship helpers --------------------------------------------

    /** A sample account that anyone can sign in as while the site runs in demo mode. */
    public function isLockedDemoAccount(): bool
    {
        return $this->is_demo && config('aviate.demo.enabled');
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    public function isFriendsWith(User $other): bool
    {
        return DB::table('friendships')
            ->where('user_id', $this->id)
            ->where('friend_id', $other->id)
            ->exists();
    }

    /** @return array<int> */
    public function friendIds(): array
    {
        return DB::table('friendships')->where('user_id', $this->id)->pluck('friend_id')->all();
    }

    public function mutualFriendsCount(User $other): int
    {
        if ($this->is($other)) {
            return 0;
        }

        return DB::table('friendships as a')
            ->join('friendships as b', 'a.friend_id', '=', 'b.friend_id')
            ->where('a.user_id', $this->id)
            ->where('b.user_id', $other->id)
            ->count();
    }

    /** One of: self, friends, request_sent, request_received, none. */
    public function friendshipStatusWith(User $other): string
    {
        if ($this->is($other)) {
            return 'self';
        }
        if ($this->isFriendsWith($other)) {
            return 'friends';
        }
        if ($this->sentFriendRequests()->where('recipient_id', $other->id)->exists()) {
            return 'request_sent';
        }
        if ($this->receivedFriendRequests()->where('sender_id', $other->id)->exists()) {
            return 'request_received';
        }

        return 'none';
    }

    /** Builds a unique username like "jane.pilot" or "jane.pilot2". */
    public static function generateUsername(string $first, string $last): string
    {
        $base = Str::of($first.'.'.$last)->ascii()->lower()->replaceMatches('/[^a-z0-9.]/', '')->trim('.')->limit(50, '')->value();
        $base = $base !== '' ? $base : 'aviator';

        $username = $base;
        $i = 1;
        while (static::where('username', $username)->exists()) {
            $i++;
            $username = $base.$i;
        }

        return $username;
    }
}
