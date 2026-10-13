<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida los datos de entrada para actualizar la porción de un ingrediente en la receta.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003 / CC-46
 */
class ActualizarIngredienteRequest extends FormRequest
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
     * Reglas de validación para actualizar la porción base de un ingrediente.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cantidad_por_porcion' => ['required', 'numeric', 'min:0.01'],
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
            'cantidad_por_porcion.required' => 'La cantidad por porción es obligatoria.',
            'cantidad_por_porcion.numeric'  => 'La cantidad por porción debe ser un número.',
            'cantidad_por_porcion.min'      => 'La cantidad por porción debe ser mayor que cero.',
        ];
    }
}
