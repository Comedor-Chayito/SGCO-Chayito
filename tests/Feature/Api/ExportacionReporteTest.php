<?php

namespace Tests\Feature\Api;

use App\Models\Comanda;
use App\Models\ComandaDetalle;
use App\Models\Compra;
use App\Models\Egreso;
use App\Models\ExistenciaAreaOperativa;
use App\Models\Insumo;
use App\Models\Platillo;
use App\Models\SubcategoriaEgreso;
use App\Models\User;
use App\Models\Venta;
use App\Services\ExportacionReporteService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SubcategoriaEgresoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

/**
 * Pruebas de integración para la exportación de reportes consolidados en CSV y XLSX.
 *
 * @autor  CesarZV23005
 * @fecha  2026-10-25
 * @módulo Reportes – US-REP-02 / RF-REP-007 / CC-78 / CC-79 / CC-80 / CC-81 / CC-82
 */
class ExportacionReporteTest extends TestCase
{
    use RefreshDatabase;

    private User $administrador;

    private User $socio;

    private User $contadora;

    private User $cajero;

    private User $mesero;

    private User $cocinero;

    private User $cocinera;

    /**
     * Prepara el entorno con roles oficiales, permisos y usuarios de prueba.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – US-REP-02 / CC-82
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, SubcategoriaEgresoSeeder::class]);

        $this->administrador = User::where('email', 'admin@sgco-chayito.local')->firstOrFail();

        $this->socio = User::factory()->create();
        $this->socio->assignRole('socio');

        $this->contadora = User::factory()->create();
        $this->contadora->assignRole('contadora');

        $this->cajero = User::factory()->create();
        $this->cajero->assignRole('cajero');

        $this->mesero = User::factory()->create();
        $this->mesero->assignRole('mesero');

        $this->cocinero = User::factory()->create();
        $this->cocinero->assignRole('cocinero');

        $this->cocinera = User::factory()->create();
        $this->cocinera->assignRole('cocinera');
    }

    /**
     * Verifica que el administrador puede descargar el reporte consolidado en CSV con BOM y cabeceras.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – US-REP-02 / CC-79 / CC-82
     */
    public function test_administrador_descarga_reporte_consolidado_en_csv_con_cabeceras_y_bom(): void
    {
        $fechaDesde = now()->subDays(7)->toDateString();
        $fechaHasta = now()->subDays(1)->toDateString();

        Sanctum::actingAs($this->administrador);

        $response = $this->get("/api/reportes/exportar?fecha_desde={$fechaDesde}&fecha_hasta={$fechaHasta}&formato=csv")
            ->assertOk();

        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader(
            'Content-Disposition',
            "attachment; filename=\"reporte-consolidado_{$fechaDesde}_{$fechaHasta}.csv\"",
        );

        $contenido = $response->getContent();

        // Verifica que incluya la marca de orden de bytes (BOM UTF-8)
        $this->assertStringStartsWith("\xEF\xBB\xBF", $contenido);

        // Verifica presencia de las 6 secciones oficiales
        $this->assertStringContainsString('Resumen', $contenido);
        $this->assertStringContainsString('Egresos', $contenido);
        $this->assertStringContainsString('Gastos por subcategoría', $contenido);
        $this->assertStringContainsString('Compras', $contenido);
        $this->assertStringContainsString('Inventario', $contenido);
        $this->assertStringContainsString('Existencias por área', $contenido);
    }

    /**
     * Verifica que el administrador puede descargar el reporte consolidado en Excel XLSX con formato binario.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – US-REP-02 / CC-80 / CC-82
     */
    public function test_administrador_descarga_reporte_consolidado_en_xlsx_con_cabeceras_y_hojas(): void
    {
        $fechaDesde = now()->subDays(7)->toDateString();
        $fechaHasta = now()->subDays(1)->toDateString();

        Sanctum::actingAs($this->administrador);

        $response = $this->get("/api/reportes/exportar?fecha_desde={$fechaDesde}&fecha_hasta={$fechaHasta}&formato=xlsx")
            ->assertOk();

        $response->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
        $response->assertHeader(
            'Content-Disposition',
            "attachment; filename=\"reporte-consolidado_{$fechaDesde}_{$fechaHasta}.xlsx\"",
        );

        $contenido = $response->getContent();

        // Firma estándar de archivo ZIP/XLSX: PK\x03\x04
        $this->assertStringStartsWith("PK\x03\x04", $contenido);
    }

