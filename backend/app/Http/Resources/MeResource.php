<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** The signed-in user, including private fields only they should see. */
class MeResource extends UserResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'email' => $this->email,
            'joined_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
