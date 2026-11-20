<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['sender_id', 'recipient_id', 'body', 'read_at'])]
class Message extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /** Messages in the conversation between two users, either direction. */
    public function scopeBetween(Builder $query, int $a, int $b): void
    {
        $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $x) => $x->where('sender_id', $a)->where('recipient_id', $b))
            ->orWhere(fn (Builder $x) => $x->where('sender_id', $b)->where('recipient_id', $a)));
    }
}
