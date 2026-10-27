<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el importe total de una compra antes de calcular su IVA crédito fiscal.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
 */
class RegistrarCompraRequest extends FormRequest
{
    /**
     * Confirma que el acceso por rol fue aplicado por la ruta protegida.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
     *
     * @return bool True; el middleware restringe a contadora o administrador.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Define los datos mínimos de un comprobante para ejecutar el cálculo automático.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
     *
     * @return array<string, mixed> Reglas para fecha y monto total de la compra.
     */
    public function rules(): array
    {
        return [
            'fecha_compra' => ['required', 'date', 'before_or_equal:today'],
            'monto_total' => ['required', 'numeric', 'gt:0', 'max:99999999.99', 'decimal:0,2'],
        ];
    }

    /**
     * Presenta mensajes de validación comprensibles para la persona contadora.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
     *
     * @return array<string, string> Mensajes personalizados de validación.
     */
    public function messages(): array
    {
        return [
            'fecha_compra.required' => 'La fecha de compra es obligatoria.',
            'fecha_compra.date' => 'La fecha de compra debe tener un formato válido.',
            'fecha_compra.before_or_equal' => 'No se puede registrar una compra con fecha futura.',
            'monto_total.required' => 'El monto total de la compra es obligatorio.',
            'monto_total.numeric' => 'El monto total debe ser numérico.',
            'monto_total.gt' => 'El monto total debe ser mayor que cero.',
            'monto_total.max' => 'El monto total no puede superar $99,999,999.99.',
            'monto_total.decimal' => 'El monto total admite como máximo dos decimales.',
        ];
    }
}
