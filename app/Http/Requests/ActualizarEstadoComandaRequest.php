<?php

namespace App\Http\Requests;

use App\Models\Comanda;
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
     * Una comanda pagada o cancelada no puede cambiar de estado, por lo que una
     * comanda ya pagada no regresa a la cola de cocina (CC-28 / CC-34).
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
                'in:pendiente,en_preparacion,lista,pagada,cancelada',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $comanda = $this->route('comanda');
                    if (! ($comanda instanceof Comanda) && is_numeric($comanda)) {
                        $comanda = Comanda::find($comanda);
                    }

                    if ($comanda instanceof Comanda) {
                        if ($comanda->estado === 'pagada') {
                            $fail('Esta comanda ya ha sido pagada.');
                        } elseif ($comanda->estado === 'cancelada') {
                            $fail('No se puede modificar el estado de una comanda cancelada.');
                        }
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
            'estado.in'       => 'El estado debe ser: pendiente, en_preparacion, lista, pagada o cancelada.',
        ];
    }
}
