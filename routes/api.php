<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CierreCajaController;
use App\Http\Controllers\ComandaController;
use App\Http\Controllers\InsumoController;
use App\Http\Controllers\RecetaController;
use App\Http\Controllers\RetiroController;
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

    // Módulo POS — RF-POS-002 (Cobros y Ventas): solo cajero y administrador (CLAUDE.md §9)
    Route::middleware('role:cajero|administrador')->group(function () {
        Route::post('/cobros',     [VentaController::class, 'cobrar']);
        Route::post('/ventas',     [VentaController::class, 'cobrar']);
        Route::get('/cobros/{id}', [VentaController::class, 'mostrar']);
        Route::get('/ventas/{id}', [VentaController::class, 'mostrar']);

        // Retiros parciales de efectivo asociados al turno (CC-37)
        Route::get('/retiros',  [RetiroController::class, 'index']);
        Route::post('/retiros', [RetiroController::class, 'store']);

        // Cierre diario y cuadre de caja (CC-41 / US-POS-04)
        Route::post('/cierres-caja',        [CierreCajaController::class, 'cerrar']);
        Route::get('/cierres-caja',         [CierreCajaController::class, 'historial']);
        Route::get('/cierres-caja/{fecha}', [CierreCajaController::class, 'mostrar']);
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

// US-POS-02: Cola de cocina y cambio de estado (CC-27 / CC-28)
// IMPORTANTE: /comandas/cocina debe ir antes de /comandas/{comanda} para evitar conflicto de rutas
Route::get('/comandas/cocina',              [ComandaController::class, 'colaCocina']);
Route::patch('/comandas/{comanda}/estado',  [ComandaController::class, 'actualizarEstado']);

// Módulo Inventario — CC-45: CRUD de insumos con unidad de medida (US-INV-01 / RF-INV-003)
Route::get('/insumos',           [InsumoController::class, 'index']);
Route::post('/insumos',          [InsumoController::class, 'store']);
Route::get('/insumos/{insumo}',  [InsumoController::class, 'show']);
Route::put('/insumos/{insumo}',  [InsumoController::class, 'update']);
Route::delete('/insumos/{insumo}', [InsumoController::class, 'destroy']);

// Módulo Inventario — CC-46: Vincular ingredientes a platillos (US-INV-01 / RF-INV-003)
Route::get('/platillos/{platillo}/recetas',                        [RecetaController::class, 'index']);
Route::post('/platillos/{platillo}/recetas',                       [RecetaController::class, 'store']);
Route::put('/platillos/{platillo}/recetas/{receta}',               [RecetaController::class, 'update']);
Route::delete('/platillos/{platillo}/recetas/{receta}',            [RecetaController::class, 'destroy']);

