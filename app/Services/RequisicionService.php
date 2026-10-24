<?php

namespace App\Services;

use App\Models\ExistenciaAreaOperativa;
use App\Models\Insumo;
use App\Models\Requisicion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Gestiona el registro, aprobación y transferencia de requisiciones internas.
 *
 * @autor  Jeferson De La Cruz / Alexis
 *
 * @fecha  2026-10-22
 *
 * @módulo Inventario – RF-INV-004 / CC-52 / CC-53 / CC-54
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

    /**
     * Lista requisiciones junto con usuarios, detalles e insumos para gestión de bodega.
     *
     * @autor  Alexis
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-53 / CC-54
     *
     * @return Collection<int, Requisicion> Requisiciones ordenadas desde la más reciente.
     */
    public function listar(): Collection
    {
        return Requisicion::query()
            ->with(['detalles.insumo', 'usuarioSolicita:id,name', 'usuarioAprueba:id,name'])
            ->latest()
            ->get();
    }

    /**
     * Aprueba cantidades parciales o rechaza una requisición aún pendiente.
     *
     * @autor  Alexis
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-53
     *
     * @param  Requisicion $requisicion Requisición que se desea resolver.
     * @param  array<string, mixed> $datos Decisión y cantidades aprobadas ya validadas.
     * @param  int $usuarioId Identificador del administrador que toma la decisión.
     * @return Requisicion Requisición resuelta con detalles e insumos cargados.
     *
     * @throws ValidationException Si no está pendiente o las cantidades no corresponden.
     */
    public function resolver(Requisicion $requisicion, array $datos, int $usuarioId): Requisicion
    {
        return DB::transaction(function () use ($requisicion, $datos, $usuarioId) {
            $requisicionBloqueada = Requisicion::query()
                ->with('detalles')
                ->lockForUpdate()
                ->findOrFail($requisicion->id);

            if ($requisicionBloqueada->estado !== 'pendiente') {
                throw ValidationException::withMessages([
                    'estado' => 'Solo se pueden resolver requisiciones pendientes.',
                ]);
            }

            if ($datos['estado'] === 'aprobada') {
                $this->validarCantidadesAprobadas($requisicionBloqueada, $datos['detalles']);

                foreach ($datos['detalles'] as $detalle) {
                    $requisicionBloqueada->detalles
                        ->firstWhere('id', $detalle['id'])
                        ->update(['cantidad_aprobada' => $detalle['cantidad_aprobada']]);
                }
            }

            $requisicionBloqueada->update([
                'estado' => $datos['estado'],
                'usuario_aprueba_id' => $usuarioId,
                'fecha_resolucion' => now(),
            ]);

            return $this->cargarRelaciones($requisicionBloqueada);
        });
    }

    /**
     * Transfiere cantidades aprobadas de bodega general al inventario del área solicitante.
     *
     * @autor  Alexis
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-54
     *
     * @param  Requisicion $requisicion Requisición aprobada que se transferirá.
     * @return Requisicion Requisición transferida con sus relaciones actualizadas.
     *
     * @throws ValidationException Si no está aprobada o bodega no tiene stock suficiente.
     */
    public function transferir(Requisicion $requisicion): Requisicion
    {
        return DB::transaction(function () use ($requisicion) {
            $requisicionBloqueada = Requisicion::query()
                ->with('detalles.insumo')
                ->lockForUpdate()
                ->findOrFail($requisicion->id);

            if ($requisicionBloqueada->estado !== 'aprobada') {
                throw ValidationException::withMessages([
                    'estado' => 'Solo se pueden transferir requisiciones aprobadas.',
                ]);
            }

            $insumos = Insumo::query()
                ->whereIn('id', $requisicionBloqueada->detalles->pluck('insumo_id')->unique())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $errores = [];

            foreach ($requisicionBloqueada->detalles as $detalle) {
                $cantidadAprobada = (float) $detalle->cantidad_aprobada;
                $insumo = $insumos->get($detalle->insumo_id);

                if ($cantidadAprobada <= 0) {
                    $errores["detalles.{$detalle->id}.cantidad_aprobada"] =
                        'La requisición aprobada contiene una cantidad inválida.';
                } elseif (! $insumo || (float) $insumo->stock_actual < $cantidadAprobada) {
                    $errores["detalles.{$detalle->id}.cantidad_aprobada"] =
                        "Stock insuficiente para {$detalle->insumo->nombre}.";
                }
            }

            if ($errores !== []) {
                throw ValidationException::withMessages($errores);
            }

            $existenciasArea = ExistenciaAreaOperativa::query()
                ->where('area', $requisicionBloqueada->area_solicitante)
                ->whereIn('insumo_id', $insumos->keys())
                ->lockForUpdate()
                ->get()
                ->keyBy('insumo_id');

            foreach ($requisicionBloqueada->detalles as $detalle) {
                $cantidadAprobada = (float) $detalle->cantidad_aprobada;
                $insumo = $insumos->get($detalle->insumo_id);
                $stockBodega = round((float) $insumo->stock_actual - $cantidadAprobada, 6);

                $insumo->update(['stock_actual' => $stockBodega]);

                $existenciaArea = $existenciasArea->get($insumo->id);

                if ($existenciaArea) {
                    $existenciaArea->update([
                        'stock_actual' => round((float) $existenciaArea->stock_actual + $cantidadAprobada, 6),
                    ]);
                } else {
                    $existenciaArea = ExistenciaAreaOperativa::create([
                        'area' => $requisicionBloqueada->area_solicitante,
                        'insumo_id' => $insumo->id,
                        'stock_actual' => $cantidadAprobada,
                    ]);
                    $existenciasArea->put($insumo->id, $existenciaArea);
                }
            }

            $requisicionBloqueada->update(['estado' => 'transferida']);

            return $this->cargarRelaciones($requisicionBloqueada);
        });
    }

    /**
     * Verifica que se resuelva cada detalle con una cantidad no mayor a la solicitada.
     *
     * @autor  Alexis
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-53
     *
     * @param  Requisicion $requisicion Requisición bloqueada con sus detalles cargados.
     * @param  array<int, array{id: int, cantidad_aprobada: float|int|string}> $detalles Datos de aprobación.
     *
     * @throws ValidationException Si falta, sobra o excede una cantidad aprobada.
     */
    private function validarCantidadesAprobadas(Requisicion $requisicion, array $detalles): void
    {
        $detallesPorId = collect($detalles)->keyBy('id');

        if ($detallesPorId->count() !== $requisicion->detalles->count()) {
            throw ValidationException::withMessages([
                'detalles' => 'Debes aprobar una cantidad para cada insumo solicitado.',
            ]);
        }

        foreach ($requisicion->detalles as $detalle) {
            $detalleAprobado = $detallesPorId->get($detalle->id);

            if (! $detalleAprobado) {
                throw ValidationException::withMessages([
                    'detalles' => 'Los renglones aprobados no corresponden a la requisición.',
                ]);
            }

            if ((float) $detalleAprobado['cantidad_aprobada'] > (float) $detalle->cantidad_solicitada) {
                throw ValidationException::withMessages([
                    "detalles.{$detalle->id}.cantidad_aprobada" =>
                        'La cantidad aprobada no puede superar la cantidad solicitada.',
                ]);
            }
        }
    }

    /**
     * Recarga las relaciones que requiere la respuesta de la API de requisiciones.
     *
     * @autor  Alexis
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-53 / CC-54
     *
     * @param  Requisicion $requisicion Requisición a cargar.
     * @return Requisicion Requisición con solicitante, aprobador, detalles e insumos.
     */
    private function cargarRelaciones(Requisicion $requisicion): Requisicion
    {
        return $requisicion->load(['detalles.insumo', 'usuarioSolicita:id,name', 'usuarioAprueba:id,name']);
    }
}
