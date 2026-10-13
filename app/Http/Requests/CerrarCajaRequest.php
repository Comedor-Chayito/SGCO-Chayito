<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CerrarCajaRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     * La autorización formal RBAC por rol se implementa en US-ADM-02.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-09-22
     *
     * @módulo POS – RF-POS-002 / CC-41
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para el cierre de caja.
     * Los totales de ventas y retiros se calculan en el servicio, no se reciben del frontend.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-09-22
     *
     * @módulo POS – RF-POS-002 / CC-41
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date', 'before_or_equal:today', 'unique:cierres_caja,fecha'],
            'saldo_inicial' => ['required', 'numeric', 'min:0'],
            'saldo_final' => ['required', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-09-22
     *
     * @módulo POS – RF-POS-002 / CC-41
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha.required' => 'La fecha del cierre es obligatoria.',
            'fecha.date' => 'La fecha debe tener un formato válido.',
            'fecha.before_or_equal' => 'No se puede registrar un cierre para una fecha futura.',
            'fecha.unique' => 'Ya existe un cierre registrado para esta fecha.',
            'saldo_inicial.required' => 'El saldo inicial es obligatorio.',
            'saldo_inicial.numeric' => 'El saldo inicial debe ser un valor numérico.',
            'saldo_inicial.min' => 'El saldo inicial no puede ser negativo.',
            'saldo_final.required' => 'El saldo final es obligatorio.',
            'saldo_final.numeric' => 'El saldo final debe ser un valor numérico.',
            'saldo_final.min' => 'El saldo final no puede ser negativo.',
            'observaciones.max' => 'Las observaciones no pueden exceder 500 caracteres.',
        ];
    }
}
