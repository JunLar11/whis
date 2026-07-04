<?php

namespace App\Middlewares;

use Closure;
use Whis\Auth\Auth;
use Whis\Http\Middleware;
use Whis\Http\Request;
use Whis\Http\Response;

class ApiAbilityMiddleware implements Middleware
{
    public function __construct(
        protected string $ability = '*'
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::isApi()) {
            return Response::json([
                'ok' => false,
                'message' => 'No autorizado.',
                'errors' => [],
            ])->setStatus(401)
              ->setHeader('WWW-Authenticate', 'Bearer');
        }

        if (Auth::tokenCant($this->ability)) {
            return Response::json([
                'ok' => false,
                'message' => 'No tienes permiso para realizar esta acción.',
                'errors' => [
                    'ability' => "Se requiere el permiso [{$this->ability}].",
                ],
            ])->setStatus(403);
        }

        return $next($request);
    }
}
