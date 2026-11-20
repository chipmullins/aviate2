<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trend;
use Illuminate\Http\JsonResponse;

class TrendController extends Controller
{
    public function index(): JsonResponse
    {
        $trends = Trend::query()->orderByDesc('hits')->orderBy('term')->limit(9)->get(['term', 'hits']);

        return response()->json(['data' => $trends]);
    }
}
