<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de creación de usuario en el panel administrativo (US-ADM-01 / CC-84).
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-20
 * @módulo Administración y Seguridad – RF-ADM-008
 */
class CrearUsuarioRequest extends FormRequest
{
    /**
     * Determina si el usuario autenticado tiene autorización para crear usuarios.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('administrador') ?? false;
    }

    /**
     * Reglas de validación para la creación de usuario.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'rol'      => ['required', 'string', 'exists:roles,name'],
            'activo'   => ['nullable', 'boolean'],
        ];
    }

    /**
     * Mensajes de error en español para validaciones de creación.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'     => 'El nombre completo es obligatorio.',
            'name.max'          => 'El nombre no puede exceder 255 caracteres.',
            'email.required'    => 'El correo electrónico es obligatorio.',
            'email.email'       => 'El formato del correo electrónico no es válido.',
            'email.unique'      => 'Este correo electrónico ya está registrado en el sistema.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min'      => 'La contraseña debe contener al menos 8 caracteres.',
            'rol.required'      => 'Debe asignar un rol al usuario.',
            'rol.exists'        => 'El rol seleccionado no es válido en el sistema.',
        ];
    }
}

