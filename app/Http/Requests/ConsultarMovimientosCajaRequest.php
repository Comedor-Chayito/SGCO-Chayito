<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConsultarMovimientosCajaRequest extends FormRequest
{
    /**
     * Determina si el usuario puede consultar los movimientos de caja.
     * El acceso por rol (cajero, administrador o socio) lo controla el middleware de la ruta.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-18
     *
     * @módulo POS – RF-POS-002 / US-POS-04
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas del filtro opcional de fecha: un día que no sea futuro.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-18
     *
     * @módulo POS – RF-POS-002 / US-POS-04
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fecha' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    /**
     * Mensajes de error en español.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-18
     *
     * @módulo POS – RF-POS-002 / US-POS-04
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha.date_format' => 'La fecha debe tener el formato AAAA-MM-DD.',
            'fecha.before_or_equal' => 'No se pueden consultar movimientos de una fecha futura.',
        ];
    }
}
