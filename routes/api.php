<?php

use App\Http\Controllers\AperturaCajaController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CierreCajaController;
use App\Http\Controllers\ComandaController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\DashboardGerencialController;
use App\Http\Controllers\ExportacionReporteController;
use App\Http\Controllers\EgresoController;
use App\Http\Controllers\InsumoController;
use App\Http\Controllers\MatrizPermisoController;
use App\Http\Controllers\MesaController;
use App\Http\Controllers\MovimientoCajaController;
use App\Http\Controllers\MovimientoContableController;
use App\Http\Controllers\MovimientoInventarioController;
use App\Http\Controllers\PlatilloController;
use App\Http\Controllers\RecetaController;
use App\Http\Controllers\RequisicionController;
use App\Http\Controllers\RetiroController;
use App\Http\Controllers\SubcategoriaEgresoController;
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

    // Módulo Administración y Seguridad — Gestión de Usuarios, Roles RBAC y Matriz (US-ADM-01 / CC-84 / US-ADM-04 / CC-121)
    Route::middleware('role:administrador|socio')->group(function () {
        Route::get('/roles',                                [UsuarioController::class, 'roles']);
        Route::get('/matriz-permisos',                      [MatrizPermisoController::class, 'index']);
        Route::get('/roles/matriz',                         [MatrizPermisoController::class, 'index']);
        Route::get('/usuarios',                             [UsuarioController::class, 'index']);
        Route::post('/usuarios',                            [UsuarioController::class, 'store']);
        Route::get('/usuarios/{usuario}',                   [UsuarioController::class, 'show']);
        Route::put('/usuarios/{usuario}',                   [UsuarioController::class, 'update']);
        Route::delete('/usuarios/{usuario}',                [UsuarioController::class, 'destroy']);
        Route::patch('/usuarios/{usuario}/toggle-activo',   [UsuarioController::class, 'toggleActivo']);

        // Bitácora de auditoría, solo lectura (US-ADM-04 / CC-124)
        Route::get('/auditorias',                           [AuditoriaController::class, 'index']);
    });

    // Módulo Administración y Seguridad — Actualización de la matriz de permisos (US-ADM-04 / CC-122)
    Route::middleware('role:administrador')->group(function () {
        Route::put('/roles/{rol}/permisos',                 [MatrizPermisoController::class, 'actualizarPermisos']);
        Route::put('/matriz-permisos/{rol}',                [MatrizPermisoController::class, 'actualizarPermisos']);
    });

    // Módulo POS — RF-POS-002 (Cobros y Ventas): solo cajero y administrador (CLAUDE.md §9)
    Route::middleware('role:cajero|administrador|socio')->group(function () {
        Route::post('/cobros',     [VentaController::class, 'cobrar']);
        Route::post('/ventas',     [VentaController::class, 'cobrar']);
        Route::get('/cobros/{id}', [VentaController::class, 'mostrar']);
        Route::get('/ventas/{id}', [VentaController::class, 'mostrar']);

        // Retiros parciales de efectivo asociados al turno (CC-37)
        Route::get('/retiros',  [RetiroController::class, 'index']);
        Route::post('/retiros', [RetiroController::class, 'store']);

        // Apertura de caja y saldo inicial por denominación (CC-39 / US-POS-04)
        Route::get('/aperturas-caja/turno', [AperturaCajaController::class, 'obtenerDelTurno']);
        Route::post('/aperturas-caja',       [AperturaCajaController::class, 'store']);

        // Cierre diario y cuadre de caja (CC-41 / US-POS-04)
        Route::post('/cierres-caja',        [CierreCajaController::class, 'cerrar']);
        Route::get('/cierres-caja',         [CierreCajaController::class, 'historial']);
        Route::get('/cierres-caja/{fecha}', [CierreCajaController::class, 'mostrar']);

        // Resumen y movimientos de caja del día: ventas, retiros y efectivo esperado
        Route::get('/movimientos-caja', [MovimientoCajaController::class, 'index']);
    });

    // Módulo Contabilidad — RF-CON-005 (Egresos): solo contadora y administrador (CLAUDE.md §9)
    Route::middleware('role:contadora|administrador|socio')->group(function () {
        // Catálogo para clasificar facturas en el formulario rápido (CC-62 / US-CON-01)
        Route::get('/subcategorias-egreso', [SubcategoriaEgresoController::class, 'index']);

        // Historial con desglose de IVA y totales del mes (CC-69 / US-CON-02)
        Route::get('/egresos', [EgresoController::class, 'index']);

        // Registro de facturas con correlativo único (CC-63 / US-CON-01)
        Route::post('/egresos', [EgresoController::class, 'store']);

        // Libro de movimientos: ventas cobradas (ingresos) y egresos, con flujo neto
        Route::get('/movimientos-contables', [MovimientoContableController::class, 'index']);
    });

    // Panel de configuración — catálogo de subcategorías de egreso (US-ADM-03 / CC-108)
    // Sin DELETE: los egresos históricos las referencian, por eso solo se desactivan.
    Route::middleware('role:administrador|socio')->group(function () {
        Route::get('/subcategorias-egreso/todas', [SubcategoriaEgresoController::class, 'todas']);
        Route::post('/subcategorias-egreso',      [SubcategoriaEgresoController::class, 'store']);
        Route::put('/subcategorias-egreso/{subcategoriaEgreso}', [SubcategoriaEgresoController::class, 'update']);
        Route::patch(
            '/subcategorias-egreso/{subcategoriaEgreso}/toggle-activo',
            [SubcategoriaEgresoController::class, 'toggleActivo'],
        );
    });

    // Panel de configuración — catálogo de mesas (US-ADM-03 / CC-107)
    // IMPORTANTE: /mesas/todas debe ir antes de /mesas/{mesa} para evitar conflicto de rutas
    Route::middleware('role:administrador|socio')->group(function () {
        Route::get('/mesas/todas',                 [MesaController::class, 'todas']);
        Route::post('/mesas',                      [MesaController::class, 'store']);
        Route::put('/mesas/{mesa}',                [MesaController::class, 'update']);
        Route::delete('/mesas/{mesa}',             [MesaController::class, 'destroy']);
        Route::patch('/mesas/{mesa}/toggle-activo', [MesaController::class, 'toggleActivo']);
    });

    // US-ADM-03 / CC-104 / CC-106: catálogo de platillos exclusivo de administración y socios.
    // IMPORTANTE: /platillos/catalogo debe ir antes de /platillos/{platillo} para evitar conflicto de rutas
    Route::middleware('role:administrador|socio')->group(function () {
        Route::get('/platillos/catalogo',                    [PlatilloController::class, 'index']);
        Route::post('/platillos',                            [PlatilloController::class, 'store']);
        Route::get('/platillos/{platillo}',                  [PlatilloController::class, 'show']);
        Route::put('/platillos/{platillo}',                  [PlatilloController::class, 'update']);
        Route::delete('/platillos/{platillo}',               [PlatilloController::class, 'destroy']);
        Route::patch('/platillos/{platillo}/disponibilidad', [PlatilloController::class, 'disponibilidad']);
    });

    // Módulo POS — RF-POS-001 (Comandas). Roles según CLAUDE.md §9 (CC-97)
    Route::get('/platillos', [ComandaController::class, 'platillos'])
        ->middleware('role:mesero|cajero|cocinero|cocinera|administrador|socio');

    Route::middleware('role:mesero|cajero|administrador|socio')->group(function () {
        Route::get('/mesas',     [ComandaController::class, 'mesas']);
        Route::post('/comandas', [ComandaController::class, 'registrar']);
    });

    Route::get('/comandas', [ComandaController::class, 'index'])
        ->middleware('role:cajero|administrador|socio');

    // US-POS-02: Cola de cocina y cambio de estado (CC-27 / CC-28)
    // IMPORTANTE: /comandas/cocina debe ir antes de /comandas/{comanda} para evitar conflicto de rutas
    Route::middleware('role:cocinero|cocinera|administrador|socio')->group(function () {
        Route::get('/comandas/cocina',              [ComandaController::class, 'colaCocina']);
        Route::get('/comandas/{comanda}/impresion', [ComandaController::class, 'impresion']);
    });

    // El cajero cambia el estado al cobrar o cancelar; cocina al preparar
    Route::patch('/comandas/{comanda}/estado', [ComandaController::class, 'actualizarEstado'])
        ->middleware('role:cocinero|cocinera|cajero|administrador|socio');

    // CC-113 / CC-115 / CC-116 / CC-117: movimientos exclusivos de administracion y socios.
    Route::middleware('role:administrador|socio')->group(function () {
        Route::post('/insumos/{insumo}/entradas', [MovimientoInventarioController::class, 'entrada']);
        Route::post('/insumos/{insumo}/ajustes', [MovimientoInventarioController::class, 'ajuste']);
        Route::get('/insumos/{insumo}/movimientos', [MovimientoInventarioController::class, 'index']);
    });

    // Módulo Inventario — cocina, administrador y socio gestionan insumos y recetas (CC-98)
    Route::middleware('role:cocinero|cocinera|administrador|socio')->group(function () {
        // CC-45: CRUD de insumos con unidad de medida (US-INV-01 / RF-INV-003)
        Route::get('/insumos',  [InsumoController::class, 'index']);
        Route::post('/insumos', [InsumoController::class, 'store']);

        // CC-57: Alertas de quiebre de stock (US-INV-03 / RF-INV-004)
        // IMPORTANTE: /insumos/alertas debe ir antes de /insumos/{insumo} para evitar conflicto de rutas
        Route::get('/insumos/alertas', [InsumoController::class, 'alertas']);

        Route::get('/insumos/{insumo}',    [InsumoController::class, 'show']);
        Route::put('/insumos/{insumo}',    [InsumoController::class, 'update']);
        Route::delete('/insumos/{insumo}', [InsumoController::class, 'destroy']);

        // CC-46: Vincular ingredientes a platillos (US-INV-01 / RF-INV-003)
        // scopeBindings: el renglón {receta} debe pertenecer al {platillo} de la URL, si no responde 404
        Route::scopeBindings()->group(function () {
            Route::get('/platillos/{platillo}/recetas',             [RecetaController::class, 'index']);
            Route::post('/platillos/{platillo}/recetas',            [RecetaController::class, 'store']);
            Route::put('/platillos/{platillo}/recetas/{receta}',    [RecetaController::class, 'update']);
            Route::delete('/platillos/{platillo}/recetas/{receta}', [RecetaController::class, 'destroy']);
        });
    });
});

