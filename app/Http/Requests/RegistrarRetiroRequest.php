<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarRetiroRequest extends FormRequest
{
    public const MONTO_MAXIMO_RETIRO = 99999.99;

    /**
     * Determina si el usuario está autorizado a registrar retiros.
     * El acceso por rol (cajero o administrador) lo controla el middleware de la ruta.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-14
     * @módulo POS – US-POS-03 / RF-POS-002 (CC-37)
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación de un retiro parcial de efectivo.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-14
     * @módulo POS – US-POS-03 / RF-POS-002 (CC-37)
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'monto'  => ['required', 'numeric', 'min:0.01', 'max:' . self::MONTO_MAXIMO_RETIRO, 'decimal:0,2'],
            'motivo' => ['required', 'string', 'min:3', 'max:150'],
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-14
     * @módulo POS – US-POS-03 / RF-POS-002 (CC-37)
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'monto.required'  => 'El monto es obligatorio.',
            'monto.numeric'   => 'El monto debe ser un valor numérico.',
            'monto.min'       => 'El monto debe ser mayor a $0.00.',
            'monto.max'       => 'El monto no puede superar $99,999.99.',
            'monto.decimal'   => 'El monto admite como máximo 2 decimales.',
            'motivo.required' => 'El motivo es obligatorio.',
            'motivo.min'      => 'El motivo debe tener al menos 3 caracteres.',
            'motivo.max'      => 'El motivo no puede superar 150 caracteres.',
        ];
    }
}
