<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Driver de cifrado por defecto
    |--------------------------------------------------------------------------
    |
    | Fijado a "bcrypt" (CC-90 / US-ADM-02).
    | No se deja configurable por variable de entorno para que ningún cambio
    | accidental en el .env pueda debilitar el cifrado de contraseñas.
    |
    */

    'driver' => 'bcrypt',

    /*
    |--------------------------------------------------------------------------
    | Opciones de Bcrypt
    |--------------------------------------------------------------------------
    */

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => env('HASH_VERIFY', true),
        'limit' => env('BCRYPT_LIMIT', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rehash al iniciar sesión
    |--------------------------------------------------------------------------
    |
    | Si el costo (rounds) configurado cambia, Laravel vuelve a cifrar el
    | hash almacenado la próxima vez que el usuario inicia sesión.
    |
    */

    'rehash_on_login' => true,

];