    /**
     * Verifica que el CSV consolida KPIs de resumen, egresos con desglose, gastos por subcategoría y compras con totales.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – US-REP-02 / CC-81 / CC-82
     */
    public function test_reporte_csv_consolida_resumen_financiero_egresos_y_compras_con_totales(): void
    {
        $fechaDesde = now()->subDays(5)->toDateString();
        $fechaHasta = now()->subDays(1)->toDateString();
        $diaComprobante = now()->subDays(3)->toDateString();

        // 1. Venta de $200.00
        $this->registrarVenta($this->administrador, 200, $diaComprobante);

        // 2. Egresos: Carnes $113.00 ($100 neto + $13 IVA) y Aves $56.50 ($50 neto + $6.50 IVA)
        $this->registrarEgreso($this->administrador, 113.00, 100.00, $diaComprobante, 'Carnes', 'FAC-001', 'Carnicería San José');
        $this->registrarEgreso($this->administrador, 56.50, 50.00, $diaComprobante, 'Aves', 'FAC-002', 'Pollo Rey');

        // 3. Compra de insumos $56.50 ($50 neto + $6.50 IVA)
        $this->registrarCompra($this->administrador, 56.50, 50.00, $diaComprobante);

        Sanctum::actingAs($this->administrador);

        $response = $this->get("/api/reportes/exportar?fecha_desde={$fechaDesde}&fecha_hasta={$fechaHasta}&formato=csv")
            ->assertOk();

        $contenido = $response->getContent();

        // Sección Resumen
        $this->assertStringContainsString('"Ingresos por ventas",200.00', $contenido);
        $this->assertStringContainsString('"Gastos netos",150.00', $contenido);
        $this->assertStringContainsString('"IVA crédito fiscal",19.50', $contenido);
        $this->assertStringContainsString('"Utilidad bruta",50.00', $contenido);
        $this->assertStringContainsString('"Margen bruto (%)",25.00', $contenido);
        $this->assertStringContainsString('"Ventas registradas",1', $contenido);
        $this->assertStringContainsString('"Egresos registrados",2', $contenido);

        // Sección Egresos: comprobantes y totales
        $this->assertStringContainsString('Carnicería San José', $contenido);
        $this->assertStringContainsString('Pollo Rey', $contenido);
        $this->assertStringContainsString('FAC-001', $contenido);
        $this->assertStringContainsString('FAC-002', $contenido);
        // Fila Total de Egresos: Monto total: 169.50, Costo neto: 150.00, IVA: 19.50
        $this->assertStringContainsString('Total,,,,,,169.50,150.00,19.50', $contenido);

        // Sección Gastos por Subcategoría: Carnes (66.67%) y Aves (33.33%)
        $this->assertStringContainsString('Carnes,1,100.00,66.67', $contenido);
        $this->assertStringContainsString('Aves,1,50.00,33.33', $contenido);

        // Sección Compras: detalle y total
        $this->assertStringContainsString("{$diaComprobante},56.50,50.00,6.50", $contenido);
        $this->assertStringContainsString('Total,56.50,50.00,6.50', $contenido);
    }

    /**
     * Verifica que el reporte incluye inventario de bodega con estados legibles y existencias por área operativa.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – US-REP-02 / CC-81 / CC-82
     */
    public function test_reporte_exporta_inventario_de_bodega_y_existencias_por_area_operativa(): void
    {
        $fechaDesde = now()->subDays(5)->toDateString();
        $fechaHasta = now()->subDays(1)->toDateString();

        // Insumos con estados diversos: Normal, Bajo y Agotado
        $arroz = $this->crearInsumo('Arroz Blanco', 'lb', 50.000, 10.000);   // Normal
        $aceite = $this->crearInsumo('Aceite Vegetal', 'lt', 3.500, 5.000);   // Bajo
        $azucar = $this->crearInsumo('Azúcar Morena', 'lb', 0.000, 5.000);    // Agotado

        // Existencias en áreas operativas
        $this->crearExistenciaArea('cocina', $arroz, 8.250);
        $this->crearExistenciaArea('barra', $azucar, 2.000);

        Sanctum::actingAs($this->administrador);

        $response = $this->get("/api/reportes/exportar?fecha_desde={$fechaDesde}&fecha_hasta={$fechaHasta}&formato=csv")
            ->assertOk();

        $contenido = $response->getContent();

        // Sección Inventario: formato a 3 decimales y estado evaluado
        $this->assertStringContainsString('"Arroz Blanco",lb,50.000,10.000,Normal', $contenido);
        $this->assertStringContainsString('"Aceite Vegetal",lt,3.500,5.000,Bajo', $contenido);
        $this->assertStringContainsString('"Azúcar Morena",lb,0.000,5.000,Agotado', $contenido);

        // Sección Existencias por Área: área capitalizada y stock a 3 decimales
        $this->assertStringContainsString('Cocina,"Arroz Blanco",lb,8.250', $contenido);
        $this->assertStringContainsString('Barra,"Azúcar Morena",lb,2.000', $contenido);
    }

