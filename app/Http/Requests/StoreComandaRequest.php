<?php

namespace App\Http\Requests;

use App\Models\Platillo;
use Illuminate\Foundation\Http\FormRequest;

class StoreComandaRequest extends FormRequest
{
    /**
     * Autoriza la petición. Sin RBAC implementado aún (CC-17/CC-18), se permite a cualquiera.
     *
     * @autor  manuelmv15
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
     * Reglas de validación para registrar una comanda con su detalle de platillos.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-18
     * @módulo POS – RF-POS-001
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mesa_id' => ['nullable', 'integer', 'required_if:canal,mesa', 'exists:mesas,id'],
            'usuario_id' => ['required', 'integer', 'exists:users,id'],
            'canal' => ['required', 'in:mesa,para_llevar,whatsapp'],
            'observaciones' => ['nullable', 'string'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.platillo_id' => [
                'required',
                'integer',
                'exists:platillos,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (Platillo::whereKey($value)->where('disponible', false)->exists()) {
                        $fail('El platillo seleccionado no está disponible en el menú del día.');
                    }
                },
            ],
            'detalles.*.cantidad' => ['required', 'integer', 'min:1'],
            'detalles.*.observaciones' => ['nullable', 'string'],
        ];
    }

    /**
     * Mensajes de validación personalizados en español.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-18
     * @módulo POS – RF-POS-001
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mesa_id.required_if' => 'La mesa es obligatoria cuando el canal es "mesa".',
            'mesa_id.exists' => 'La mesa seleccionada no existe.',
            'usuario_id.required' => 'El usuario que registra la comanda es obligatorio.',
            'usuario_id.exists' => 'El usuario indicado no existe.',
            'canal.required' => 'El canal de la comanda es obligatorio.',
            'canal.in' => 'El canal debe ser mesa, para_llevar o whatsapp.',
            'detalles.required' => 'La comanda debe incluir al menos un platillo.',
            'detalles.min' => 'La comanda debe incluir al menos un platillo.',
            'detalles.*.platillo_id.required' => 'Cada renglón debe indicar un platillo.',
            'detalles.*.platillo_id.exists' => 'Uno de los platillos indicados no existe.',
            'detalles.*.cantidad.required' => 'Cada renglón debe indicar una cantidad.',
            'detalles.*.cantidad.min' => 'La cantidad debe ser al menos 1.',
        ];
    }
}
