<?php

namespace App\Services;

use App\Http\Requests\ProcesarCobroRequest;
use App\Models\Comanda;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;

class CobroService
{
    /**
     * Inicializa el servicio de cobro con la deducción automática de inventario.
     *
     * @autor  Alexis
     *
     * @fecha  2026-10-19
     *
     * @módulo Inventario – RF-INV-003 / CC-47
     *
     * @param  DeduccionInventarioService  $deduccionInventarioService  Servicio de consumo por receta.
     */
    public function __construct(private readonly DeduccionInventarioService $deduccionInventarioService) {}

    /**
     * Procesa el cobro de una comanda dentro de una transacción de base de datos.
     * Calcula el cambio exacto, genera el registro de venta y actualiza la comanda a "pagada".
     * Con tarjeta o transferencia el monto recibido es el total y el cambio es cero.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-09-19
     *
     * @módulo POS – RF-POS-002
     *
     * @param  ProcesarCobroRequest  $request  Datos validados de la solicitud de cobro.
     * @param  int  $usuarioId  ID del cajero que procesa la transacción.
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
            $metodoPago = $datos['metodo_pago'] ?? Venta::METODO_EFECTIVO;

            // Tarjeta y transferencia se cobran por el total exacto: no hay cambio que entregar
            $montoRecibido = $metodoPago === Venta::METODO_EFECTIVO
                ? (float) $datos['monto_recibido']
                : $total;
            $cambio = round($montoRecibido - $total, 2);

            $venta = Venta::create([
                'comanda_id' => $comanda->id,
                'usuario_id' => $usuarioId,
                'total' => $total,
                'monto_recibido' => $montoRecibido,
                'cambio' => $cambio,
                'metodo_pago' => $metodoPago,
            ]);

            $this->deduccionInventarioService->descontarPorComanda($comanda);
            $comanda->update(['estado' => 'pagada']);

            // Liberar la mesa asociada si no quedan otras comandas activas (CC-34)
            if ($comanda->mesa_id) {
                $otrasActivas = Comanda::where('mesa_id', $comanda->mesa_id)
                    ->where('id', '!=', $comanda->id)
                    ->whereIn('estado', ['pendiente', 'en_preparacion', 'lista'])
                    ->exists();

                if (! $otrasActivas && $comanda->mesa) {
                    $comanda->mesa->update(['estado' => 'libre']);
                }
            }

            return $venta->load(['comanda.detalles.platillo', 'comanda.mesa', 'usuario:id,name']);
        });
    }

    /**
     * Obtiene los datos completos de un comprobante de venta o ticket.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-09-19
     *
     * @módulo POS – RF-POS-002
     *
     * @param  int  $ventaId  Identificador único de la venta.
     * @return Venta Registro de venta con relaciones cargadas para comprobante.
     */
    public function obtenerComprobante(int $ventaId): Venta
    {
        return Venta::with(['comanda.detalles.platillo', 'comanda.mesa', 'usuario:id,name'])
            ->findOrFail($ventaId);
    }
}
