<?php

use Illuminate\Support\Facades\Route;

// The Angular app is served by nginx; the backend only answers /api, /sanctum and /broadcasting.
Route::get('/', fn () => response()->json(['name' => 'Aviate API']));
