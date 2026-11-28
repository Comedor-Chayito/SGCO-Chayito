<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el nuevo nombre de una subcategoría de egreso existente.
 *
 * @autor  manuelmv15
 *
 * @fecha  2026-11-17
 *
 * @módulo Administración – US-ADM-03 / CC-108
 */
class RenombrarSubcategoriaEgresoRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     * El control de rol lo hace el middleware de la ruta.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – US-ADM-03 / CC-108
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para renombrar; la unicidad ignora a la propia subcategoría.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – US-ADM-03 / CC-108
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => [
                'required',
                'string',
                'max:80',
                Rule::unique('subcategorias_egreso', 'nombre')->ignore($this->route('subcategoriaEgreso')),
            ],
        ];
    }

    /**
     * Mensajes de validación en español.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – US-ADM-03 / CC-108
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la subcategoría es obligatorio.',
            'nombre.string' => 'El nombre de la subcategoría debe ser texto.',
            'nombre.max' => 'El nombre de la subcategoría no puede superar los 80 caracteres.',
            'nombre.unique' => 'Ya existe una subcategoría de egreso con ese nombre.',
        ];
    }
}
