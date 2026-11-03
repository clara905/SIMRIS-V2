<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('auth.logout');

Route::get('/', function () {
    return view('welcome');
});
