<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida los filtros del catálogo administrativo de platillos.
 *
 * @autor  Jeferson De La Cruz
 *
 * @fecha  2026-11-17
 *
 * @módulo Administración – RF-ADM-008 / CC-104 (US-ADM-03)
 */
class ListarPlatillosRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     * El acceso por rol (administrador|socio) lo aplica el middleware de la ruta.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-104 (US-ADM-03)
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación de los filtros ?busqueda= y ?disponible=.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-104 (US-ADM-03)
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'busqueda' => ['nullable', 'string', 'max:100'],
            'disponible' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Mensajes de validación en español.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-104 (US-ADM-03)
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'busqueda.string' => 'La búsqueda debe ser un texto.',
            'busqueda.max' => 'La búsqueda no puede superar los 100 caracteres.',
            'disponible.boolean' => 'El filtro de disponibilidad debe ser verdadero o falso (1/0).',
        ];
    }
}
