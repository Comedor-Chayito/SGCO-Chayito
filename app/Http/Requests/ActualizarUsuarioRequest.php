<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación de actualización de usuario en el panel administrativo (US-ADM-01 / CC-84).
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-20
 * @módulo Administración y Seguridad – RF-ADM-008
 */
class ActualizarUsuarioRequest extends FormRequest
{
    /**
     * Determina si el usuario autenticado tiene autorización para modificar usuarios.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('administrador') ?? false;
    }

    /**
     * Reglas de validación para la actualización de usuario.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $usuarioParam = $this->route('usuario');
        $usuarioId = is_object($usuarioParam) ? $usuarioParam->id : $usuarioParam;

        return [
            'name'     => ['sometimes', 'required', 'string', 'max:255'],
            'email'    => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($usuarioId),
            ],
            'password' => ['nullable', 'string', 'min:8'],
            'rol'      => ['sometimes', 'required', 'string', 'exists:roles,name'],
            'activo'   => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Mensajes de error en español para validaciones de actualización.
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
            'email.unique'      => 'Este correo electrónico ya está registrado por otro usuario.',
            'password.min'      => 'La contraseña debe contener al menos 8 caracteres.',
            'rol.required'      => 'Debe asignar un rol al usuario.',
            'rol.exists'        => 'El rol seleccionado no es válido en el sistema.',
        ];
    }
}

