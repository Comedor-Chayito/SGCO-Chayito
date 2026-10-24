<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubcategoriaEgreso extends Model
{
    protected $table = 'subcategorias_egreso';

    protected $fillable = [
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Obtiene los egresos clasificados en esta subcategoría.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-005
     *
     * @return HasMany<Egreso>
     */
    public function egresos(): HasMany
    {
        return $this->hasMany(Egreso::class);
    }
}
