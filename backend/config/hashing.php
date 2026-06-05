<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    */

    'driver' => 'bcrypt',

    /*
    |--------------------------------------------------------------------------
    | Bcrypt Options
    |--------------------------------------------------------------------------
    |
    | rounds: cost factor de bcrypt. Cada +1 duplica el tiempo de hashing.
    | - Laravel default = 12 (~250-800ms segun CPU) -> demasiado para login
    |   interactivo en hardware modesto.
    | - 10 = ~60-200ms, sigue siendo seguro practico para credenciales
    |   protegidas por rate limiting (throttle:5,1 ya activo en /login).
    | - OWASP 2026 minimo recomendado para bcrypt: 10.
    |
    | Sobreescribible via BCRYPT_ROUNDS en .env si un tenant lo requiere.
    */

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 10),
        'verify' => true,
        'limit' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Argon Options
    |--------------------------------------------------------------------------
    */

    'argon' => [
        'memory' => 65536,
        'threads' => 1,
        'time' => 4,
        'verify' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rehash On Login
    |--------------------------------------------------------------------------
    |
    | Si un usuario tiene password hasheada con cost > 10 (hashes viejos),
    | Laravel la rehashea al login con el cost nuevo (10). Migra sin invalidar
    | sesiones ni pedir reset.
    */

    'rehash_on_login' => true,

];
