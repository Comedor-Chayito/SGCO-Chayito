<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el número y la capacidad de una mesa existente.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-17
 * @módulo Administración – US-ADM-03 / CC-107
 */
class ActualizarMesaRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     * El control de rol lo hace el middleware de la ruta.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-17
     * @módulo Administración – US-ADM-03 / CC-107
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para editar una mesa; la unicidad ignora a la propia mesa.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-17
     * @módulo Administración – US-ADM-03 / CC-107
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'numero'    => [
                'required',
                'integer',
                'min:1',
                Rule::unique('mesas', 'numero')->ignore($this->route('mesa')),
            ],
            'capacidad' => ['nullable', 'integer', 'min:1', 'max:255'],
        ];
    }

    /**
     * Mensajes de validación en español.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-17
     * @módulo Administración – US-ADM-03 / CC-107
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'numero.required'   => 'El número de mesa es obligatorio.',
            'numero.integer'    => 'El número de mesa debe ser un número entero.',
            'numero.min'        => 'El número de mesa debe ser mayor que cero.',
            'numero.unique'     => 'Ya existe una mesa con ese número.',
            'capacidad.integer' => 'La capacidad debe ser un número entero.',
            'capacidad.min'     => 'La capacidad debe ser de al menos 1 persona.',
            'capacidad.max'     => 'La capacidad no puede superar las 255 personas.',
        ];
    }
}
