<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ComandaController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — SGCO-Chayito
|--------------------------------------------------------------------------
| Contrato: toda respuesta retorna { status, message, data }.
| Autenticación por rol (Sanctum + RBAC) se agrega en US-ADM-02.
*/

// Módulo Admin — RF-ADM-008 (Sesiones)
Route::post('/sesiones', [AuthController::class, 'store']);
Route::delete('/sesiones', [AuthController::class, 'destroy'])->middleware('auth:sanctum');

// Módulo POS — RF-POS-001 (Comandas)
Route::get('/platillos',  [ComandaController::class, 'platillos']);
Route::get('/mesas',      [ComandaController::class, 'mesas']);
Route::get('/comandas',   [ComandaController::class, 'index']);
Route::post('/comandas',  [ComandaController::class, 'registrar']);

// Módulo POS — RF-POS-002 (Cobros y Ventas)
Route::post('/cobros',       [VentaController::class, 'cobrar']);
Route::post('/ventas',       [VentaController::class, 'cobrar']);
Route::get('/cobros/{id}',   [VentaController::class, 'mostrar']);
Route::get('/ventas/{id}',   [VentaController::class, 'mostrar']);
