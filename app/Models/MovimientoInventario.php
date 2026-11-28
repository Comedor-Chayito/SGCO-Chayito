<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoInventario extends Model
{
    public const TIPO_ENTRADA = 'entrada';

    public const TIPO_VENTA = 'venta';

    public const TIPO_REQUISICION = 'requisicion';

    public const TIPO_AJUSTE = 'ajuste';

    protected $table = 'movimientos_inventario';

    protected $fillable = [
        'insumo_id',
        'usuario_id',
        'tipo',
        'cantidad',
        'stock_resultante',
        'egreso_id',
        'comanda_id',
        'requisicion_id',
        'motivo',
    ];

    protected $casts = [
        'cantidad' => 'decimal:6',
        'stock_resultante' => 'decimal:6',
    ];

    /**
     * Obtiene el insumo cuyo stock cambió con este movimiento.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-16
     *
     * @módulo Inventario – US-INV-04 (CC-114)
     *
     * @return BelongsTo<Insumo, MovimientoInventario>
     */
    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class);
    }

    /**
     * Obtiene el usuario que provocó el movimiento.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-16
     *
     * @módulo Inventario – US-INV-04 (CC-114)
     *
     * @return BelongsTo<User, MovimientoInventario>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Obtiene el egreso (factura del proveedor) al que se vincula una entrada a bodega.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-16
     *
     * @módulo Inventario – US-INV-04 (CC-114)
     *
     * @return BelongsTo<Egreso, MovimientoInventario>
     */
    public function egreso(): BelongsTo
    {
        return $this->belongsTo(Egreso::class);
    }

    /**
     * Obtiene la comanda pagada que originó un movimiento de venta.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-16
     *
     * @módulo Inventario – US-INV-04 (CC-114)
     *
     * @return BelongsTo<Comanda, MovimientoInventario>
     */
    public function comanda(): BelongsTo
    {
        return $this->belongsTo(Comanda::class);
    }

    /**
     * Obtiene la requisición transferida que originó un movimiento de requisición.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-16
     *
     * @módulo Inventario – US-INV-04 (CC-114)
     *
     * @return BelongsTo<Requisicion, MovimientoInventario>
     */
    public function requisicion(): BelongsTo
    {
        return $this->belongsTo(Requisicion::class);
    }
}
