<?php

namespace App\Services;

use App\Models\Auditoria;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class AuditoriaService
{
    /**
     * Registros por página de la bitácora cuando no se indica otro valor.
     */
    public const REGISTROS_POR_PAGINA = 20;

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
     *
     * @fecha  2026-09-20
     *
     * @módulo Administración y Seguridad – RF-ADM-008 / CC-87
     *
     * @param  string  $accion  Acción realizada: creado, actualizado,
     *                          desactivado, reactivado, eliminado,
     *                          rol_asignado o rol_removido.
     * @param  Model  $entidad  Modelo auditado (por ejemplo, un usuario).
     * @param  array|null  $datosAnteriores  Estado previo relevante, o null si no aplica.
     * @param  array|null  $datosNuevos  Estado nuevo relevante, o null si no aplica.
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
     * Consulta la bitácora de auditoría, de lo más reciente a lo más antiguo, con filtros opcionales.
     * Cada registro incluye el nombre del usuario y el nombre corto de la entidad (por ejemplo, "User").
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – US-ADM-04 / CC-124
     *
     * @param  array  $filtros  Filtros validados por ListarAuditoriasRequest: usuario_id, accion,
     *                          entidad, desde, hasta y por_pagina, todos opcionales.
     * @return LengthAwarePaginator Página de registros de auditoría.
     */
    public function listar(array $filtros): LengthAwarePaginator
    {
        $consulta = Auditoria::query()->with('usuario:id,name');

        if (! empty($filtros['usuario_id'])) {
            $consulta->where('usuario_id', $filtros['usuario_id']);
        }

        if (! empty($filtros['accion'])) {
            $consulta->where('accion', $filtros['accion']);
        }

        if (! empty($filtros['entidad'])) {
            $consulta->whereIn('entidad_type', $this->tiposDeEntidad($filtros['entidad']));
        }

        // Rango por límites de fecha y hora, sin funciones sobre la columna, para que pueda usar índice
        if (! empty($filtros['desde'])) {
            $consulta->where('created_at', '>=', Carbon::parse($filtros['desde'])->startOfDay());
        }

        if (! empty($filtros['hasta'])) {
            $consulta->where('created_at', '<', Carbon::parse($filtros['hasta'])->addDay()->startOfDay());
        }

        $pagina = $consulta
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) ($filtros['por_pagina'] ?? self::REGISTROS_POR_PAGINA));

        $pagina->getCollection()->each(
            fn (Auditoria $auditoria) => $auditoria->setAttribute('entidad', class_basename($auditoria->entidad_type)),
        );

        return $pagina;
    }

    /**
     * Traduce el nombre corto de una entidad (por ejemplo, "User") a los tipos completos
     * guardados en la bitácora (por ejemplo, "App\Models\User").
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – US-ADM-04 / CC-124
     *
     * @param  string  $entidad  Nombre corto del modelo auditado.
     * @return array<int, string> Tipos completos que coinciden; vacío si no hay ninguno.
     */
    private function tiposDeEntidad(string $entidad): array
    {
        return Auditoria::query()
            ->distinct()
            ->pluck('entidad_type')
            ->filter(fn (string $tipo) => class_basename($tipo) === $entidad)
            ->values()
            ->all();
    }

    /**
     * Quita las credenciales de un arreglo de atributos antes de persistirlo,
     * conservando únicamente si el campo sensible cambió o no.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-09-20
     *
     * @módulo Administración y Seguridad – RF-ADM-008 / CC-87
     *
     * @param  array|null  $atributos  Atributos crudos del modelo auditado.
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