// Módulo Inventario — CC-52: Solicitudes internas por área (US-INV-02 / RF-INV-004)
Route::middleware(['auth:sanctum', 'role:cocinero|cocinera|administrador|socio'])->group(function () {
    Route::post('/requisiciones', [RequisicionController::class, 'store']);
});

// Módulo Inventario — CC-53 / CC-54: aprobación y transferencia de requisiciones
Route::middleware(['auth:sanctum', 'role:administrador|socio'])->group(function () {
    Route::get('/requisiciones', [RequisicionController::class, 'index']);
    Route::patch('/requisiciones/{requisicion}/resolucion', [RequisicionController::class, 'resolver']);
    Route::post('/requisiciones/{requisicion}/transferir', [RequisicionController::class, 'transferir']);
});

// Módulo Contabilidad — CC-66 / CC-67: desglose automático de IVA crédito fiscal
Route::middleware(['auth:sanctum', 'role:contadora|administrador|socio'])->group(function () {
    Route::get('/compras', [CompraController::class, 'index']);
    Route::post('/compras', [CompraController::class, 'store']);
});

// Módulo Reportes — CC-75 / CC-76: KPIs financieros exclusivos de socios y administración
Route::middleware(['auth:sanctum', 'role:socio|administrador'])->group(function () {
    Route::get('/reportes/dashboard', [DashboardGerencialController::class, 'index']);
});

// Módulo Reportes — CC-79: exportación del reporte consolidado para llenado manual
Route::middleware(['auth:sanctum', 'role:contadora|socio|administrador'])->group(function () {
    Route::get('/reportes/exportar', [ExportacionReporteController::class, 'exportar']);
});
