<?php

namespace App\Http\Requests;

use App\Models\Platillo;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class RegistrarComandaRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a hacer esta solicitud.
     * La autorización por rol se implementa en US-ADM-02 con Sanctum.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-09-18
     * @módulo POS – RF-POS-001
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para el registro de una comanda.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-09-18
     * @módulo POS – RF-POS-001
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'canal'                     => ['required', 'in:mesa,para_llevar,whatsapp'],
            'mesa_id'                   => ['nullable', 'required_if:canal,mesa', 'exists:mesas,id'],
            'observaciones'             => ['nullable', 'string', 'max:500'],
            'items'                     => ['required', 'array', 'min:1'],
            'items.*.platillo_id'       => [
                'required',
                'integer',
                'exists:platillos,id',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (Platillo::whereKey($value)->where('disponible', false)->exists()) {
                        $fail('El platillo seleccionado no está disponible en el menú del día.');
                    }
                },
            ],
            'items.*.cantidad'          => ['required', 'integer', 'min:1'],
            'items.*.observaciones'     => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-09-18
     * @módulo POS – RF-POS-001
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'canal.required'                 => 'El canal de venta es obligatorio.',
            'canal.in'                       => 'El canal debe ser: mesa, para_llevar o whatsapp.',
            'mesa_id.required_if'            => 'Debe seleccionar una mesa cuando el canal es "Mesa".',
            'mesa_id.exists'                 => 'La mesa seleccionada no existe.',
            'items.required'                 => 'La comanda debe contener al menos un platillo.',
            'items.min'                      => 'La comanda debe contener al menos un platillo.',
            'items.*.platillo_id.required'   => 'Cada ítem debe tener un platillo asignado.',
            'items.*.platillo_id.exists'     => 'Uno de los platillos seleccionados no existe.',
            'items.*.cantidad.required'      => 'La cantidad de cada ítem es obligatoria.',
            'items.*.cantidad.min'           => 'La cantidad mínima por ítem es 1.',
        ];
    }
}
