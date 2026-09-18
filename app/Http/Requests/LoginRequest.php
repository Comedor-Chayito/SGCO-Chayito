<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Autoriza la petición. El inicio de sesión es público por definición.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-18
     * @módulo Administración y Seguridad – CC-91 / US-ADM-02
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para el inicio de sesión por correo y contraseña.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-18
     * @módulo Administración y Seguridad – CC-91 / US-ADM-02
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Mensajes de validación personalizados en español.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-18
     * @módulo Administración y Seguridad – CC-91 / US-ADM-02
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'El correo no tiene un formato válido.',
            'password.required' => 'La contraseña es obligatoria.',
        ];
    }
}
