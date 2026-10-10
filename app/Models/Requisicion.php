<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Requisicion extends Model
{
    protected $fillable = [
        'usuario_solicita_id',
        'usuario_aprueba_id',
        'estado',
        'observaciones',
        'fecha_resolucion',
    ];

    protected $casts = [
        'fecha_resolucion' => 'datetime',
    ];

    /**
     * Obtiene el usuario que solicitó la requisición.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-10
     * @módulo Inventario – RF-INV-004
     *
     * @return BelongsTo<User, Requisicion>
     */
    public function usuarioSolicita(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_solicita_id');
    }

    /**
     * Obtiene el usuario que aprobó o rechazó la requisición.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-10
     * @módulo Inventario – RF-INV-004
     *
     * @return BelongsTo<User, Requisicion>
     */
    public function usuarioAprueba(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_aprueba_id');
    }

    /**
     * Obtiene el detalle de insumos y cantidades de la requisición.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-10
     * @módulo Inventario – RF-INV-004
     *
     * @return HasMany<RequisicionDetalle>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(RequisicionDetalle::class);
    }
}