    /**
     * Verifica que la exportación filtra comprobantes de egreso y compras dentro del rango y excluye los externos.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – US-REP-02 / CC-79 / CC-81 / CC-82
     */
    public function test_exportacion_filtra_movimientos_exclusivamente_dentro_del_periodo(): void
    {
        $fechaDesde = now()->subDays(6)->toDateString();
        $fechaHasta = now()->subDays(2)->toDateString();
        $fechaDentro = now()->subDays(4)->toDateString();
        $fechaAntes = now()->subDays(8)->toDateString();
        $fechaDespues = now()->subDay()->toDateString();

        // Dentro del periodo
        $this->registrarEgreso($this->administrador, 113.00, 100.00, $fechaDentro, 'Carnes', 'EG-DENTRO', 'Proveedor Dentro');
        $this->registrarCompra($this->administrador, 113.00, 100.00, $fechaDentro);

        // Fuera del periodo (antes y después)
        $this->registrarEgreso($this->administrador, 226.00, 200.00, $fechaAntes, 'Carnes', 'EG-ANTES', 'Proveedor Antes');
        $this->registrarEgreso($this->administrador, 339.00, 300.00, $fechaDespues, 'Carnes', 'EG-DESPUES', 'Proveedor Despues');
        $this->registrarCompra($this->administrador, 226.00, 200.00, $fechaAntes);
        $this->registrarCompra($this->administrador, 339.00, 300.00, $fechaDespues);

        Sanctum::actingAs($this->administrador);

        $response = $this->get("/api/reportes/exportar?fecha_desde={$fechaDesde}&fecha_hasta={$fechaHasta}&formato=csv")
            ->assertOk();

        $contenido = $response->getContent();

        // Incluidos
        $this->assertStringContainsString('Proveedor Dentro', $contenido);
        $this->assertStringContainsString('EG-DENTRO', $contenido);

        // Excluidos
        $this->assertStringNotContainsString('Proveedor Antes', $contenido);
        $this->assertStringNotContainsString('EG-ANTES', $contenido);
        $this->assertStringNotContainsString('Proveedor Despues', $contenido);
        $this->assertStringNotContainsString('EG-DESPUES', $contenido);

        // Totales de egresos deben reflejar solo los $100.00 de costo neto
        $this->assertStringContainsString('Total,,,,,,113.00,100.00,13.00', $contenido);
        // Totales de compras deben reflejar solo los $100.00 de costo neto
        $this->assertStringContainsString('Total,113.00,100.00,13.00', $contenido);
    }

