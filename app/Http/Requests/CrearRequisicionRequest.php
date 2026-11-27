<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida una solicitud interna de insumos para un área operativa.
 *
 * @autor  Jeferson De La Cruz
 *
 * @fecha  2026-10-22
 *
 * @módulo Inventario – RF-INV-004 / CC-52
 */
class CrearRequisicionRequest extends FormRequest
{
    /**
     * Determina si el usuario puede crear requisiciones internas de insumos.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-10-22
     *
     * @módulo Inventario – RF-INV-004 / CC-52
     *
     * @return bool True si tiene un rol autorizado para solicitar insumos.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['cocinero', 'cocinera', 'administrador', 'socio']) ?? false;
    }

    /**
     * Define las reglas para crear una requisición con al menos un insumo.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-10-22
     *
     * @módulo Inventario – RF-INV-004 / CC-52
     *
     * @return array<string, mixed> Reglas de validación de la solicitud.
     */
    public function rules(): array
    {
        return [
            'area_solicitante' => ['required', 'string', 'in:cocina,servicio,despacho'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'detalles' => ['required', 'array', 'min:1', 'max:30'],
            'detalles.*.insumo_id' => ['required', 'integer', 'distinct', 'exists:insumos,id'],
            'detalles.*.cantidad_solicitada' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
        ];
    }

    /**
     * Devuelve mensajes de validación comprensibles para el formulario móvil.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-10-22
     *
     * @módulo Inventario – RF-INV-004 / CC-52
     *
     * @return array<string, string> Mensajes personalizados por regla.
     */
    public function messages(): array
    {
        return [
            'area_solicitante.required' => 'Selecciona el área que solicita los insumos.',
            'area_solicitante.in' => 'El área solicitante no es válida.',
            'detalles.required' => 'Agrega al menos un insumo a la requisición.',
            'detalles.min' => 'Agrega al menos un insumo a la requisición.',
            'detalles.*.insumo_id.required' => 'Selecciona un insumo en cada renglón.',
            'detalles.*.insumo_id.distinct' => 'No repitas un insumo en la misma requisición.',
            'detalles.*.insumo_id.exists' => 'Uno de los insumos seleccionados ya no existe.',
            'detalles.*.cantidad_solicitada.required' => 'Indica la cantidad solicitada.',
            'detalles.*.cantidad_solicitada.numeric' => 'La cantidad solicitada debe ser numérica.',
            'detalles.*.cantidad_solicitada.gt' => 'La cantidad solicitada debe ser mayor que cero.',
            'detalles.*.cantidad_solicitada.decimal' => 'La cantidad solicitada admite como máximo dos decimales.',
            'observaciones.max' => 'Las observaciones no pueden superar 1000 caracteres.',
        ];
    }
}
