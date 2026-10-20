<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Insumo extends Model
{
    protected $fillable = [
        'nombre',
        'unidad_medida',
        'stock_actual',
        'stock_minimo',
    ];

    protected $casts = [
        'stock_actual' => 'decimal:6',
        'stock_minimo' => 'decimal:6',
    ];

    /**
     * Obtiene los renglones de receta donde se usa este insumo.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-10-10
     *
     * @módulo Inventario – RF-INV-003
     *
     * @return HasMany<Receta>
     */
    public function recetas(): HasMany
    {
        return $this->hasMany(Receta::class);
    }

    /**
     * Obtiene los renglones de requisición donde se solicita este insumo.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-10-10
     *
     * @módulo Inventario – RF-INV-004
     *
     * @return HasMany<RequisicionDetalle>
     */
    public function requisicionDetalles(): HasMany
    {
        return $this->hasMany(RequisicionDetalle::class);
    }
}
