<?php

namespace App\Http\Controllers;

use App\Actions\Voice\StoreVoiceTurn;
use App\Http\Requests\StoreVoiceTurnRequest;
use Illuminate\Http\JsonResponse;

class VoiceTurnController extends Controller
{
    public function store(StoreVoiceTurnRequest $request, StoreVoiceTurn $storeVoiceTurn): JsonResponse
    {
        $voiceTurn = $storeVoiceTurn->handle(
            $request->user(),
            $request->file('audio'),
        );

        return response()->json([
            'uuid' => $voiceTurn->uuid,
        ]);
    }
}
