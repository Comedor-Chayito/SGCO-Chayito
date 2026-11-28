<?php

namespace Tests\Unit\Administracion;

use App\Models\Auditoria;
use App\Models\Comanda;
use App\Models\Insumo;
use App\Models\Platillo;
use App\Models\Receta;
use App\Models\User;
use App\Services\PlatilloService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pruebas unitarias del servicio del catálogo de platillos (Cuadrante 1 – CC-143).
 *
 * Caja negra: 4 altas de platillo con distintas combinaciones de datos.
 * Caja blanca: 3 ejecuciones sobre la comparación de precio para auditoría y los bloqueos al eliminar.
 *
 * @autor  Jeferson De La Cruz
 *
 * @fecha  2026-11-27
 *
 * @módulo Administración – RF-ADM-008 / US-ADM-03 / CC-143
 */
class CatalogoPlatilloServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlatilloService $servicio;

    private User $administrador;

    /**
     * Resuelve el servicio y autentica al administrador, actor de la bitácora.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Administración – RF-ADM-008 / CC-143
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->servicio = app(PlatilloService::class);
        $this->administrador = User::factory()->create();
        $this->actingAs($this->administrador);
    }

    /**
     * Casos de caja negra: datos de entrada y [nombre, descripción, precio, disponible] esperados.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Administración – RF-ADM-008 / CC-143
     *
     * @return array<string, array{array<string, mixed>, array{string, ?string, string, bool}}>
     */
    public static function casosCajaNegra(): array
    {
        return [
            'cn-pla-01 nombre con espacios se recorta' => [
                ['nombre' => '  Pupusa revuelta ', 'precio_unitario' => 0.75],
                ['Pupusa revuelta', null, '0.75', true],
            ],
            'cn-pla-02 descripción se recorta' => [
                ['nombre' => 'Sopa de res', 'descripcion' => '  Con verduras ', 'precio_unitario' => 3.50],
                ['Sopa de res', 'Con verduras', '3.50', true],
            ],
            'cn-pla-03 alta no disponible' => [
                ['nombre' => 'Tamal de elote', 'precio_unitario' => 1.00, 'disponible' => false],
                ['Tamal de elote', null, '1.00', false],
            ],
            'cn-pla-04 precio con un decimal' => [
                ['nombre' => 'Plato típico', 'precio_unitario' => 12.5],
                ['Plato típico', null, '12.50', true],
            ],
        ];
    }

    /**
     * Verifica que el alta normalice textos, conserve el precio y quede disponible por defecto.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Administración – RF-ADM-008 / CC-143
     *
     * @param  array<string, mixed>  $datos  Datos validados del platillo.
     * @param  array{string, ?string, string, bool}  $esperado  Nombre, descripción, precio y disponibilidad.
     * @return void
     */
    #[DataProvider('casosCajaNegra')]
    public function test_caja_negra_crea_platillo(array $datos, array $esperado): void
    {
        $platillo = $this->servicio->crear($datos);

        $this->assertSame(
            $esperado,
            [$platillo->nombre, $platillo->descripcion, $platillo->precio_unitario, (bool) $platillo->disponible]
        );
    }

    /**
     * CB-PLA-01: rama de comparación de precio; "4.5" y 4.50 son el mismo precio y no se audita cambio.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Administración – RF-ADM-008 / CC-109 / CC-143
     *
     * @return void
     */
    public function test_caja_blanca_precio_igual_en_otro_formato_no_se_audita(): void
    {
        $platillo = Platillo::create(['nombre' => 'Pollo frito', 'precio_unitario' => 4.50]);

        $this->servicio->actualizar($platillo, ['nombre' => 'Pollo frito', 'precio_unitario' => '4.5']);

        $this->assertSame(0, Auditoria::where('accion', 'precio_cambiado')->count());
    }

    /**
     * CB-PLA-02: rama de historial de ventas; un platillo con comandas no se elimina.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Administración – RF-ADM-008 / CC-143
     *
     * @return void
     */
    public function test_caja_blanca_platillo_con_comandas_no_se_elimina(): void
    {
        $platillo = Platillo::create(['nombre' => 'Carne asada', 'precio_unitario' => 5.00]);
        $comanda = Comanda::create([
            'usuario_id' => $this->administrador->id,
            'canal' => 'para_llevar',
            'estado' => 'pagada',
            'subtotal' => 5.00,
        ]);
        $comanda->detalles()->create(['platillo_id' => $platillo->id, 'cantidad' => 1, 'precio_unitario' => 5.00]);

        $this->assertEliminarFalla($platillo);
    }

    /**
     * CB-PLA-03: rama de receta vinculada; un platillo con receta no se elimina.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Administración – RF-ADM-008 / CC-143
     *
     * @return void
     */
    public function test_caja_blanca_platillo_con_receta_no_se_elimina(): void
    {
        $platillo = Platillo::create(['nombre' => 'Arroz frito', 'precio_unitario' => 2.00]);
        $insumo = Insumo::create(['nombre' => 'Arroz', 'unidad_medida' => 'kg', 'stock_actual' => 1]);
        Receta::create([
            'platillo_id' => $platillo->id,
            'insumo_id' => $insumo->id,
            'cantidad_por_porcion' => 100,
            'unidad_consumo' => 'g',
        ]);

        $this->assertEliminarFalla($platillo);
    }

    /**
     * Afirma que eliminar el platillo lanza ValidationException y el registro se conserva.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Administración – RF-ADM-008 / CC-143
     *
     * @param  Platillo  $platillo  Platillo que no debe eliminarse.
     * @return void
     */
    private function assertEliminarFalla(Platillo $platillo): void
    {
        try {
            $this->servicio->eliminar($platillo);
            $this->fail('Se esperaba ValidationException al eliminar.');
        } catch (ValidationException $excepcion) {
            $this->assertArrayHasKey('platillo', $excepcion->errors());
        }

        $this->assertDatabaseHas('platillos', ['id' => $platillo->id]);
    }
}
