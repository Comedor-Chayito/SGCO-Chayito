<?php

use Illuminate\Support\Facades\Route;

// Ruta raíz SPA — todas las rutas las maneja Vue Router
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
