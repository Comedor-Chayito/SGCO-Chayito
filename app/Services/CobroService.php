<?php

namespace App\Services;

use App\Http\Requests\ProcesarCobroRequest;
use App\Models\Comanda;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;

class CobroService
{
    /**
     * Procesa el cobro de una comanda dentro de una transacción de base de datos.
     * Calcula el cambio exacto, genera el registro de venta y actualiza la comanda a "pagada".
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-002
     *
     * @param  ProcesarCobroRequest $request   Datos validados de la solicitud de cobro.
     * @param  int                  $usuarioId ID del cajero que procesa la transacción.
     * @return Venta Registro de venta con comanda, detalles, mesa y cajero cargados.
     *
     * @throws \Throwable Si falla la transacción en base de datos.
     */
    public function procesarCobro(ProcesarCobroRequest $request, int $usuarioId): Venta
    {
        return DB::transaction(function () use ($request, $usuarioId) {
            $datos = $request->validated();
            $comanda = Comanda::lockForUpdate()->findOrFail($datos['comanda_id']);

            $total = (float) $comanda->subtotal;
            $montoRecibido = (float) $datos['monto_recibido'];
            $cambio = round($montoRecibido - $total, 2);
            $metodoPago = $datos['metodo_pago'] ?? 'efectivo';

            $venta = Venta::create([
                'comanda_id'     => $comanda->id,
                'usuario_id'     => $usuarioId,
                'total'          => $total,
                'monto_recibido' => $montoRecibido,
                'cambio'         => $cambio,
                'metodo_pago'    => $metodoPago,
            ]);

            $comanda->update(['estado' => 'pagada']);

            return $venta->load(['comanda.detalles.platillo', 'comanda.mesa', 'usuario:id,name']);
        });
    }

    /**
     * Obtiene los datos completos de un comprobante de venta o ticket.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-002
     *
     * @param  int $ventaId Identificador único de la venta.
     * @return Venta Registro de venta con relaciones cargadas para comprobante.
     */
    public function obtenerComprobante(int $ventaId): Venta
    {
        return Venta::with(['comanda.detalles.platillo', 'comanda.mesa', 'usuario:id,name'])
            ->findOrFail($ventaId);
    }
}

