<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetiroParcial extends Model
{
    protected $table = 'retiros_parciales';

    protected $fillable = [
        'usuario_id',
        'monto',
        'motivo',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    /**
     * Obtiene el usuario (cajero) que realizó el retiro.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-002
     *
     * @return BelongsTo<User, RetiroParcial>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
