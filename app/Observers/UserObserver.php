<?php

namespace App\Observers;

use App\Models\User;
use App\Services\AuditoriaService;

class UserObserver
{
    public function __construct(private readonly AuditoriaService $auditoriaService)
    {
    }

    /**
     * Registra en auditoría la creación de un usuario.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-20
     * @módulo Administración y Seguridad – RF-ADM-008 / CC-87
     *
     * @param  User $user Usuario recién creado.
     * @return void
     */
    public function created(User $user): void
    {
        $this->auditoriaService->registrar(
            accion: 'creado',
            entidad: $user,
            datosNuevos: $user->getAttributes(),
        );
    }

    /**
     * Registra en auditoría la modificación de un usuario, incluida su
     * desactivación o reactivación cuando el CRUD (CC-84) agregue el campo
     * de estado correspondiente.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-20
     * @módulo Administración y Seguridad – RF-ADM-008 / CC-87
     *
     * @param  User $user Usuario modificado.
     * @return void
     */
    public function updated(User $user): void
    {
        $accion = 'actualizado';

        if ($user->wasChanged('activo')) {
            $accion = $user->activo ? 'reactivado' : 'desactivado';
        }

        $this->auditoriaService->registrar(
            accion: $accion,
            entidad: $user,
            datosAnteriores: $user->getOriginal(),
            datosNuevos: $user->getChanges(),
        );
    }

    /**
     * Registra en auditoría la eliminación definitiva de un usuario.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-20
     * @módulo Administración y Seguridad – RF-ADM-008 / CC-87
     *
     * @param  User $user Usuario eliminado.
     * @return void
     */
    public function deleted(User $user): void
    {
        $this->auditoriaService->registrar(
            accion: 'eliminado',
            entidad: $user,
            datosAnteriores: $user->getAttributes(),
        );
    }
}
