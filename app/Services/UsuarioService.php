<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Servicio de lógica de negocio para la gestión de usuarios y roles RBAC (US-ADM-01 / CC-84).
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-20
 * @módulo Administración y Seguridad – RF-ADM-008
 */
class UsuarioService
{
    /**
     * Lista los usuarios del sistema aplicando filtros opcionales de búsqueda, rol y estado.
     *
     * @param  array<string, mixed> $filtros Filtros opcionales: busqueda, rol, activo.
     * @return Collection<int, User>
     */
    public function listar(array $filtros = []): Collection
    {
        $query = User::with(['roles:id,name', 'permissions:id,name'])
            ->orderBy('id', 'asc');

        if (! empty($filtros['busqueda'])) {
            $busqueda = '%' . trim($filtros['busqueda']) . '%';
            $query->where(function ($q) use ($busqueda) {
                $q->where('name', 'like', $busqueda)
                  ->orWhere('email', 'like', $busqueda);
            });
        }

        if (! empty($filtros['rol'])) {
            $query->role($filtros['rol']);
        }

        if (isset($filtros['activo']) && $filtros['activo'] !== '') {
            $query->where('activo', filter_var($filtros['activo'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->get();
    }

    /**
     * Registra un nuevo usuario en el sistema, cifra su contraseña con Bcrypt y le asigna su rol.
     *
     * @param  array<string, mixed> $datos Datos validados del usuario.
     * @return User
     */
    public function crear(array $datos): User
    {
        return DB::transaction(function () use ($datos) {
            $usuario = User::create([
                'name'     => trim($datos['name']),
                'email'    => strtolower(trim($datos['email'])),
                'password' => Hash::make($datos['password']),
                'activo'   => $datos['activo'] ?? true,
            ]);

            if (! empty($datos['rol'])) {
                $usuario->assignRole($datos['rol']);
            }

            return $usuario->fresh(['roles', 'permissions']);
        });
    }

    /**
     * Actualiza la información de un usuario, reasigna su rol y cifra su nueva contraseña si se envía.
     *
     * @param  User                 $usuario Instancia del usuario a modificar.
     * @param  array<string, mixed> $datos   Datos validados a actualizar.
     * @return User
     */
    public function actualizar(User $usuario, array $datos): User
    {
        return DB::transaction(function () use ($usuario, $datos) {
            $actualizaciones = [];

            if (isset($datos['name'])) {
                $actualizaciones['name'] = trim($datos['name']);
            }

            if (isset($datos['email'])) {
                $actualizaciones['email'] = strtolower(trim($datos['email']));
            }

            if (! empty($datos['password'])) {
                $actualizaciones['password'] = Hash::make($datos['password']);
            }

            if (isset($datos['activo'])) {
                $nuevoEstado = filter_var($datos['activo'], FILTER_VALIDATE_BOOLEAN);
                $actualizaciones['activo'] = $nuevoEstado;

                // Si se desactiva, revocar todos los tokens de sesión de inmediato
                if (! $nuevoEstado) {
                    $usuario->tokens()->delete();
                }
            }

            if (! empty($actualizaciones)) {
                $usuario->update($actualizaciones);
            }

            if (! empty($datos['rol'])) {
                $usuario->syncRoles([$datos['rol']]);
            }

            return $usuario->fresh(['roles', 'permissions']);
        });
    }

    /**
     * Desactiva la cuenta de un usuario impidiendo inicios de sesión y revocando sus tokens activos.
     * Regla de negocio: Un administrador no puede desactivarse a sí mismo si es el único activo.
     *
     * @param  User      $usuario     Usuario que será desactivado.
     * @param  User|null $solicitante Usuario administrador que ejecuta la acción.
     * @return User
     * @throws ValidationException
     */
    public function desactivar(User $usuario, ?User $solicitante = null): User
    {
        if ($solicitante && $usuario->id === $solicitante->id) {
            throw ValidationException::withMessages([
                'usuario' => 'No puedes desactivar tu propia cuenta de administrador en sesión.',
            ]);
        }

        // Verificar si es el último administrador activo
        if ($usuario->hasRole('administrador')) {
            $adminsActivos = User::role('administrador')->where('activo', true)->count();
            if ($adminsActivos <= 1) {
                throw ValidationException::withMessages([
                    'usuario' => 'No es posible desactivar al único administrador activo del sistema.',
                ]);
            }
        }

        return DB::transaction(function () use ($usuario) {
            $usuario->update(['activo' => false]);
            $usuario->tokens()->delete();

            return $usuario->fresh(['roles', 'permissions']);
        });
    }

    /**
     * Activa una cuenta de usuario previamente inhabilitada.
     *
     * @param  User $usuario Usuario a reactivar.
     * @return User
     */
    public function activar(User $usuario): User
    {
        $usuario->update(['activo' => true]);

        return $usuario->fresh(['roles', 'permissions']);
    }

    /**
     * Alterna el estado activo de un usuario (activar o desactivar según corresponda).
     *
     * @param  User      $usuario
     * @param  User|null $solicitante
     * @return User
     */
    public function toggleActivo(User $usuario, ?User $solicitante = null): User
    {
        if ($usuario->activo) {
            return $this->desactivar($usuario, $solicitante);
        }

        return $this->activar($usuario);
    }

    /**
     * Elimina permanentemente una cuenta de usuario del sistema.
     * Reglas de negocio e integridad referencial:
     * 1. Un administrador no puede eliminarse a sí mismo en sesión.
     * 2. No se puede eliminar al único administrador del sistema.
     * 3. No se puede eliminar si posee registros operativos vinculados (comandas, ventas o retiros).
     *
     * @param  User      $usuario     Usuario a eliminar.
     * @param  User|null $solicitante Usuario administrador que ejecuta la acción.
     * @return bool
     * @throws ValidationException
     */
    public function eliminar(User $usuario, ?User $solicitante = null): bool
    {
        if ($solicitante && $usuario->id === $solicitante->id) {
            throw ValidationException::withMessages([
                'usuario' => 'No puedes eliminar tu propia cuenta de administrador en sesión.',
            ]);
        }

        // Verificar si es el último administrador existente
        if ($usuario->hasRole('administrador')) {
            $adminsCount = User::role('administrador')->count();
            if ($adminsCount <= 1) {
                throw ValidationException::withMessages([
                    'usuario' => 'No es posible eliminar al único administrador del sistema.',
                ]);
            }
        }

        // Verificar integridad referencial: comandas, ventas, retiros o auditoría
        $tieneComandas   = DB::table('comandas')->where('usuario_id', $usuario->id)->exists();
        $tieneVentas     = DB::table('ventas')->where('usuario_id', $usuario->id)->exists();
        $tieneRetiros    = DB::table('retiros_parciales')->where('usuario_id', $usuario->id)->exists();
        $tieneAuditorias = DB::table('auditorias')->where('usuario_id', $usuario->id)->exists();

        if ($tieneComandas || $tieneVentas || $tieneRetiros) {
            throw ValidationException::withMessages([
                'usuario' => 'No es posible eliminar permanentemente este usuario porque posee registros operativos vinculados (comandas, ventas o retiros). Se recomienda desactivar la cuenta para bloquear su acceso conservando la trazabilidad contable.',
            ]);
        }

        if ($tieneAuditorias) {
            throw ValidationException::withMessages([
                'usuario' => 'No es posible eliminar permanentemente este usuario porque registra acciones en el historial de auditoría (RF-ADM-008). Se recomienda desactivar la cuenta para conservar la trazabilidad.',
            ]);
        }

        return DB::transaction(function () use ($usuario) {
            $usuario->tokens()->delete();
            $usuario->roles()->detach();
            $usuario->permissions()->detach();

            return (bool) $usuario->delete();
        });
    }

    /**
     * Obtiene el catálogo de roles oficiales disponibles en el sistema SGCO-Chayito.
     *
     * @return array<int, string>
     */
    public function obtenerRolesDisponibles(): array
    {
        return Role::pluck('name')->toArray();
    }
}

