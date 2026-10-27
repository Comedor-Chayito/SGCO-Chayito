<?php

namespace App\Services;

use App\Models\Auditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class AuditoriaService
{
    /**
     * Atributos que nunca se guardan en el registro de auditoría, aunque
     * hayan cambiado, para no dejar credenciales expuestas en el historial.
     *
     * @var list<string>
     */
    private const ATRIBUTOS_SENSIBLES = ['password', 'remember_token'];

    /**
     * Deja constancia de una acción (creación, modificación, desactivación,
     * eliminación o cambio de rol) sobre una entidad administrable, junto con
     * el usuario autenticado que la ejecutó. Requisito RF-ADM-008: toda
     * creación o modificación de usuario deja registro de auditoría.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-20
     * @módulo Administración y Seguridad – RF-ADM-008 / CC-87
     *
     * @param  string     $accion           Acción realizada: creado, actualizado,
     *                                       desactivado, reactivado, eliminado,
     *                                       rol_asignado o rol_removido.
     * @param  Model      $entidad          Modelo auditado (por ejemplo, un usuario).
     * @param  array|null $datosAnteriores  Estado previo relevante, o null si no aplica.
     * @param  array|null $datosNuevos      Estado nuevo relevante, o null si no aplica.
     * @return Auditoria
     */
    public function registrar(
        string $accion,
        Model $entidad,
        ?array $datosAnteriores = null,
        ?array $datosNuevos = null,
    ): Auditoria {
        return Auditoria::create([
            'usuario_id' => Auth::id(),
            'accion' => $accion,
            'entidad_type' => $entidad->getMorphClass(),
            'entidad_id' => $entidad->getKey(),
            'datos_anteriores' => $this->filtrarAtributosSensibles($datosAnteriores),
            'datos_nuevos' => $this->filtrarAtributosSensibles($datosNuevos),
        ]);
    }

    /**
     * Quita las credenciales de un arreglo de atributos antes de persistirlo,
     * conservando únicamente si el campo sensible cambió o no.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-20
     * @módulo Administración y Seguridad – RF-ADM-008 / CC-87
     *
     * @param  array|null $atributos Atributos crudos del modelo auditado.
     * @return array|null Atributos sin credenciales, o null si no había nada que filtrar.
     */
    private function filtrarAtributosSensibles(?array $atributos): ?array
    {
        if ($atributos === null) {
            return null;
        }

        $atributosFiltrados = Arr::except($atributos, self::ATRIBUTOS_SENSIBLES);

        foreach (self::ATRIBUTOS_SENSIBLES as $atributoSensible) {
            if (array_key_exists($atributoSensible, $atributos)) {
                $atributosFiltrados[$atributoSensible] = '[protegido]';
            }
        }

        return $atributosFiltrados;
    }
}
