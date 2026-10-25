<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el rango opcional de fechas con el que se consultan los KPIs gerenciales.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / CC-75 / CC-76
 */
class ConsultarDashboardGerencialRequest extends FormRequest
{
    /**
     * Confirma que la autorización fue resuelta por la ruta protegida por roles.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Reportes – RF-REP-007 / CC-75 / CC-76
     *
     * @return bool True; el middleware admite únicamente socio o administrador.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Define las fechas opcionales para consultar un periodo financiero. Se envían ambas o
     * ninguna: con una sola, el periodo por defecto podría quedar invertido.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Reportes – RF-REP-007 / CC-75 / CC-72
     *
     * @return array<string, mixed> Reglas del rango de fechas.
     */
    public function rules(): array
    {
        return [
            'fecha_desde' => ['nullable', 'required_with:fecha_hasta', 'date', 'before_or_equal:today'],
            'fecha_hasta' => [
                'nullable',
                'required_with:fecha_desde',
                'date',
                'after_or_equal:fecha_desde',
                'before_or_equal:today',
            ],
        ];
    }

    /**
     * Devuelve mensajes de error claros para el filtro temporal del dashboard.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Reportes – RF-REP-007 / CC-75 / CC-72
     *
     * @return array<string, string> Mensajes personalizados de validación.
     */
    public function messages(): array
    {
        return [
            'fecha_desde.required_with' => 'Indica la fecha inicial del periodo.',
            'fecha_hasta.required_with' => 'Indica la fecha final del periodo.',
            'fecha_desde.date' => 'La fecha inicial debe tener un formato válido.',
            'fecha_desde.before_or_equal' => 'La fecha inicial no puede ser futura.',
            'fecha_hasta.date' => 'La fecha final debe tener un formato válido.',
            'fecha_hasta.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
            'fecha_hasta.before_or_equal' => 'La fecha final no puede ser futura.',
        ];
    }
}
