<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request para el inicio de sesión por correo y contraseña.
 * Soporta nombres de campos en español (correo, contrasena) y estándar (email, password).
 *
 * @autor  manuelmv15 / Equipo SGCO-Chayito
 * @fecha  2026-09-18
 * @módulo Administración y Seguridad – CC-91 / US-ADM-02
 */
class LoginRequest extends FormRequest
{
    /**
     * Autoriza la petición. El inicio de sesión es público por definición.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza los nombres de campos para soportar tanto español (correo, contrasena)
     * como estándar (email, password).
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email'    => $this->input('email', $this->input('correo')),
            'password' => $this->input('password', $this->input('contrasena')),
        ]);
    }

    /**
     * Reglas de validación para el inicio de sesión por correo y contraseña.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Mensajes de validación personalizados en español.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required'    => 'El correo es obligatorio.',
            'email.email'       => 'El correo no tiene un formato válido.',
            'password.required' => 'La contraseña es obligatoria.',
        ];
    }
}
