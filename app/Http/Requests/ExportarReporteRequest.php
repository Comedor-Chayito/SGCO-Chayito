<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el período y el formato con que se exporta el reporte consolidado.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-24
 * @módulo Reportes – Sec-3.5.1 / CC-79
 */
class ExportarReporteRequest extends FormRequest
{
    /**
     * Confirma que la autorización fue resuelta por la ruta protegida por roles.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – Sec-3.5.1 / CC-79
     *
     * @return bool True; el middleware admite contadora, socio o administrador.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Define el período obligatorio y los formatos de archivo disponibles.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – Sec-3.5.1 / CC-79 / CC-80
     *
     * @return array<string, mixed> Reglas del período y del formato.
     */
    public function rules(): array
    {
        return [
            'fecha_desde' => ['required', 'date', 'before_or_equal:today'],
            'fecha_hasta' => ['required', 'date', 'after_or_equal:fecha_desde', 'before_or_equal:today'],
            'formato' => ['required', 'in:csv,xlsx'],
        ];
    }

    /**
     * Devuelve mensajes de error claros para la pantalla de exportación.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – Sec-3.5.1 / CC-79
     *
     * @return array<string, string> Mensajes personalizados de validación.
     */
    public function messages(): array
    {
        return [
            'fecha_desde.required' => 'Indica la fecha inicial del período.',
            'fecha_desde.date' => 'La fecha inicial debe tener un formato válido.',
            'fecha_desde.before_or_equal' => 'La fecha inicial no puede ser futura.',
            'fecha_hasta.required' => 'Indica la fecha final del período.',
            'fecha_hasta.date' => 'La fecha final debe tener un formato válido.',
            'fecha_hasta.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
            'fecha_hasta.before_or_equal' => 'La fecha final no puede ser futura.',
            'formato.required' => 'Indica el formato del archivo.',
            'formato.in' => 'El formato solicitado no está disponible.',
        ];
    }
}
