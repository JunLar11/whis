<?php

return [
    /*
     * Modelo usado por defecto al validar JWT.
     */
    'auth' => [
        'user_model' => App\Models\User::class,
    ],

    /*
     * Tokens persistentes en base de datos.
     */
    'tokens' => [
        'prefix' => 'whis_',
    ],

    /*
     * JWT stateless.
     * Usa una clave larga en .env:
     * JWT_SECRET=base64-o-string-largo-aleatorio
     */
    'jwt' => [
        'secret' => $_ENV['JWT_SECRET'] ?? $_ENV['APP_KEY'] ?? null,
        'ttl' => 3600,
        'leeway' => 5,
        'issuer' => $_ENV['APP_URL'] ?? 'whis',
        'audience' => null,
    ],

    /*
     * CORS básico para /api.
     */
    'cors' => [
        'origin' => $_ENV['API_CORS_ORIGIN'] ?? '*',
    ],
];
