<?php

namespace Tests\Feature\Crud;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Scripts de captura (5 corridas) y edición (5 corridas) de la pantalla de usuarios (CC-177).
 * Todos los nombres y correos son ficticios.
 *
 * @autor  Jeferson De La Cruz
 *
 * @fecha  2026-11-27
 *
 * @módulo Administración – RF-ADM-008 / US-ADM-01 / CC-177
 */
class UsuarioCrudTest extends CrudTestCase
{
    /**
     * Corridas de captura: nombre, correo y rol del usuario nuevo.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Administración – RF-ADM-008 / CC-177
     *
     * @return array<string, array{string, string, string}>
     */
    public static function capturas(): array
    {
        return [
            'cap-usu-01 cajero' => ['Usuario Prueba Uno', 'prueba1@ejemplo.test', 'cajero'],
            'cap-usu-02 mesero' => ['Usuario Prueba Dos', 'prueba2@ejemplo.test', 'mesero'],
            'cap-usu-03 cocinero' => ['Usuario Prueba Tres', 'prueba3@ejemplo.test', 'cocinero'],
            'cap-usu-04 contadora' => ['Usuario Prueba Cuatro', 'prueba4@ejemplo.test', 'contadora'],
            'cap-usu-05 correo en mayúsculas' => ['Usuario Prueba Cinco', 'PRUEBA5@Ejemplo.test', 'socio'],
        ];
    }

    /**
     * Corridas de edición: campos a modificar de un usuario existente.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Administración – RF-ADM-008 / CC-177
     *
     * @return array<string, array{array<string, mixed>}>
     */
    public static function ediciones(): array
    {
        return [
            'edi-usu-01 cambiar nombre' => [['name' => 'Usuario Renombrado']],
            'edi-usu-02 cambiar correo' => [['email' => 'nuevo.correo@ejemplo.test']],
            'edi-usu-03 cambiar rol' => [['rol' => 'cajero']],
            'edi-usu-04 cambiar contraseña' => [['password' => 'ClaveNueva2026']],
            'edi-usu-05 desactivar' => [['activo' => false]],
        ];
    }

    /**
     * Captura un usuario y verifica correo normalizado, contraseña cifrada y rol asignado.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Administración – RF-ADM-008 / US-ADM-02 / CC-177
     *
     * @param  string  $nombre  Nombre del usuario.
     * @param  string  $correo  Correo de acceso.
     * @param  string  $rol  Rol asignado.
     * @return void
     */
    #[DataProvider('capturas')]
    public function test_captura_registra_usuario(string $nombre, string $correo, string $rol): void
    {
        $respuesta = $this->postJson('/api/usuarios', [
            'name' => $nombre,
            'email' => $correo,
            'password' => 'ClaveSegura2026',
            'rol' => $rol,
        ]);

        $this->assertContratoOk($respuesta, 201);
        $usuario = User::where('email', strtolower($correo))->firstOrFail();
        $this->assertSame($nombre, $usuario->name);
        $this->assertTrue(Hash::check('ClaveSegura2026', $usuario->password));
        $this->assertTrue($usuario->hasRole($rol));
    }

    /**
     * Edita un usuario existente y verifica que el cambio quede guardado.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Administración – RF-ADM-008 / CC-177
     *
     * @param  array<string, mixed>  $datos  Campos a modificar.
     * @return void
     */
    #[DataProvider('ediciones')]
    public function test_edicion_actualiza_usuario(array $datos): void
    {
        $usuario = User::factory()->create(['name' => 'Usuario Original', 'email' => 'original@ejemplo.test']);
        $usuario->assignRole('mesero');

        $this->assertContratoOk($this->putJson("/api/usuarios/{$usuario->id}", $datos), 200);

        $usuario->refresh();
        match (array_key_first($datos)) {
            'password' => $this->assertTrue(Hash::check($datos['password'], $usuario->password)),
            'rol' => $this->assertSame(['cajero'], $usuario->getRoleNames()->all()),
            'activo' => $this->assertFalse((bool) $usuario->activo),
            default => $this->assertSame(current($datos), $usuario->{array_key_first($datos)}),
        };
    }
}
