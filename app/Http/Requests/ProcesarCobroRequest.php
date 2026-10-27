<?php

namespace App\Http\Requests;

use App\Models\Comanda;
use App\Models\Venta;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcesarCobroRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     * La autorización formal RBAC por rol se implementa en US-ADM-02.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-002
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para el procesamiento del cobro de una comanda.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-002
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'comanda_id'     => [
                'required',
                'integer',
                'exists:comandas,id',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $comanda = Comanda::find($value);
                    if ($comanda) {
                        if ($comanda->estado === 'pagada') {
                            $fail('Esta comanda ya ha sido pagada.');
                        } elseif ($comanda->estado === 'cancelada') {
                            $fail('No se puede cobrar una comanda cancelada.');
                        }
                    }
                },
            ],
            'monto_recibido' => [
                Rule::excludeIf(fn (): bool => ! $this->esPagoEnEfectivo()),
                'required',
                'numeric',
                'min:0.01',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $comandaId = $this->input('comanda_id');
                    if ($comandaId) {
                        $comanda = Comanda::find($comandaId);
                        if ($comanda && (float) $value < (float) $comanda->subtotal) {
                            $fail('El monto recibido es insuficiente para cubrir el total de la comanda.');
                        }
                    }
                },
            ],
            'metodo_pago'    => ['nullable', 'string', Rule::in(Venta::METODOS_PAGO)],
        ];
    }

    /**
     * Indica si el cobro es en efectivo; un método vacío se toma como efectivo.
     * Solo el efectivo exige monto recibido, los demás se cobran por el total exacto.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-14
     * @módulo POS – US-POS-03 / RF-POS-002
     *
     * @return bool
     */
    private function esPagoEnEfectivo(): bool
    {
        return ($this->input('metodo_pago') ?? Venta::METODO_EFECTIVO) === Venta::METODO_EFECTIVO;
    }

    /**
     * Mensajes de error personalizados en español.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-002
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'comanda_id.required'     => 'El identificador de comanda es obligatorio.',
            'comanda_id.integer'      => 'El identificador de comanda debe ser un número entero.',
            'comanda_id.exists'       => 'La comanda seleccionada no existe.',
            'monto_recibido.required' => 'El monto recibido es obligatorio.',
            'monto_recibido.numeric'  => 'El monto recibido debe ser un valor numérico.',
            'monto_recibido.min'      => 'El monto recibido debe ser mayor a 0.',
            'metodo_pago.in'          => 'El método de pago debe ser: efectivo, tarjeta o transferencia.',
        ];
    }
}

