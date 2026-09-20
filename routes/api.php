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

// Módulo POS — RF-POS-001 / US-POS-01 y US-POS-02
Route::get('/platillos',                  [ComandaController::class, 'platillos']);
Route::get('/mesas',                      [ComandaController::class, 'mesas']);
Route::post('/comandas',                  [ComandaController::class, 'registrar']);
Route::get('/comandas/cocina',            [ComandaController::class, 'colaCocina']);
Route::patch('/comandas/{comanda}/estado', [ComandaController::class, 'actualizarEstado']);
