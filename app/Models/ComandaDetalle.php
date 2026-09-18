<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComandaDetalle extends Model
{
    protected $fillable = [
        'comanda_id',
        'platillo_id',
        'cantidad',
        'precio_unitario',
        'observaciones',
    ];

    protected $casts = [
        'precio_unitario' => 'decimal:2',
    ];

    /**
     * Obtiene la comanda a la que pertenece este detalle.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-17
     * @módulo POS – RF-POS-001
     *
     * @return BelongsTo<Comanda, ComandaDetalle>
     */
    public function comanda(): BelongsTo
    {
        return $this->belongsTo(Comanda::class);
    }

    /**
     * Obtiene el platillo vendido en este renglón de la comanda.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-17
     * @módulo POS – RF-POS-001
     *
     * @return BelongsTo<Platillo, ComandaDetalle>
     */
    public function platillo(): BelongsTo
    {
        return $this->belongsTo(Platillo::class);
    }
}