    /**
     * Verifica que un periodo sin movimientos genera un reporte válido con totales en cero sin excepciones.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – US-REP-02 / CC-82
     */
    public function test_exportacion_con_periodo_vacio_genera_archivo_valido_con_totales_cero(): void
    {
        $fechaDesde = now()->subDays(4)->toDateString();
        $fechaHasta = now()->subDays(2)->toDateString();

        Sanctum::actingAs($this->administrador);

        // 1. CSV
        $responseCsv = $this->get("/api/reportes/exportar?fecha_desde={$fechaDesde}&fecha_hasta={$fechaHasta}&formato=csv")
            ->assertOk();

        $contenidoCsv = $responseCsv->getContent();
        $this->assertStringContainsString('"Ingresos por ventas",0.00', $contenidoCsv);
        $this->assertStringContainsString('"Gastos netos",0.00', $contenidoCsv);
        $this->assertStringContainsString('"IVA crédito fiscal",0.00', $contenidoCsv);
        $this->assertStringContainsString('"Utilidad bruta",0.00', $contenidoCsv);
        $this->assertStringContainsString('"Margen bruto (%)",0.00', $contenidoCsv);
        $this->assertStringContainsString('Total,,,,,,0.00,0.00,0.00', $contenidoCsv);
        $this->assertStringContainsString('Total,0.00,0.00,0.00', $contenidoCsv);

        // 2. XLSX
        $this->get("/api/reportes/exportar?fecha_desde={$fechaDesde}&fecha_hasta={$fechaHasta}&formato=xlsx")
            ->assertOk();
    }

    /**
     * Verifica que el rol Socio está autorizado a descargar el reporte consolidado.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – US-REP-02 / CC-79 / CC-80 / CC-82
     */
    public function test_socio_puede_exportar_reporte_consolidado(): void
    {
        $fechaDesde = now()->subDays(3)->toDateString();
        $fechaHasta = now()->subDays(1)->toDateString();

        Sanctum::actingAs($this->socio);

        $this->get("/api/reportes/exportar?fecha_desde={$fechaDesde}&fecha_hasta={$fechaHasta}&formato=csv")
            ->assertOk();

        $this->get("/api/reportes/exportar?fecha_desde={$fechaDesde}&fecha_hasta={$fechaHasta}&formato=xlsx")
            ->assertOk();
    }

    /**
     * Verifica que el rol Contadora está autorizado a descargar el reporte consolidado.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – US-REP-02 / CC-79 / CC-80 / CC-82
     */
    public function test_contadora_puede_exportar_reporte_consolidado(): void
    {
        $fechaDesde = now()->subDays(3)->toDateString();
        $fechaHasta = now()->subDays(1)->toDateString();

        Sanctum::actingAs($this->contadora);

        $this->get("/api/reportes/exportar?fecha_desde={$fechaDesde}&fecha_hasta={$fechaHasta}&formato=csv")
            ->assertOk();

        $this->get("/api/reportes/exportar?fecha_desde={$fechaDesde}&fecha_hasta={$fechaHasta}&formato=xlsx")
            ->assertOk();
    }

    /**
     * Verifica que los roles operativos (cajero, mesero, cocinero, cocinera) reciben 403 Forbidden.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – US-REP-02 / CC-82
     */
    public function test_roles_operativos_cajero_mesero_cocinero_no_pueden_exportar(): void
    {
        $fechaDesde = now()->subDays(3)->toDateString();
        $fechaHasta = now()->subDays(1)->toDateString();
        $url = "/api/reportes/exportar?fecha_desde={$fechaDesde}&fecha_hasta={$fechaHasta}&formato=csv";

        Sanctum::actingAs($this->cajero);
        $this->get($url)->assertForbidden();

        Sanctum::actingAs($this->mesero);
        $this->get($url)->assertForbidden();

        Sanctum::actingAs($this->cocinero);
        $this->get($url)->assertForbidden();

        Sanctum::actingAs($this->cocinera);
        $this->get($url)->assertForbidden();
    }

    /**
     * Verifica que las peticiones no autenticadas son rechazadas con 401 Unauthorized.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – US-REP-02 / CC-82
     */
    public function test_exportacion_requiere_autenticacion(): void
    {
        $this->get('/api/reportes/exportar?fecha_desde=2026-10-01&fecha_hasta=2026-10-15&formato=csv')
            ->assertUnauthorized();
    }

    /**
     * Verifica que se rechaza la exportación si falta la fecha inicial del período.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – US-REP-02 / CC-79 / CC-82
     */
    public function test_rechaza_exportacion_si_falta_fecha_desde(): void
    {
        $hoy = now()->toDateString();
        Sanctum::actingAs($this->administrador);

        $this->getJson("/api/reportes/exportar?fecha_hasta={$hoy}&formato=csv")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_desde' => 'Indica la fecha inicial del período.']);
    }

