<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida los datos de entrada para crear una subcategoría de egreso desde el panel de configuración.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-17
 * @módulo Administración – US-ADM-03 / CC-108
 */
class CrearSubcategoriaEgresoRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     * El control de rol lo hace el middleware de la ruta.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-17
     * @módulo Administración – US-ADM-03 / CC-108
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para crear una subcategoría de egreso.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-17
     * @módulo Administración – US-ADM-03 / CC-108
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:80', 'unique:subcategorias_egreso,nombre'],
        ];
    }

    /**
     * Mensajes de validación en español.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-17
     * @módulo Administración – US-ADM-03 / CC-108
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la subcategoría es obligatorio.',
            'nombre.string'   => 'El nombre de la subcategoría debe ser texto.',
            'nombre.max'      => 'El nombre de la subcategoría no puede superar los 80 caracteres.',
            'nombre.unique'   => 'Ya existe una subcategoría de egreso con ese nombre.',
        ];
    }
}
