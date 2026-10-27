<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarEgresosRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a consultar el historial de egresos.
     * El acceso por rol (contadora o administrador) lo controla el middleware de la ruta.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Contabilidad – RF-CON-006 (CC-69)
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de los filtros opcionales del historial: rango de fechas y subcategoría.
     * La subcategoría puede estar desactivada, porque sus egresos siguen en el historial.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Contabilidad – RF-CON-006 (CC-69)
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'desde'                  => ['nullable', 'date_format:Y-m-d'],
            'hasta'                  => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'subcategoria_egreso_id' => ['nullable', 'integer', Rule::exists('subcategorias_egreso', 'id')],
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Contabilidad – RF-CON-006 (CC-69)
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'desde.date_format'              => 'La fecha inicial debe tener el formato AAAA-MM-DD.',
            'hasta.date_format'              => 'La fecha final debe tener el formato AAAA-MM-DD.',
            'hasta.after_or_equal'           => 'La fecha final no puede ser anterior a la inicial.',
            'subcategoria_egreso_id.integer' => 'La subcategoría no es válida.',
            'subcategoria_egreso_id.exists'  => 'La subcategoría no existe.',
        ];
    }
}
