<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AperturaCaja extends Model
{
    /**
     * El registro de apertura es inalterable: no se registra fecha de actualización.
     */
    public const UPDATED_AT = null;

    protected $table = 'aperturas_caja';

    protected $fillable = [
        'usuario_id',
        'fecha',
        'saldo_inicial',
        'denominaciones',
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
            'denominaciones' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Obtiene el usuario (cajero/administrador) que realizó la apertura de caja.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-10-15
     *
     * @módulo POS – RF-POS-002 / US-POS-04 (CC-39)
     *
     * @return BelongsTo<User, AperturaCaja>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
