<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receta extends Model
{
    protected $fillable = [
        'platillo_id',
        'insumo_id',
        'cantidad_por_porcion',
    ];

    protected $casts = [
        'cantidad_por_porcion' => 'decimal:2',
    ];

    /**
     * Obtiene el platillo al que pertenece este renglón de receta.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-10
     * @módulo Inventario – RF-INV-003
     *
     * @return BelongsTo<Platillo, Receta>
     */
    public function platillo(): BelongsTo
    {
        return $this->belongsTo(Platillo::class);
    }

    /**
     * Obtiene el insumo requerido en este renglón de receta.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-10
     * @módulo Inventario – RF-INV-003
     *
     * @return BelongsTo<Insumo, Receta>
     */
    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class);
    }
}
