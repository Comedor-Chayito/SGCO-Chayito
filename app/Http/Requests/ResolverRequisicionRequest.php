<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la aprobación o el rechazo de una requisición de insumos.
 *
 * @autor  Alexis
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-53
 */
class ResolverRequisicionRequest extends FormRequest
{
    /**
     * Determina si un administrador puede resolver la requisición solicitada.
     *
     * @autor  Alexis
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-53
     *
     * @return bool True si el usuario posee el rol administrador.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('administrador') ?? false;
    }

    /**
     * Define cantidades aprobadas obligatorias cuando la decisión es aprobar.
     *
     * @autor  Alexis
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-53
     *
     * @return array<string, mixed> Reglas de validación de la resolución.
     */
    public function rules(): array
    {
        return [
            'estado' => ['required', 'string', 'in:aprobada,rechazada'],
            'detalles' => ['required_if:estado,aprobada', 'array', 'min:1'],
            'detalles.*.id' => ['required_if:estado,aprobada', 'integer', 'distinct'],
            'detalles.*.cantidad_aprobada' => [
                'required_if:estado,aprobada',
                'numeric',
                'gt:0',
                'decimal:0,2',
            ],
        ];
    }

    /**
     * Devuelve mensajes en español para los errores de aprobación.
     *
     * @autor  Alexis
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-53
     *
     * @return array<string, string> Mensajes personalizados por regla.
     */
    public function messages(): array
    {
        return [
            'estado.required' => 'Indica si la requisición será aprobada o rechazada.',
            'estado.in' => 'La decisión debe ser aprobada o rechazada.',
            'detalles.required_if' => 'Indica las cantidades aprobadas de cada insumo.',
            'detalles.min' => 'Indica al menos un insumo aprobado.',
            'detalles.*.id.required_if' => 'Cada renglón aprobado debe identificar su detalle.',
            'detalles.*.id.distinct' => 'No repitas un renglón en la aprobación.',
            'detalles.*.cantidad_aprobada.required_if' => 'Indica la cantidad aprobada.',
            'detalles.*.cantidad_aprobada.numeric' => 'La cantidad aprobada debe ser numérica.',
            'detalles.*.cantidad_aprobada.gt' => 'La cantidad aprobada debe ser mayor que cero.',
            'detalles.*.cantidad_aprobada.decimal' => 'La cantidad aprobada admite como máximo dos decimales.',
        ];
    }
}
