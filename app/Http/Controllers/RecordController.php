<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class RecordController extends Controller
{
    /**
     * @return array<int, mixed>
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([]);
    }
}
