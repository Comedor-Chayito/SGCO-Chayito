<?php

namespace Tests\Feature\Api;

use App\Models\Auditoria;
use App\Models\Platillo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Pruebas de la auditoría de cambios de precio y disponibilidad de platillos (US-ADM-03 / CC-109).
 * Verifica que cada cambio real deje exactamente un registro con el valor anterior, el nuevo
 * y el usuario que lo hizo, y que los cambios sin efecto no ensucien la bitácora.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-17
 * @módulo Administración – US-ADM-03 / CC-109
 */
class AuditoriaPlatilloTest extends TestCase
{
    use RefreshDatabase;

    protected User $administrador;

    /**
     * Crea los roles, inicia sesión como administrador y deja la bitácora vacía.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-17
     * @módulo Administración – US-ADM-03 / CC-109
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['administrador', 'socio', 'mesero'] as $rol) {
            Role::findOrCreate($rol, 'web');
        }

        $this->administrador = User::factory()->create(['activo' => true]);
        $this->administrador->assignRole('administrador');
        Sanctum::actingAs($this->administrador);

        // Crear usuarios y asignar roles también se audita; se limpia para contar solo lo de platillos
        Auditoria::query()->delete();
    }

    /**
     * Crea un platillo de prueba disponible.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-17
     * @módulo Administración – US-ADM-03 / CC-109
     *
     * @param  string $precio Precio unitario inicial.
     * @return Platillo
     */
    private function crearPlatillo(string $precio = '2.50'): Platillo
    {
        return Platillo::create([
            'nombre'          => 'Pupusa revuelta',
            'descripcion'     => 'Pupusa de prueba',
            'precio_unitario' => $precio,
            'disponible'      => true,
        ]);
    }

    /**
     * Devuelve los registros de la bitácora de un platillo para una acción.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-17
     * @módulo Administración – US-ADM-03 / CC-109
     *
     * @param  Platillo $platillo Platillo auditado.
     * @param  string   $accion   Acción registrada.
     * @return \Illuminate\Database\Eloquent\Collection<int, Auditoria>
     */
    private function auditoriasDe(Platillo $platillo, string $accion)
    {
        return Auditoria::query()
            ->where('entidad_type', $platillo->getMorphClass())
            ->where('entidad_id', $platillo->id)
            ->where('accion', $accion)
            ->get();
    }

    /**
     * Verifica que un cambio de precio deje un registro con el precio anterior, el nuevo y el usuario.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-17
     * @módulo Administración – US-ADM-03 / CC-109
     *
     * @return void
     */
    public function test_cambio_de_precio_queda_en_la_bitacora(): void
    {
        $platillo = $this->crearPlatillo('2.50');

        $this->putJson("/api/platillos/{$platillo->id}", [
            'nombre'          => 'Pupusa revuelta',
            'precio_unitario' => 3.00,
        ])->assertOk();

        $registros = $this->auditoriasDe($platillo, 'precio_cambiado');

        $this->assertCount(1, $registros);
        $this->assertSame($this->administrador->id, $registros->first()->usuario_id);
        $this->assertSame(['precio_unitario' => '2.50'], $registros->first()->datos_anteriores);
        $this->assertSame(['precio_unitario' => '3.00'], $registros->first()->datos_nuevos);
    }

    /**
     * Verifica que editar solo el nombre o la descripción no registre un cambio de precio.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-17
     * @módulo Administración – US-ADM-03 / CC-109
     *
     * @return void
     */
    public function test_editar_sin_cambiar_precio_no_registra_auditoria(): void
    {
        $platillo = $this->crearPlatillo('2.50');

        $this->putJson("/api/platillos/{$platillo->id}", [
            'nombre'          => 'Pupusa de queso',
            'descripcion'     => 'Nueva descripción',
            'precio_unitario' => 2.50,
        ])->assertOk();

        $this->assertCount(0, $this->auditoriasDe($platillo, 'precio_cambiado'));
    }

