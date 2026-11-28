<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida los filtros opcionales de la consulta de la bitácora de auditoría.
 *
 * @autor  manuelmv15
 *
 * @fecha  2026-11-17
 *
 * @módulo Administración – US-ADM-04 / CC-124
 */
class ListarAuditoriasRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a consultar la bitácora.
     * El acceso por rol (administrador o socio) lo controla el middleware de la ruta.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – US-ADM-04 / CC-124
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de los filtros: usuario, acción, entidad, rango de fechas y tamaño de página.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – US-ADM-04 / CC-124
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'usuario_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'accion' => ['nullable', 'string', 'max:30'],
            'entidad' => ['nullable', 'string', 'max:60'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – US-ADM-04 / CC-124
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'usuario_id.integer' => 'El usuario no es válido.',
            'usuario_id.exists' => 'El usuario no existe.',
            'accion.max' => 'La acción no puede superar los 30 caracteres.',
            'entidad.max' => 'La entidad no puede superar los 60 caracteres.',
            'desde.date_format' => 'La fecha inicial debe tener el formato AAAA-MM-DD.',
            'hasta.date_format' => 'La fecha final debe tener el formato AAAA-MM-DD.',
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
            'por_pagina.integer' => 'La cantidad por página debe ser un número entero.',
            'por_pagina.min' => 'La cantidad por página debe ser al menos 1.',
            'por_pagina.max' => 'La cantidad por página no puede superar 100.',
        ];
    }
}
