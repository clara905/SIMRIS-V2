<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('auth.logout');
Route::post('/api/konfirmasi-password', [AuthController::class, 'confirm'])->middleware(['auth:sanctum', 'account.active', 'throttle:6,1'])->name('auth.confirm');

Route::get('/', function () {
    return view('welcome');
});
