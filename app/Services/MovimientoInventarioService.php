<?php

namespace App\Services;

use App\Exceptions\ValidacionMovimientoInventarioException;
use App\Models\Insumo;
use App\Models\MovimientoInventario;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MovimientoInventarioService
{
    /**
     * Aplica un delta bajo bloqueo y registra exactamente un movimiento en la misma transaccion.
     *
     * @autor  Equipo de desarrollo
     * @fecha  2026-11-17
     * @módulo Inventario RF-INV-003 / CC-113 / CC-115 / CC-116
     *
     * @param Insumo $insumo Insumo cuyo saldo cambia.
     * @param float|string $cantidad Delta firmado con precision de seis decimales.
     * @param string $tipo Tipo definido por MovimientoInventario.
     * @param int|null $usuarioId Actor real; por defecto el usuario autenticado.
     * @param array<string, mixed> $referencias Origen del movimiento y motivo.
     * @return array{insumo: Insumo, movimiento: MovimientoInventario} Saldo e historial confirmados.
     */
    public function aplicar(
        Insumo $insumo,
        float|string $cantidad,
        string $tipo,
        ?int $usuarioId = null,
        array $referencias = []
    ): array {
        $usuarioId ??= auth()->id();

        if ($usuarioId === null) {
            throw new AuthenticationException('Se requiere un usuario para registrar el movimiento.');
        }

        $delta = (int) round((float) $cantidad * 1000000);
        $tipos = [MovimientoInventario::TIPO_ENTRADA, MovimientoInventario::TIPO_AJUSTE,
            MovimientoInventario::TIPO_VENTA, MovimientoInventario::TIPO_REQUISICION];

        if (! is_finite((float) $cantidad) || abs((float) $cantidad) > 99999999.999999
            || $delta === 0 || ! in_array($tipo, $tipos, true)
            || ($tipo === MovimientoInventario::TIPO_ENTRADA && $delta < 0)
            || (in_array($tipo, [MovimientoInventario::TIPO_VENTA, MovimientoInventario::TIPO_REQUISICION], true)
                && $delta > 0)) {
            throw ValidacionMovimientoInventarioException::withMessages([
                'cantidad' => 'La cantidad no es valida para el tipo de movimiento.',
            ]);
        }

        if ($tipo === MovimientoInventario::TIPO_AJUSTE && trim($referencias['motivo'] ?? '') === '') {
            throw ValidacionMovimientoInventarioException::withMessages([
                'motivo' => 'El motivo del ajuste es obligatorio.',
            ]);
        }

        return DB::transaction(function () use ($insumo, $delta, $tipo, $usuarioId, $referencias) {
            $bloqueado = Insumo::query()->lockForUpdate()->findOrFail($insumo->id);
            // Las sumas en millonesimos evitan perder precision al acumular deltas.
            $saldo = (int) round((float) $bloqueado->stock_actual * 1000000) + $delta;

            if (abs($saldo) > 99999999999999
                || ($saldo < 0 && in_array($tipo, [
                    MovimientoInventario::TIPO_AJUSTE, MovimientoInventario::TIPO_REQUISICION,
                ], true))) {
                throw ValidacionMovimientoInventarioException::withMessages([
                    'cantidad' => 'El movimiento excede el rango del saldo o deja existencias negativas.',
                ]);
            }

            $stockResultante = number_format($saldo / 1000000, 6, '.', '');
            $bloqueado->update(['stock_actual' => $stockResultante]);
            $movimiento = MovimientoInventario::create([
                'insumo_id' => $bloqueado->id,
                'usuario_id' => $usuarioId,
                'tipo' => $tipo,
                'cantidad' => number_format($delta / 1000000, 6, '.', ''),
                'stock_resultante' => $stockResultante,
                'egreso_id' => $referencias['egreso_id'] ?? null,
                'comanda_id' => $referencias['comanda_id'] ?? null,
                'requisicion_id' => $referencias['requisicion_id'] ?? null,
                'motivo' => $referencias['motivo'] ?? null,
            ]);
            $movimiento->load(['usuario:id,name', 'egreso:id,correlativo,proveedor,numero_comprobante']);
            $movimiento->usuario?->setAppends([]);

            return [
                'insumo' => app(InsumoService::class)->detalle($bloqueado),
                'movimiento' => $movimiento,
            ];
        });
    }

    /**
     * Lista el historial paginado con responsables y comprobantes sin exponer otros datos del usuario.
     *
     * @autor  Equipo de desarrollo
     * @fecha  2026-11-17
     * @módulo Inventario RF-INV-003 / CC-113 / CC-117
     *
     * @param Insumo $insumo Insumo consultado.
     * @param int $pagina Pagina validada del historial.
     * @return LengthAwarePaginator Movimientos mas recientes primero, veinte por pagina.
     */
    public function listar(Insumo $insumo, int $pagina = 1): LengthAwarePaginator
    {
        $paginador = $insumo->movimientos()
            ->with(['usuario:id,name', 'egreso:id,correlativo,proveedor,numero_comprobante'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'page', $pagina);

        foreach ($paginador->items() as $movimiento) {
            $movimiento->usuario?->setAppends([]);
        }

        return $paginador;
    }
}
