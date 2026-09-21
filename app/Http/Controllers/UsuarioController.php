<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActualizarUsuarioRequest;
use App\Http\Requests\CrearUsuarioRequest;
use App\Models\User;
use App\Services\UsuarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para la gestión de usuarios y roles RBAC (US-ADM-01 / CC-84).
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-20
 * @módulo Administración y Seguridad – RF-ADM-008
 */
class UsuarioController extends Controller
{
    public function __construct(private readonly UsuarioService $usuarioService)
    {
    }

    /**
     * Lista los usuarios del sistema con sus roles y estados.
     *
     * @param  Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->only(['busqueda', 'rol', 'activo']);
        $usuarios = $this->usuarioService->listar($filtros);

        return response()->json([
            'status'  => 'ok',
            'message' => 'Usuarios obtenidos correctamente.',
            'data'    => $usuarios,
        ]);
    }

    /**
     * Registra un nuevo usuario asignándole un rol oficial.
     *
     * @param  CrearUsuarioRequest $request
     * @return JsonResponse
     */
    public function store(CrearUsuarioRequest $request): JsonResponse
    {
        $usuario = $this->usuarioService->crear($request->validated());

        return response()->json([
            'status'  => 'ok',
            'message' => 'Usuario registrado exitosamente.',
            'data'    => $usuario,
        ], 201);
    }

    /**
     * Devuelve los detalles de un usuario específico.
     *
     * @param  User $usuario
     * @return JsonResponse
     */
    public function show(User $usuario): JsonResponse
    {
        $usuario->load(['roles:id,name', 'permissions:id,name']);

        return response()->json([
            'status'  => 'ok',
            'message' => 'Detalle del usuario obtenido.',
            'data'    => $usuario,
        ]);
    }

    /**
     * Modifica los datos y/o rol de un usuario existente.
     *
     * @param  ActualizarUsuarioRequest $request
     * @param  User                     $usuario
     * @return JsonResponse
     */
    public function update(ActualizarUsuarioRequest $request, User $usuario): JsonResponse
    {
        $usuarioActualizado = $this->usuarioService->actualizar($usuario, $request->validated());

        return response()->json([
            'status'  => 'ok',
            'message' => 'Usuario actualizado correctamente.',
            'data'    => $usuarioActualizado,
        ]);
    }

    /**
     * Elimina permanentemente la cuenta de un usuario si no posee restricciones de integridad.
     *
     * @param  Request $request
     * @param  User    $usuario
     * @return JsonResponse
     */
    public function destroy(Request $request, User $usuario): JsonResponse
    {
        $this->usuarioService->eliminar($usuario, $request->user());

        return response()->json([
            'status'  => 'ok',
            'message' => 'Usuario eliminado correctamente.',
            'data'    => null,
        ]);
    }

    /**
     * Alterna el estado activo de la cuenta de un usuario (activar o desactivar).
     *
     * @param  Request $request
     * @param  User    $usuario
     * @return JsonResponse
     */
    public function toggleActivo(Request $request, User $usuario): JsonResponse
    {
        $usuarioActualizado = $this->usuarioService->toggleActivo($usuario, $request->user());

        $mensaje = $usuarioActualizado->activo
            ? 'Cuenta de usuario reactivada correctamente.'
            : 'Cuenta de usuario desactivada correctamente.';

        return response()->json([
            'status'  => 'ok',
            'message' => $mensaje,
            'data'    => $usuarioActualizado,
        ]);
    }

    /**
     * Devuelve el catálogo de roles oficiales disponibles en el sistema.
     *
     * @return JsonResponse
     */
    public function roles(): JsonResponse
    {
        $roles = $this->usuarioService->obtenerRolesDisponibles();

        return response()->json([
            'status'  => 'ok',
            'message' => 'Roles disponibles obtenidos correctamente.',
            'data'    => $roles,
        ]);
    }
}