    /**
     * Verifica que se rechaza la exportación si falta la fecha final del período.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – US-REP-02 / CC-79 / CC-82
     */
    public function test_rechaza_exportacion_si_falta_fecha_hasta(): void
    {
        $hoy = now()->toDateString();
        Sanctum::actingAs($this->administrador);

        $this->getJson("/api/reportes/exportar?fecha_desde={$hoy}&formato=csv")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_hasta' => 'Indica la fecha final del período.']);
    }

    /**
     * Verifica que se rechaza la exportación si falta el formato del archivo.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – US-REP-02 / CC-79 / CC-80 / CC-82
     */
    public function test_rechaza_exportacion_si_falta_formato(): void
    {
        $hoy = now()->toDateString();
        Sanctum::actingAs($this->administrador);

        $this->getJson("/api/reportes/exportar?fecha_desde={$hoy}&fecha_hasta={$hoy}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['formato' => 'Indica el formato del archivo.']);
    }

    /**
     * Verifica que se rechaza un formato no soportado (ej. pdf o word).
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – US-REP-02 / CC-80 / CC-82
     */
    public function test_rechaza_exportacion_con_formato_no_disponible(): void
    {
        $hoy = now()->toDateString();
        Sanctum::actingAs($this->administrador);

        $this->getJson("/api/reportes/exportar?fecha_desde={$hoy}&fecha_hasta={$hoy}&formato=pdf")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['formato' => 'El formato solicitado no está disponible.']);
    }

    /**
     * Verifica que se rechazan fechas con formato no válido.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – US-REP-02 / CC-79 / CC-82
     */
    public function test_rechaza_exportacion_con_fechas_con_formato_invalido(): void
    {
        Sanctum::actingAs($this->administrador);

        $this->getJson('/api/reportes/exportar?fecha_desde=fecha-invalida&fecha_hasta=2026-10-20&formato=csv')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_desde' => 'La fecha inicial debe tener un formato válido.']);

        $this->getJson('/api/reportes/exportar?fecha_desde=2026-10-01&fecha_hasta=otra-cosa&formato=csv')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_hasta' => 'La fecha final debe tener un formato válido.']);
    }

    /**
     * Verifica que se rechaza un rango donde fecha_desde es posterior a fecha_hasta.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – US-REP-02 / CC-79 / CC-82
     */
    public function test_rechaza_exportacion_con_rango_invertido(): void
    {
        $hoy = now()->toDateString();
        $ayer = now()->subDay()->toDateString();

        Sanctum::actingAs($this->administrador);

        $this->getJson("/api/reportes/exportar?fecha_desde={$hoy}&fecha_hasta={$ayer}&formato=csv")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_hasta' => 'La fecha final debe ser igual o posterior a la inicial.']);
    }

    /**
     * Verifica que se rechazan fechas futuras tanto en fecha_desde como en fecha_hasta.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – US-REP-02 / CC-79 / CC-82
     */
    public function test_rechaza_exportacion_con_fechas_futuras(): void
    {
        $hoy = now()->toDateString();
        $manana = now()->addDay()->toDateString();

        Sanctum::actingAs($this->administrador);

        $this->getJson("/api/reportes/exportar?fecha_desde={$manana}&fecha_hasta={$manana}&formato=csv")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_desde' => 'La fecha inicial no puede ser futura.']);

        $this->getJson("/api/reportes/exportar?fecha_desde={$hoy}&fecha_hasta={$manana}&formato=csv")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_hasta' => 'La fecha final no puede ser futura.']);
    }

    /**
     * Verifica que el CSV exportado conserva correctamente caracteres UTF-8 como tildes y eñes sin corrupción.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – US-REP-02 / CC-79 / CC-81 / CC-82
     */
    public function test_exportacion_csv_conserva_caracteres_utf8_con_tildes_y_enes(): void
    {
        $fechaDesde = now()->subDays(3)->toDateString();
        $fechaHasta = now()->subDays(1)->toDateString();

        $this->registrarEgreso(
            $this->administrador,
            113.00,
            100.00,
            $fechaDesde,
            'Lácteos',
            'FAC-TILDES-01',
            'Compañía de Lácteos & Piña S.A. de C.V.',
        );

        $this->crearInsumo('Chicharrón de cerdo', 'lb', 15.500, 5.000);

        Sanctum::actingAs($this->administrador);

        $response = $this->get("/api/reportes/exportar?fecha_desde={$fechaDesde}&fecha_hasta={$fechaHasta}&formato=csv")
            ->assertOk();

        $contenido = $response->getContent();

        $this->assertStringContainsString('Compañía de Lácteos & Piña S.A. de C.V.', $contenido);
        $this->assertStringContainsString('Lácteos', $contenido);
        $this->assertStringContainsString('Chicharrón de cerdo', $contenido);
    }

