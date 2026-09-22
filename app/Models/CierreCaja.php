<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CierreCaja extends Model
{
    /**
     * La bitácora de cierre es inalterable: no se registra fecha de actualización.
     */
    public const UPDATED_AT = null;

    protected $table = 'cierres_caja';

    protected $fillable = [
        'usuario_id',
        'fecha',
        'saldo_inicial',
        'total_ventas_efectivo',
        'total_ventas_transferencia',
        'total_retiros',
        'saldo_final',
        'venta_del_dia',
        'diferencia',
        'observaciones',
    ];

    /**
     * Tipos de datos para el casting automático de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'saldo_inicial' => 'decimal:2',
            'total_ventas_efectivo' => 'decimal:2',
            'total_ventas_transferencia' => 'decimal:2',
            'total_retiros' => 'decimal:2',
            'saldo_final' => 'decimal:2',
            'venta_del_dia' => 'decimal:2',
            'diferencia' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Obtiene el usuario (cajero/administrador) que realizó el cierre de caja.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-09-22
     *
     * @módulo POS – RF-POS-002 / CC-41
     *
     * @return BelongsTo<User, CierreCaja>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
