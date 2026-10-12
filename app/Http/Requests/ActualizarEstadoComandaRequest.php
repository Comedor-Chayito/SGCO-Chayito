<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class ActualizarEstadoComandaRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a cambiar el estado de la comanda.
     * La autorización granular por rol se complementa en US-ADM-02 con Sanctum.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – US-POS-02 / RF-POS-001
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para actualizar el estado de una comanda.
     * El estado "pagada" solo lo asigna caja al cobrar (POST /api/cobros), y una
     * comanda ya pagada no puede regresar a la cola de cocina.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – US-POS-02 / RF-POS-001
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estado' => [
                'required',
                'string',
                'in:pendiente,en_preparacion,lista,cancelada',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->route('comanda')?->estado === 'pagada') {
                        $fail('La comanda ya fue pagada y no puede cambiar de estado.');
                    }
                },
            ],
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – US-POS-02 / RF-POS-001
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estado.required' => 'El estado de la comanda es obligatorio.',
            'estado.in'       => 'El estado debe ser: pendiente, en_preparacion, lista o cancelada.',
        ];
    }
}
