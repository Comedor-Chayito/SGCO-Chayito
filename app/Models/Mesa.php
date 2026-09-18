<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mesa extends Model
{
    protected $fillable = [
        'numero',
        'capacidad',
        'estado',
    ];

    /**
     * Obtiene las comandas registradas para esta mesa.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-17
     * @módulo POS – RF-POS-001
     *
     * @return HasMany<Comanda>
     */
    public function comandas(): HasMany
    {
        return $this->hasMany(Comanda::class);
    }
}
