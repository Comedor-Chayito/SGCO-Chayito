<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el cambio de disponibilidad de un platillo en el menú del día.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-11-17
 * @módulo Administración – RF-ADM-008 / CC-106 (US-ADM-03)
 */
class CambiarDisponibilidadPlatilloRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     * El acceso por rol (administrador|socio) lo aplica el middleware de la ruta.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-17
     * @módulo Administración – RF-ADM-008 / CC-106 (US-ADM-03)
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación del cambio de disponibilidad.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-17
     * @módulo Administración – RF-ADM-008 / CC-106 (US-ADM-03)
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'disponible' => ['required', 'boolean'],
        ];
    }

    /**
     * Mensajes de validación en español.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-17
     * @módulo Administración – RF-ADM-008 / CC-106 (US-ADM-03)
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'disponible.required' => 'Debe indicar si el platillo está disponible o no.',
            'disponible.boolean'  => 'La disponibilidad debe ser verdadero o falso.',
        ];
    }
}
