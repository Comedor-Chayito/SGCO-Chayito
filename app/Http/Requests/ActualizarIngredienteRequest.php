<?php

namespace App\Http\Requests;

use App\Models\Receta;
use App\Services\ConversionService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida los datos de entrada para actualizar la porción de un ingrediente en la receta.
 *
 * @autor  Jeferson De La Cruz
 *
 * @fecha  2026-10-13
 *
 * @módulo Inventario – RF-INV-003 / CC-46
 */
class ActualizarIngredienteRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
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
            'cantidad_por_porcion' => ['required', 'numeric', 'min:0.001'],
            'unidad_consumo' => [
                'required',
                'string',
                'max:20',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $receta = $this->route('receta');

                    if ($receta instanceof Receta && is_string($value)
                        && ! ConversionService::sonCompatibles($value, $receta->insumo->unidad_medida)) {
                        $fail('La unidad de consumo debe ser compatible con la unidad de bodega del insumo.');
                    }
                },
            ],
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
            'cantidad_por_porcion.numeric' => 'La cantidad por porción debe ser un número.',
            'cantidad_por_porcion.min' => 'La cantidad por porción debe ser mayor que cero.',
            'unidad_consumo.required' => 'La unidad de consumo es obligatoria.',
            'unidad_consumo.max' => 'La unidad de consumo no puede superar los 20 caracteres.',
        ];
    }
}
