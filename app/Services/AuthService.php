<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    private const NOMBRE_TOKEN = 'token-sesion';

    /**
     * Valida las credenciales y genera un token de sesión seguro (Sanctum) para
     * que el SPA lo use como Bearer token en las siguientes peticiones. Devuelve
     * siempre el mismo mensaje de error ante correo o contraseña incorrectos,
     * para no revelar si el correo existe.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-18
     * @módulo Administración y Seguridad – CC-91 / US-ADM-02
     *
     * @param  array $credenciales Datos ya validados: email, password.
     * @return array{usuario: User, token: string} Usuario autenticado y token en texto plano.
     */
    public function iniciarSesion(array $credenciales): array
    {
        $usuario = User::where('email', $credenciales['email'])->first();

        if (! $usuario || ! Hash::check($credenciales['password'], $usuario->password)) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no coinciden con ningún registro.',
            ]);
        }

        if (! $usuario->activo) {
            throw ValidationException::withMessages([
                'email' => 'Esta cuenta de usuario se encuentra desactivada.',
            ]);
        }

        $token = $usuario->createToken(self::NOMBRE_TOKEN)->plainTextToken;

        return [
            'usuario' => $usuario,
            'token' => $token,
        ];
    }

    /**
     * Cierra la sesión activa revocando únicamente el token de acceso con el
     * que se autenticó la petición actual. Las demás sesiones del usuario en
     * otros dispositivos, si existen, no se ven afectadas.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-18
     * @módulo Administración y Seguridad – CC-92 / US-ADM-02
     *
     * @param  User $usuario Usuario autenticado en la petición actual.
     * @return void
     */
    public function cerrarSesion(User $usuario): void
    {
        $usuario->currentAccessToken()->delete();
    }
}
