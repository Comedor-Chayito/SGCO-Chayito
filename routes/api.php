<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ComandaController;
use App\Http\Controllers\VentaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — SGCO-Chayito
|--------------------------------------------------------------------------
| Contrato: toda respuesta retorna { status, message, data }.
| Autenticación por rol (Sanctum + RBAC) se agrega en US-ADM-02.
*/

// Módulo Administración y Seguridad — US-ADM-02 / Sec. 3.5.1
Route::post('/sesiones',    [AuthController::class, 'store']);
Route::post('/auth/login',  [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::delete('/sesiones',   [AuthController::class, 'destroy']);
    Route::post('/auth/logout',  [AuthController::class, 'logout']);
    Route::get('/auth/usuario',  fn (Request $request) => response()->json([
        'status'  => 'ok',
        'message' => 'Usuario autenticado obtenido.',
        'data'    => $request->user(),
    ]));
});

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
