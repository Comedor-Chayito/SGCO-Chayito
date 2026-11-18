<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'costo_neto',
        'iva_credito_fiscal',
        'descripcion',
    ];

    protected $casts = [
        'fecha_comprobante' => 'date:Y-m-d',
        'monto_total' => 'decimal:2',
        'costo_neto' => 'decimal:2',
        'iva_credito_fiscal' => 'decimal:2',
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

    /**
     * Obtiene las entradas a bodega registradas a partir de este egreso.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-16
     * @módulo Inventario – US-INV-04 (CC-114)
     *
     * @return HasMany<MovimientoInventario>
     */
    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class);
    }
}
