<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Representa una compra registrada con su costo neto e IVA crédito fiscal.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
 */
class Compra extends Model
{
    /**
     * Tabla física de los comprobantes de compra.
     *
     * @var string
     */
    protected $table = 'compras';

    /**
     * Atributos asignables de un comprobante de compra calculado.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'usuario_id',
        'fecha_compra',
        'monto_total',
        'costo_neto',
        'iva_credito_fiscal',
    ];

    /**
     * Convierte fechas y montos a tipos consistentes para la contabilidad.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_compra' => 'date:Y-m-d',
            'monto_total' => 'decimal:2',
            'costo_neto' => 'decimal:2',
            'iva_credito_fiscal' => 'decimal:2',
        ];
    }

    /**
     * Obtiene a la persona que registró la compra en el sistema.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
     *
     * @return BelongsTo<User, Compra>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
