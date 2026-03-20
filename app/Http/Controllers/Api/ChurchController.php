<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Church;
use Illuminate\Http\JsonResponse;

class ChurchController extends Controller
{
    public function show(Church $church): JsonResponse
    {
        return response()->json([
            'data' => $church->load(['users', 'serviceSchedules']),
        ]);
    }

    public function serviceSchedules(Church $church): JsonResponse
    {
        return response()->json([
            'data' => $church->serviceSchedules()->get(),
        ]);
    }
}
