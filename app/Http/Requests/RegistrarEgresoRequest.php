<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarEgresoRequest extends FormRequest
{
    public const MONTO_MAXIMO_EGRESO = 99999999.99;

    /**
     * Determina si el usuario está autorizado a registrar egresos.
     * El acceso por rol (contadora o administrador) lo controla el middleware de la ruta.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-005 (CC-63)
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación de la factura de un proveedor registrada como egreso.
     * Límites alineados con la tabla egresos y con FormularioEgreso.vue.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-005 (CC-63)
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subcategoria_egreso_id' => [
                'required',
                'integer',
                Rule::exists('subcategorias_egreso', 'id')->where('activo', true),
            ],
            'proveedor'              => ['required', 'string', 'max:150'],
            'numero_comprobante'     => [
                'required',
                'string',
                'max:50',
                // Un proveedor no puede repetir el mismo comprobante (índice único compuesto)
                Rule::unique('egresos', 'numero_comprobante')->where('proveedor', $this->input('proveedor')),
            ],
            'fecha_comprobante'      => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'monto_total'            => [
                'required',
                'numeric',
                'min:0.01',
                'max:' . self::MONTO_MAXIMO_EGRESO,
                'decimal:0,2',
            ],
            'descripcion'            => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-005 (CC-63)
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subcategoria_egreso_id.required'   => 'Elige una subcategoría.',
            'subcategoria_egreso_id.integer'    => 'La subcategoría no es válida.',
            'subcategoria_egreso_id.exists'     => 'La subcategoría no existe o está desactivada.',
            'proveedor.required'                => 'El proveedor es obligatorio.',
            'proveedor.max'                     => 'El proveedor no puede superar 150 caracteres.',
            'numero_comprobante.required'       => 'El número de comprobante es obligatorio.',
            'numero_comprobante.max'            => 'El número de comprobante no puede superar 50 caracteres.',
            'numero_comprobante.unique'         => 'Esta factura de este proveedor ya fue registrada.',
            'fecha_comprobante.required'        => 'La fecha es obligatoria.',
            'fecha_comprobante.date_format'     => 'La fecha debe tener el formato AAAA-MM-DD.',
            'fecha_comprobante.before_or_equal' => 'La fecha no puede ser futura.',
            'monto_total.required'              => 'El monto es obligatorio.',
            'monto_total.numeric'               => 'El monto debe ser un valor numérico.',
            'monto_total.min'                   => 'El monto debe ser mayor a $0.00.',
            'monto_total.max'                   => 'El monto no puede superar $99,999,999.99.',
            'monto_total.decimal'               => 'El monto admite como máximo 2 decimales.',
            'descripcion.max'                   => 'La descripción no puede superar 500 caracteres.',
        ];
    }
}
