<?php

namespace App\Http\Requests;

use App\Exceptions\ValidacionMovimientoInventarioException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

abstract class MovimientoInventarioRequest extends FormRequest
{
    /**
     * Autoriza exclusivamente a administracion y socios para gestionar movimientos.
     *
     * @autor  Equipo de desarrollo
     * @fecha  2026-11-17
     * @módulo Inventario RF-INV-003 / CC-113
     *
     * @return bool Indica si el usuario tiene uno de los roles permitidos.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['administrador', 'socio']) ?? false;
    }

    /**
     * Devuelve los errores de entrada sin agregar campos al contrato de la API.
     *
     * @autor  Equipo de desarrollo
     * @fecha  2026-11-17
     * @módulo Inventario RF-INV-003 / CC-113
     *
     * @param Validator $validator Validacion fallida del formulario.
     * @return void
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new ValidacionMovimientoInventarioException($validator);
    }

    /**
     * Describe en espanol los errores comunes de los formularios de movimientos.
     *
     * @autor  Equipo de desarrollo
     * @fecha  2026-11-17
     * @módulo Inventario RF-INV-003 / CC-113
     *
     * @return array<string, string> Mensajes de validacion.
     */
    public function messages(): array
    {
        return [
            'cantidad.required' => 'La cantidad es obligatoria.',
            'cantidad.numeric' => 'La cantidad debe ser numerica.',
            'cantidad.decimal' => 'La cantidad admite como maximo seis decimales.',
            'cantidad.gt' => 'La cantidad de entrada debe ser positiva.',
            'cantidad.not_in' => 'El ajuste debe tener una cantidad distinta de cero.',
            'cantidad.between' => 'La cantidad excede el rango permitido de inventario.',
            'cantidad.max' => 'La cantidad excede el rango permitido de inventario.',
            'egreso_id.integer' => 'El identificador del egreso debe ser un entero.',
            'egreso_id.exists' => 'El egreso indicado no existe.',
            'motivo.required' => 'El motivo del ajuste es obligatorio.',
            'motivo.string' => 'El motivo debe ser texto.',
            'motivo.max' => 'El motivo no puede superar los 255 caracteres.',
            'page.integer' => 'La pagina debe ser un numero entero.',
            'page.min' => 'La pagina debe ser mayor o igual a uno.',
            'page.max' => 'La pagina excede el rango permitido.',
        ];
    }
}
