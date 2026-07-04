<?php

namespace App\Providers;

class ApiAuthServiceProvider
{
    public function registerServices(): void
    {
        /*
         * Provider de integración para la API de Whis.
         *
         * Por ahora no necesita registrar nada obligatorio porque:
         *
         * - La autenticación API la resuelven los middlewares.
         * - Los tokens los maneja el modelo ApiToken.
         * - El contexto API lo maneja ApiAuthContext.
         * - Las rutas API deben cargarse desde tu archivo de rutas.
         *
         * Este provider existe para que config/providers.php no rompa
         * y para dejar un punto de extensión limpio en el futuro.
         */
    }
}