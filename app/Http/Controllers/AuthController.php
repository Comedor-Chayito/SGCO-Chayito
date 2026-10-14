<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    /**
     * Inicia sesión con correo y contraseña, y devuelve un token de sesión seguro.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-18
     * @módulo Administración y Seguridad – CC-91 / US-ADM-02
     *
     * @param  LoginRequest $request Credenciales ya validadas.
     * @return JsonResponse
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $resultado = $this->authService->iniciarSesion($request->validated());

        return response()->json([
            'status' => 'ok',
            'message' => 'Sesión iniciada correctamente.',
            'data' => [
                'usuario' => $resultado['usuario'],
                'token' => $resultado['token'],
            ],
        ], 201);
    }

    /**
     * Cierra la sesión actual revocando el token de acceso con el que se
     * autenticó la petición.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-18
     * @módulo Administración y Seguridad – CC-92 / US-ADM-02
     *
     * @param  Request $request Petición autenticada vía Sanctum.
     * @return JsonResponse
     */
    public function destroy(Request $request): JsonResponse
    {
        $this->authService->cerrarSesion($request->user());

        return response()->json([
            'status' => 'ok',
            'message' => 'Sesión cerrada correctamente.',
            'data' => null,
        ], 200);
    }

    /**
     * Alias de store para compatibilidad con rutas frontend /auth/login.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo Administración y Seguridad – US-ADM-02
     *
     * @param  LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        return $this->store($request);
    }

    /**
     * Alias de destroy para compatibilidad con rutas frontend /auth/logout.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo Administración y Seguridad – US-ADM-02
     *
     * @param  Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        return $this->destroy($request);
    }
}
