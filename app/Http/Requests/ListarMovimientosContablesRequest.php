<?php

namespace App\Http\Requests;

use App\Services\LibroMovimientosService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarMovimientosContablesRequest extends FormRequest
{
    /**
     * Determina si el usuario puede consultar el libro de movimientos.
     * El acceso por rol (contadora, administrador o socio) lo controla el middleware de la ruta.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-18
     * @módulo Contabilidad – RF-CON-006
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de los filtros opcionales del libro: rango de fechas y tipo de movimiento.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-18
     * @módulo Contabilidad – RF-CON-006
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'tipo'  => [
                'nullable',
                Rule::in([LibroMovimientosService::TIPO_INGRESO, LibroMovimientosService::TIPO_EGRESO]),
            ],
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-18
     * @módulo Contabilidad – RF-CON-006
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'desde.date_format'    => 'La fecha inicial debe tener el formato AAAA-MM-DD.',
            'hasta.date_format'    => 'La fecha final debe tener el formato AAAA-MM-DD.',
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
            'tipo.in'              => 'El tipo de movimiento debe ser ingreso o egreso.',
        ];
    }
}
