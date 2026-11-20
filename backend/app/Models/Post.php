<?php

namespace App\Models;

use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable(['user_id', 'wall_user_id', 'body', 'image_path'])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, SoftDeletes;

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function wallUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'wall_user_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    /** Hide posts whose author (or wall owner) has closed their account. */
    public function scopeVisible(Builder $query): void
    {
        $query->whereHas('author', fn (Builder $q) => $q->whereNull('closed_at'))
            ->where(fn (Builder $q) => $q->whereNull('wall_user_id')
                ->orWhereHas('wallUser', fn (Builder $w) => $w->whereNull('closed_at')));
    }

    /** Eager-load everything a PostResource needs for the given viewer. */
    public function scopeWithCardData(Builder $query, User $viewer): void
    {
        $query->with(['author', 'wallUser'])
            ->withCount(['likes', 'comments'])
            ->withExists(['likes as liked_by_me' => fn (Builder $q) => $q->where('user_id', $viewer->id)]);
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->image_path ? Storage::disk('public')->url($this->image_path) : null);
    }
}
