<?php

namespace App\Services;

use App\Models\Requisicion;
use Illuminate\Support\Facades\DB;

/**
 * Gestiona el registro de solicitudes internas de insumos por área.
 *
 * @autor  Jeferson De La Cruz
 *
 * @fecha  2026-10-22
 *
 * @módulo Inventario – RF-INV-004 / CC-52
 */
class RequisicionService
{
    /**
     * Crea una requisición pendiente junto con todos sus renglones solicitados.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-10-22
     *
     * @módulo Inventario – RF-INV-004 / CC-52
     *
     * @param  array<string, mixed>  $datos  Datos ya validados del formulario.
     * @param  int  $usuarioId  Identificador del usuario que realiza la solicitud.
     * @return Requisicion Requisición creada con detalles e insumos cargados.
     */
    public function crear(array $datos, int $usuarioId): Requisicion
    {
        return DB::transaction(function () use ($datos, $usuarioId) {
            $requisicion = Requisicion::create([
                'usuario_solicita_id' => $usuarioId,
                'area_solicitante' => $datos['area_solicitante'],
                'estado' => 'pendiente',
                'observaciones' => $datos['observaciones'] ?? null,
            ]);

            foreach ($datos['detalles'] as $detalle) {
                $requisicion->detalles()->create([
                    'insumo_id' => $detalle['insumo_id'],
                    'cantidad_solicitada' => $detalle['cantidad_solicitada'],
                ]);
            }

            return $requisicion->load(['detalles.insumo', 'usuarioSolicita:id,name']);
        });
    }
}
