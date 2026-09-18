<?php

use App\Http\Controllers\ComandaController;
use Illuminate\Support\Facades\Route;

Route::post('/comandas', [ComandaController::class, 'store']);
