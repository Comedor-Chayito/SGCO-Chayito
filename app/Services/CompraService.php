<?php

namespace App\Services;

use App\Models\Compra;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Registra compras y persiste su desglose automático de IVA crédito fiscal.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
 */
class CompraService
{
    /**
     * Inicializa el servicio con el calculador centralizado de IVA crédito fiscal.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
     *
     * @param  IvaCreditoFiscalService  $ivaCreditoFiscalService  Servicio de fórmulas tributarias.
     */
    public function __construct(private readonly IvaCreditoFiscalService $ivaCreditoFiscalService) {}

    /**
     * Calcula y guarda el desglose tributario de una compra en una sola transacción.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
     *
     * @param  array{fecha_compra: string, monto_total: float|int|string}  $datos  Compra ya validada.
     * @param  int  $usuarioId  Identificador de quien registra el comprobante.
     * @return Compra Compra persistida con el desglose de IVA y el usuario cargado.
     */
    public function registrar(array $datos, int $usuarioId): Compra
    {
        return DB::transaction(function () use ($datos, $usuarioId) {
            $montoTotal = round((float) $datos['monto_total'], 2);
            $desglose = $this->ivaCreditoFiscalService->calcular($montoTotal);

            $compra = Compra::create([
                'usuario_id' => $usuarioId,
                'fecha_compra' => $datos['fecha_compra'],
                'monto_total' => $montoTotal,
                'costo_neto' => $desglose['costo_neto'],
                'iva_credito_fiscal' => $desglose['iva_credito_fiscal'],
            ]);

            return $compra->load('usuario:id,name');
        });
    }

    /**
     * Lista las compras más recientes con la persona que las registró.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
     *
     * @return Collection<int, Compra> Compras ordenadas para consulta contable.
     */
    public function listar(): Collection
    {
        return Compra::query()
            ->with('usuario:id,name')
            ->orderByDesc('fecha_compra')
            ->orderByDesc('id')
            ->get();
    }
}
