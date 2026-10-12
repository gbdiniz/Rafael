<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\RecordController;
use App\Http\Controllers\VoiceTurnController;
use Illuminate\Support\Facades\Route;

Route::get('manifest.webmanifest', function () {
    /** @var array<string, mixed> $manifest */
    $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true);

    return response()->json($manifest);
})->name('pwa.manifest');

Route::get('sw.js', function () {
    return response((string) file_get_contents(public_path('sw.js')), 200, [
        'Content-Type' => 'text/javascript',
        'Service-Worker-Allowed' => '/',
    ]);
})->name('pwa.service-worker');

Route::middleware('guest')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
    Route::livewire('/', 'pages::conversation')->name('conversation');
    Route::get('records', RecordController::class)->name('records.index');
    Route::post('voice-turns', [VoiceTurnController::class, 'store'])->name('voice-turns.store');
});
