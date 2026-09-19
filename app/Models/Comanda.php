<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Comanda extends Model
{
    protected $fillable = [
        'mesa_id',
        'usuario_id',
        'canal',
        'estado',
        'observaciones',
        'subtotal',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
    ];

    /**
     * Obtiene la mesa asociada a la comanda, si el canal es "Mesa".
     *
     * @autor  manuelmv15
     * @fecha  2026-09-17
     * @módulo POS – RF-POS-001
     *
     * @return BelongsTo<Mesa, Comanda>
     */
    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class);
    }

    /**
     * Obtiene el usuario (mesero o cajero) que registró la comanda.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-17
     * @módulo POS – RF-POS-001
     *
     * @return BelongsTo<User, Comanda>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Obtiene el detalle de platillos y cantidades de la comanda.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-17
     * @módulo POS – RF-POS-001
     *
     * @return HasMany<ComandaDetalle>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(ComandaDetalle::class);
    }

    /**
     * Obtiene el registro de venta (cobro) asociado a la comanda.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-002
     *
     * @return HasOne<Venta>
     */
    public function venta(): HasOne
    {
        return $this->hasOne(Venta::class);
    }
}
