<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Platillo extends Model
{
    protected $fillable = [
        'nombre',
        'descripcion',
        'precio_unitario',
        'disponible',
    ];

    protected $casts = [
        'precio_unitario' => 'decimal:2',
        'disponible' => 'boolean',
    ];

    /**
     * Obtiene los detalles de comanda donde se ha vendido este platillo.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-17
     * @módulo POS – RF-POS-001
     *
     * @return HasMany<ComandaDetalle>
     */
    public function comandaDetalles(): HasMany
    {
        return $this->hasMany(ComandaDetalle::class);
    }
}
