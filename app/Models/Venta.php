<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Venta extends Model
{
    protected $table = 'ventas';

    protected $fillable = [
        'comanda_id',
        'usuario_id',
        'total',
        'monto_recibido',
        'cambio',
        'metodo_pago',
    ];

    protected $casts = [
        'total'          => 'decimal:2',
        'monto_recibido' => 'decimal:2',
        'cambio'         => 'decimal:2',
    ];

    /**
     * Obtiene la comanda asociada a la venta.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-002
     *
     * @return BelongsTo<Comanda, Venta>
     */
    public function comanda(): BelongsTo
    {
        return $this->belongsTo(Comanda::class);
    }

    /**
     * Obtiene el usuario (cajero) que procesó el cobro de la venta.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-002
     *
     * @return BelongsTo<User, Venta>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

