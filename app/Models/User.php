<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Modelo de Usuario del Sistema con autenticación Sanctum y RBAC Spatie (US-ADM-01 / US-ADM-02).
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-20
 * @módulo Administración y Seguridad – RF-ADM-008
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'activo',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Atributos virtuales computados adjuntos al serializar a JSON.
     *
     * @var list<string>
     */
    protected $appends = [
        'rol',
        'permisos',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    /**
     * Accesor para obtener el rol principal asignado al usuario.
     *
     * @return string|null
     */
    public function getRolAttribute(): ?string
    {
        return $this->roles->first()?->name;
    }

    /**
     * Accesor para obtener todos los nombres de permisos asociados al usuario.
     *
     * @return array<string>
     */
    public function getPermisosAttribute(): array
    {
        return $this->getAllPermissions()->pluck('name')->toArray();
    }
}
