<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida los datos de entrada para actualizar un platillo del menú.
 * La disponibilidad no se cambia aquí: tiene su propio endpoint (CC-106).
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-11-17
 * @módulo Administración – RF-ADM-008 / CC-104 (US-ADM-03)
 */
class ActualizarPlatilloRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     * El acceso por rol (administrador|socio) lo aplica el middleware de la ruta.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-17
     * @módulo Administración – RF-ADM-008 / CC-104 (US-ADM-03)
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para la actualización de un platillo.
     * El precio es DECIMAL(10,2): mayor que cero, máximo 8 enteros y 2 decimales.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-17
     * @módulo Administración – RF-ADM-008 / CC-104 (US-ADM-03)
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre'          => [
                'required',
                'string',
                'max:100',
                Rule::unique('platillos', 'nombre')->ignore($this->route('platillo')),
            ],
            'descripcion'     => ['nullable', 'string', 'max:1000'],
            'precio_unitario' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'disponible'      => ['prohibited'],
        ];
    }

    /**
     * Mensajes de validación en español.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-17
     * @módulo Administración – RF-ADM-008 / CC-104 (US-ADM-03)
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required'          => 'El nombre del platillo es obligatorio.',
            'nombre.max'               => 'El nombre del platillo no puede superar los 100 caracteres.',
            'nombre.unique'            => 'Ya existe un platillo con ese nombre.',
            'descripcion.max'          => 'La descripción no puede superar los 1000 caracteres.',
            'precio_unitario.required' => 'El precio unitario es obligatorio.',
            'precio_unitario.numeric'  => 'El precio unitario debe ser un número.',
            'precio_unitario.decimal'  => 'El precio unitario admite como máximo 2 decimales.',
            'precio_unitario.gt'       => 'El precio unitario debe ser mayor que cero.',
            'precio_unitario.max'      => 'El precio unitario no puede superar 99,999,999.99.',
            'disponible.prohibited'    => 'La disponibilidad se cambia desde el endpoint de disponibilidad.',
        ];
    }
}
