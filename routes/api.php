<?php

use App\Http\Controllers\ComandaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — SGCO-Chayito
|--------------------------------------------------------------------------
| Contrato: toda respuesta retorna { status, message, data }.
| Autenticación por rol (Sanctum + RBAC) se agrega en US-ADM-02.
*/

// Módulo POS — RF-POS-001
Route::get('/platillos',  [ComandaController::class, 'platillos']);
Route::get('/mesas',      [ComandaController::class, 'mesas']);
Route::post('/comandas',  [ComandaController::class, 'registrar']);
