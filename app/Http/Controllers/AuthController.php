<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

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
}
