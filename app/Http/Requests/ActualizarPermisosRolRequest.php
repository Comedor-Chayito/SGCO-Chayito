<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarPermisosRolRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta petición.
     * Solo los administradores pueden modificar los permisos asignados a un rol (US-ADM-04 / CC-122).
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 / CC-122
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('administrador') ?? false;
    }

    /**
     * Reglas de validación para actualizar los permisos de un rol.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 / CC-122
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'permisos' => ['present', 'array'],
            'permisos.*' => ['string', 'exists:permissions,name'],
        ];
    }

    /**
     * Mensajes descriptivos en español para los errores de validación.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 / CC-122
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'permisos.present' => 'La lista de permisos es obligatoria.',
            'permisos.required' => 'La lista de permisos es obligatoria.',
            'permisos.array' => 'Los permisos deben enviarse como un arreglo.',
            'permisos.*.string' => 'Cada permiso debe ser una cadena de texto válida.',
            'permisos.*.exists' => 'Uno o más permisos especificados no existen en el catálogo oficial del sistema.',
        ];
    }
}
