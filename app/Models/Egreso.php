<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Egreso extends Model
{
    protected $table = 'egresos';

    protected $fillable = [
        'correlativo',
        'subcategoria_egreso_id',
        'usuario_id',
        'proveedor',
        'numero_comprobante',
        'fecha_comprobante',
        'monto_total',
        'descripcion',
    ];

    protected $casts = [
        'fecha_comprobante' => 'date',
        'monto_total' => 'decimal:2',
    ];

    /**
     * Obtiene la subcategoría con que se clasificó el egreso.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-005
     *
     * @return BelongsTo<SubcategoriaEgreso, Egreso>
     */
    public function subcategoria(): BelongsTo
    {
        return $this->belongsTo(SubcategoriaEgreso::class, 'subcategoria_egreso_id');
    }

    /**
     * Obtiene el usuario que registró el egreso.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-005
     *
     * @return BelongsTo<User, Egreso>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
