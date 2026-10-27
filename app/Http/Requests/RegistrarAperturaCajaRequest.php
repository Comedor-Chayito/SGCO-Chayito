<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarAperturaCajaRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     * La autorización formal RBAC por rol se verifica en las rutas / middleware.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-10-15
     *
     * @módulo POS – RF-POS-002 / US-POS-04 (CC-39)
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para el registro de apertura de caja por denominación.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-10-15
     *
     * @módulo POS – RF-POS-002 / US-POS-04 (CC-39)
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fecha' => ['nullable', 'date', 'before_or_equal:today'],
            'denominaciones' => ['required', 'array', 'min:1'],
            'denominaciones.*.denominacion' => ['required', 'numeric', 'min:0.01'],
            'denominaciones.*.tipo' => ['required', 'string', 'in:billete,moneda'],
            'denominaciones.*.cantidad' => ['required', 'integer', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-10-15
     *
     * @módulo POS – RF-POS-002 / US-POS-04 (CC-39)
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha.date' => 'La fecha debe tener un formato válido.',
            'fecha.before_or_equal' => 'No se puede registrar una apertura para una fecha futura.',
            'denominaciones.required' => 'El desglose de denominaciones es obligatorio.',
            'denominaciones.array' => 'El formato de denominaciones debe ser un arreglo.',
            'denominaciones.min' => 'Debe incluir al menos una denominación.',
            'denominaciones.*.denominacion.required' => 'El valor de cada denominación es obligatorio.',
            'denominaciones.*.denominacion.numeric' => 'El valor de la denominación debe ser numérico.',
            'denominaciones.*.tipo.in' => 'El tipo de denominación debe ser billete o moneda.',
            'denominaciones.*.cantidad.required' => 'La cantidad de piezas es obligatoria.',
            'denominaciones.*.cantidad.integer' => 'La cantidad de piezas debe ser un número entero.',
            'denominaciones.*.cantidad.min' => 'La cantidad de piezas no puede ser negativa.',
            'observaciones.max' => 'Las observaciones no pueden exceder 500 caracteres.',
        ];
    }
}
