<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileResource;
use App\Models\User;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request, User $user): ProfileResource
    {
        // Closed accounts are hidden from everyone but their owner.
        abort_if($user->isClosed() && ! $request->user()->is($user), 404);

        return new ProfileResource($user);
    }
}