    /**
     * Verifica que el mismo precio escrito con otro formato ("2.5" contra "2.50") no cuente como cambio.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-17
     * @módulo Administración – US-ADM-03 / CC-109
     *
     * @return void
     */
    public function test_mismo_precio_con_otro_formato_no_registra_auditoria(): void
    {
        $platillo = $this->crearPlatillo('2.50');

        $this->putJson("/api/platillos/{$platillo->id}", [
            'nombre'          => 'Pupusa revuelta',
            'precio_unitario' => '2.5',
        ])->assertOk();

        $this->assertCount(0, $this->auditoriasDe($platillo, 'precio_cambiado'));
    }

    /**
     * Verifica que una edición rechazada por validación no deje registro ni cambie el precio.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-17
     * @módulo Administración – US-ADM-03 / CC-109
     *
     * @return void
     */
    public function test_precio_invalido_no_registra_auditoria(): void
    {
        $platillo = $this->crearPlatillo('2.50');

        $this->putJson("/api/platillos/{$platillo->id}", [
            'nombre'          => 'Pupusa revuelta',
            'precio_unitario' => 0,
        ])->assertStatus(422);

        $this->assertCount(0, $this->auditoriasDe($platillo, 'precio_cambiado'));
        $this->assertSame('2.50', $platillo->refresh()->precio_unitario);
    }

    /**
     * Verifica que desactivar y reactivar un platillo dejen un registro cada uno, con su valor anterior y nuevo.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-17
     * @módulo Administración – US-ADM-03 / CC-109
     *
     * @return void
     */
    public function test_desactivar_y_reactivar_quedan_en_la_bitacora(): void
    {
        $platillo = $this->crearPlatillo();

        $this->patchJson("/api/platillos/{$platillo->id}/disponibilidad", ['disponible' => false])->assertOk();
        $this->patchJson("/api/platillos/{$platillo->id}/disponibilidad", ['disponible' => true])->assertOk();

        $registros = $this->auditoriasDe($platillo, 'disponibilidad_cambiada')->sortBy('id')->values();

        $this->assertCount(2, $registros);
        $this->assertSame(['disponible' => true], $registros[0]->datos_anteriores);
        $this->assertSame(['disponible' => false], $registros[0]->datos_nuevos);
        $this->assertSame(['disponible' => false], $registros[1]->datos_anteriores);
        $this->assertSame(['disponible' => true], $registros[1]->datos_nuevos);
        $this->assertSame($this->administrador->id, $registros[1]->usuario_id);
    }

    /**
     * Verifica que enviar la misma disponibilidad que ya tiene el platillo no registre nada.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-17
     * @módulo Administración – US-ADM-03 / CC-109
     *
     * @return void
     */
    public function test_misma_disponibilidad_no_registra_auditoria(): void
    {
        $platillo = $this->crearPlatillo();

        $this->patchJson("/api/platillos/{$platillo->id}/disponibilidad", ['disponible' => true])->assertOk();

        $this->assertCount(0, $this->auditoriasDe($platillo, 'disponibilidad_cambiada'));
    }

    /**
     * Verifica que un rol sin acceso al catálogo reciba 403 y no deje registro en la bitácora.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-17
     * @módulo Administración – US-ADM-03 / CC-109
     *
     * @return void
     */
    public function test_rol_sin_permiso_no_deja_registro(): void
    {
        $platillo = $this->crearPlatillo('2.50');
        $mesero = User::factory()->create(['activo' => true]);
        $mesero->assignRole('mesero');
        Auditoria::query()->delete();
        Sanctum::actingAs($mesero);

        $this->putJson("/api/platillos/{$platillo->id}", [
            'nombre'          => 'Pupusa revuelta',
            'precio_unitario' => 9.99,
        ])->assertForbidden();

        $this->patchJson("/api/platillos/{$platillo->id}/disponibilidad", ['disponible' => false])
            ->assertForbidden();

        $this->assertSame(0, Auditoria::count());
    }
}
