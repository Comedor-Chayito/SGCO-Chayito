<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Representa la existencia disponible de un insumo dentro de un área operativa.
 *
 * @autor  Alexis
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-54
 */
class ExistenciaAreaOperativa extends Model
{
    /**
     * Tabla física de existencias por área operativa.
     *
     * @var string
     */
    protected $table = 'existencias_area_operativa';

    /**
     * Atributos asignables para registrar transferencias de bodega.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'area',
        'insumo_id',
        'stock_actual',
    ];

    /**
     * Convierte el stock a una representación decimal sin pérdida de precisión.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'stock_actual' => 'decimal:6',
    ];

    /**
     * Obtiene el insumo cuyo stock se encuentra en esta área.
     *
     * @autor  Alexis
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-54
     *
     * @return BelongsTo<Insumo, ExistenciaAreaOperativa>
     */
    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class);
    }
}
