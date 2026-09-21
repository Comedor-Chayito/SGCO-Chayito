<?php

namespace App\Listeners;

use App\Services\AuditoriaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Spatie\Permission\Models\Role;

class AuditarCambiosDeRol
{
    public function __construct(private readonly AuditoriaService $auditoriaService)
    {
    }

    /**
     * Registra en auditoría la asignación de uno o más roles a un usuario.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-20
     * @módulo Administración y Seguridad – RF-ADM-008 / CC-87
     *
     * @param  RoleAttachedEvent $event Evento emitido por spatie/laravel-permission.
     * @return void
     */
    public function alAsignar(RoleAttachedEvent $event): void
    {
        if (! $event->model instanceof Model) {
            return;
        }

        $this->auditoriaService->registrar(
            accion: 'rol_asignado',
            entidad: $event->model,
            datosNuevos: ['roles' => $this->normalizarRoles($event->rolesOrIds)],
        );
    }

    /**
     * Registra en auditoría la remoción de uno o más roles de un usuario.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-20
     * @módulo Administración y Seguridad – RF-ADM-008 / CC-87
     *
     * @param  RoleDetachedEvent $event Evento emitido por spatie/laravel-permission.
     * @return void
     */
    public function alRemover(RoleDetachedEvent $event): void
    {
        if (! $event->model instanceof Model) {
            return;
        }

        $this->auditoriaService->registrar(
            accion: 'rol_removido',
            entidad: $event->model,
            datosAnteriores: ['roles' => $this->normalizarRoles($event->rolesOrIds)],
        );
    }

    /**
     * Convierte los roles recibidos del evento (ids, nombres o modelos, según
     * la versión de spatie/laravel-permission) a una lista de nombres de rol,
     * para que el registro de auditoría sea legible por la administradora.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-20
     * @módulo Administración y Seguridad – RF-ADM-008 / CC-87
     *
     * @param  mixed $rolesOrIds Roles recibidos del evento de spatie/laravel-permission.
     * @return array Lista de nombres de rol.
     */
    private function normalizarRoles(mixed $rolesOrIds): array
    {
        $roles = match (true) {
            $rolesOrIds instanceof Collection => $rolesOrIds->all(),
            is_array($rolesOrIds) => $rolesOrIds,
            default => [$rolesOrIds],
        };

        $idsPorResolver = array_values(array_filter($roles, fn ($rol) => ! is_object($rol)));
        $nombresPorId = $idsPorResolver === []
            ? collect()
            : Role::whereIn('id', $idsPorResolver)->pluck('name', 'id');

        return array_values(array_map(
            fn ($rol) => is_object($rol) && property_exists($rol, 'name')
                ? $rol->name
                : ($nombresPorId[$rol] ?? $rol),
            $roles,
        ));
    }
}
