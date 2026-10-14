<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida los datos de entrada para crear un nuevo insumo de bodega.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003 / CC-45
 */
class CrearInsumoRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para la creación de un insumo.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre'        => ['required', 'string', 'max:100'],
            'unidad_medida' => ['required', 'string', 'max:20'],
            'stock_actual'  => ['nullable', 'numeric', 'min:0'],
            'stock_minimo'  => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Mensajes de validación en español.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required'        => 'El nombre del insumo es obligatorio.',
            'nombre.max'             => 'El nombre del insumo no puede superar los 100 caracteres.',
            'unidad_medida.required' => 'La unidad de medida es obligatoria (ej: kg, lt, unidad).',
            'unidad_medida.max'      => 'La unidad de medida no puede superar los 20 caracteres.',
            'stock_actual.numeric'   => 'El stock actual debe ser un número.',
            'stock_actual.min'       => 'El stock actual no puede ser negativo.',
            'stock_minimo.numeric'   => 'El stock mínimo debe ser un número.',
            'stock_minimo.min'       => 'El stock mínimo no puede ser negativo.',
        ];
    }
}
