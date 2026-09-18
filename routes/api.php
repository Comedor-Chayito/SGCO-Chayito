<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ComandaController;
use Illuminate\Support\Facades\Route;

Route::post('/comandas', [ComandaController::class, 'store']);
Route::post('/sesiones', [AuthController::class, 'store']);
