<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Auditoria extends Model
{
    public const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',
        'accion',
        'entidad_type',
        'entidad_id',
        'datos_anteriores',
        'datos_nuevos',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'datos_anteriores' => 'array',
            'datos_nuevos' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Usuario que ejecutó la acción auditada. Nulo cuando el cambio se
     * originó sin sesión autenticada (por ejemplo, un seeder).
     *
     * @autor  manuelmv15
     * @fecha  2026-09-20
     * @módulo Administración y Seguridad – RF-ADM-008 / CC-87
     *
     * @return BelongsTo<User, Auditoria>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Entidad sobre la que se registró el cambio (por ejemplo, un usuario).
     *
     * @autor  manuelmv15
     * @fecha  2026-09-20
     * @módulo Administración y Seguridad – RF-ADM-008 / CC-87
     *
     * @return MorphTo
     */
    public function entidad(): MorphTo
    {
        return $this->morphTo();
    }
}
