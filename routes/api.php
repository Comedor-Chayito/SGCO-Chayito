<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CierreCajaController;
use App\Http\Controllers\ComandaController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VentaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — SGCO-Chayito
|--------------------------------------------------------------------------
| Contrato: toda respuesta retorna { status, message, data }.
| Autenticación por rol (Sanctum + RBAC Spatie).
*/

// Módulo Administración y Seguridad — Autenticación (US-ADM-02 / Sec. 3.5.1)
Route::post('/sesiones',   [AuthController::class, 'store']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::delete('/sesiones',  [AuthController::class, 'destroy']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/usuario', fn (Request $request) => response()->json([
        'status'  => 'ok',
        'message' => 'Usuario autenticado obtenido.',
        'data'    => $request->user()->load(['roles:id,name', 'permissions:id,name']),
    ]));

    // Módulo Administración y Seguridad — Gestión de Usuarios y Roles RBAC (US-ADM-01 / CC-84)
    Route::middleware('role:administrador')->group(function () {
        Route::get('/roles',                                [UsuarioController::class, 'roles']);
        Route::get('/usuarios',                             [UsuarioController::class, 'index']);
        Route::post('/usuarios',                            [UsuarioController::class, 'store']);
        Route::get('/usuarios/{usuario}',                   [UsuarioController::class, 'show']);
        Route::put('/usuarios/{usuario}',                   [UsuarioController::class, 'update']);
        Route::delete('/usuarios/{usuario}',                [UsuarioController::class, 'destroy']);
        Route::patch('/usuarios/{usuario}/toggle-activo',   [UsuarioController::class, 'toggleActivo']);
    });
});

// Módulo POS — RF-POS-001 (Comandas)
Route::get('/platillos',                    [ComandaController::class, 'platillos']);
Route::get('/mesas',                        [ComandaController::class, 'mesas']);
Route::get('/comandas',                     [ComandaController::class, 'index']);
Route::post('/comandas',                    [ComandaController::class, 'registrar']);
Route::get('/comandas/cocina',              [ComandaController::class, 'colaCocina']);
Route::patch('/comandas/{comanda}/estado',  [ComandaController::class, 'actualizarEstado']);
Route::get('/comandas/{comanda}/impresion', [ComandaController::class, 'impresion']);

// Módulo POS — RF-POS-002 (Cobros y Ventas)
Route::post('/cobros',       [VentaController::class, 'cobrar']);
Route::post('/ventas',       [VentaController::class, 'cobrar']);
Route::get('/cobros/{id}',   [VentaController::class, 'mostrar']);
Route::get('/ventas/{id}',   [VentaController::class, 'mostrar']);

// Módulo POS — RF-POS-002 (Cierre de Caja)
Route::post('/cierres-caja',        [CierreCajaController::class, 'cerrar']);
Route::get('/cierres-caja',         [CierreCajaController::class, 'historial']);
Route::get('/cierres-caja/{fecha}', [CierreCajaController::class, 'mostrar']);
