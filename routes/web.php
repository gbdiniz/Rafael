<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\RecordController;
use App\Http\Controllers\VoiceTurnController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
    Route::view('/', 'conversation')->name('conversation');
    Route::get('records', RecordController::class)->name('records.index');
    Route::post('voice-turns', [VoiceTurnController::class, 'store'])->name('voice-turns.store');
});