    /**
     * Verifica que el XLSX generado puede ser leído y contiene exactamente las seis hojas estructuradas.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – US-REP-02 / CC-80 / CC-81 / CC-82
     */
    public function test_exportacion_xlsx_es_valido_y_contiene_las_seis_hojas_con_datos(): void
    {
        $fechaDesde = now()->subDays(5)->toDateString();
        $fechaHasta = now()->subDays(1)->toDateString();

        $this->registrarEgreso($this->administrador, 113.00, 100.00, $fechaDesde, 'Carnes');
        $this->registrarCompra($this->administrador, 56.50, 50.00, $fechaDesde);
        $insumo = $this->crearInsumo('Frijol Rojo', 'lb', 20.000, 5.000);
        $this->crearExistenciaArea('cocina', $insumo, 5.000);

        Sanctum::actingAs($this->administrador);

        $response = $this->get("/api/reportes/exportar?fecha_desde={$fechaDesde}&fecha_hasta={$fechaHasta}&formato=xlsx")
            ->assertOk();

        $contenido = $response->getContent();
        $rutaTemporal = tempnam(sys_get_temp_dir(), 'test_xlsx_');
        file_put_contents($rutaTemporal, $contenido);

        $reader = new Reader;
        $reader->open($rutaTemporal);

        $nombresHojas = [];
        $filasPorHoja = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $nombreHoja = $sheet->getName();
            $nombresHojas[] = $nombreHoja;
            $contadorFilas = 0;

            foreach ($sheet->getRowIterator() as $row) {
                $contadorFilas++;
            }
            $filasPorHoja[$nombreHoja] = $contadorFilas;
        }

        $reader->close();
        unlink($rutaTemporal);

        // Verifica orden y nombres exactos de las seis hojas
        $this->assertSame([
            'Resumen',
            'Egresos',
            'Gastos por subcategoría',
            'Compras',
            'Inventario',
            'Existencias por área',
        ], $nombresHojas);

