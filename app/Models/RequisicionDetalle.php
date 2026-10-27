<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisicionDetalle extends Model
{
    protected $fillable = [
        'requisicion_id',
        'insumo_id',
        'cantidad_solicitada',
        'cantidad_aprobada',
    ];

    protected $casts = [
        'cantidad_solicitada' => 'decimal:3',
        'cantidad_aprobada' => 'decimal:3',
    ];

    /**
     * Obtiene la requisición a la que pertenece este renglón.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-10
     * @módulo Inventario – RF-INV-004
     *
     * @return BelongsTo<Requisicion, RequisicionDetalle>
     */
    public function requisicion(): BelongsTo
    {
        return $this->belongsTo(Requisicion::class);
    }

    /**
     * Obtiene el insumo solicitado en este renglón de la requisición.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-10
     * @módulo Inventario – RF-INV-004
     *
     * @return BelongsTo<Insumo, RequisicionDetalle>
     */
    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class);
    }
}