        // Todas las hojas contienen al menos sus encabezados
        $this->assertGreaterThanOrEqual(1, $filasPorHoja['Resumen']);
        $this->assertGreaterThanOrEqual(2, $filasPorHoja['Egresos']); // encabezado + egreso + total
        $this->assertGreaterThanOrEqual(2, $filasPorHoja['Gastos por subcategoría']);
        $this->assertGreaterThanOrEqual(2, $filasPorHoja['Compras']);
        $this->assertGreaterThanOrEqual(2, $filasPorHoja['Inventario']);
        $this->assertGreaterThanOrEqual(2, $filasPorHoja['Existencias por área']);
    }

    /**
     * Verifica los métodos auxiliares de ExportacionReporteService (nombre de archivo, MIME y estructura de secciones).
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – US-REP-02 / CC-79 / CC-80 / CC-82
     */
    public function test_servicio_exportacion_metodos_auxiliares_de_soporte(): void
    {
        /** @var ExportacionReporteService $servicio */
        $servicio = app(ExportacionReporteService::class);

        // 1. Nombres de archivo
        $this->assertSame(
            'reporte-consolidado_2026-10-01_2026-10-15.csv',
            $servicio->nombreArchivo('2026-10-01', '2026-10-15', 'csv'),
        );
        $this->assertSame(
            'reporte-consolidado_2026-10-01_2026-10-15.xlsx',
            $servicio->nombreArchivo('2026-10-01', '2026-10-15', 'xlsx'),
        );

        // 2. Tipos MIME
        $this->assertSame('text/csv; charset=UTF-8', $servicio->tipoMime('csv'));
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $servicio->tipoMime('xlsx'));

        // 3. Estructura de secciones
        $secciones = $servicio->obtenerSecciones('2026-10-01', '2026-10-15');
        $this->assertCount(6, $secciones);

        $titulosEsperados = ['Resumen', 'Egresos', 'Gastos por subcategoría', 'Compras', 'Inventario', 'Existencias por área'];
        $this->assertSame($titulosEsperados, array_column($secciones, 'titulo'));

        foreach ($secciones as $seccion) {
            $this->assertArrayHasKey('titulo', $seccion);
            $this->assertArrayHasKey('columnas', $seccion);
            $this->assertArrayHasKey('filas', $seccion);
            $this->assertArrayHasKey('total', $seccion);
        }
    }

    /**
     * Registra una venta pagada vinculada a una comanda en la fecha requerida.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – US-REP-02 / CC-82
     */
    private function registrarVenta(User $usuario, float $total, string $fecha): void
    {
        $momento = Carbon::parse($fecha)->setTime(12, 0);

        $comanda = Comanda::create([
            'usuario_id' => $usuario->id,
            'canal' => 'para_llevar',
            'estado' => 'pagada',
            'subtotal' => $total,
        ]);

        $platillo = Platillo::create([
            'nombre' => "Platillo Export {$comanda->id}",
            'precio_unitario' => $total,
            'disponible' => true,
        ]);

        ComandaDetalle::create([
            'comanda_id' => $comanda->id,
            'platillo_id' => $platillo->id,
            'cantidad' => 1,
            'precio_unitario' => $total,
        ]);

        $venta = Venta::create([
            'comanda_id' => $comanda->id,
            'usuario_id' => $usuario->id,
            'total' => $total,
            'monto_recibido' => $total,
            'cambio' => 0,
            'metodo_pago' => 'efectivo',
        ]);

        $comanda->forceFill(['created_at' => $momento, 'updated_at' => $momento])->save();
        $venta->forceFill(['created_at' => $momento, 'updated_at' => $momento])->save();
    }

    /**
     * Registra un egreso categorizado con su desglose de IVA crédito fiscal.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – US-REP-02 / CC-82
     */
    private function registrarEgreso(
        User $usuario,
        float $montoTotal,
        float $costoNeto,
        string $fecha,
        string $subcategoria = 'Abarrotes',
        string $comprobante = 'PRUEBA-001',
        string $proveedor = 'Proveedor General',
    ): Egreso {
        $numero = Egreso::count() + 1;
        $subcategoriaId = SubcategoriaEgreso::where('nombre', $subcategoria)->value('id')
            ?? SubcategoriaEgreso::firstOrCreate(['nombre' => $subcategoria])->id;

        return Egreso::create([
            'correlativo' => sprintf('EG-%06d', $numero),
            'subcategoria_egreso_id' => $subcategoriaId,
            'usuario_id' => $usuario->id,
            'proveedor' => $proveedor,
            'numero_comprobante' => $comprobante,
            'fecha_comprobante' => $fecha,
            'monto_total' => $montoTotal,
            'costo_neto' => $costoNeto,
            'iva_credito_fiscal' => round($montoTotal - $costoNeto, 2),
            'descripcion' => 'Egreso de prueba para exportación',
        ]);
    }

    /**
     * Registra una compra en la calculadora de IVA.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – US-REP-02 / CC-82
     */
    private function registrarCompra(User $usuario, float $montoTotal, float $costoNeto, string $fecha): Compra
    {
        return Compra::create([
            'usuario_id' => $usuario->id,
            'fecha_compra' => $fecha,
            'monto_total' => $montoTotal,
            'costo_neto' => $costoNeto,
            'iva_credito_fiscal' => round($montoTotal - $costoNeto, 2),
        ]);
    }

    /**
     * Crea un insumo en bodega.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – US-REP-02 / CC-82
     */
    private function crearInsumo(string $nombre, string $unidad, float $stockActual, float $stockMinimo): Insumo
    {
        return Insumo::create([
            'nombre' => $nombre,
            'unidad_medida' => $unidad,
            'stock_actual' => $stockActual,
            'stock_minimo' => $stockMinimo,
        ]);
    }

    /**
     * Registra existencias de un insumo dentro de un área operativa.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – US-REP-02 / CC-82
     */
    private function crearExistenciaArea(string $area, Insumo $insumo, float $stock): ExistenciaAreaOperativa
    {
        return ExistenciaAreaOperativa::create([
            'area' => $area,
            'insumo_id' => $insumo->id,
            'stock_actual' => $stock,
        ]);
    }
}

