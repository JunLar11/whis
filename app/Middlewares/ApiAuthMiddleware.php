<?php

namespace App\Middlewares;

use Closure;
use Whis\Auth\Api\ApiAuthContext;
use Whis\Auth\Api\ApiTokenGuard;
use Whis\Auth\Api\JwtGuard;
use Whis\Auth\Auth;
use Whis\Http\Middleware;
use Whis\Http\Request;
use Whis\Http\Response;

class ApiAuthMiddleware implements Middleware
{
    /**
     * @param array<int,string> $guards token, jwt
     */
    public function __construct(
        protected array $guards = ['token', 'jwt']
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $context = $this->attempt($request);

        if ($context->guest()) {
            return Response::json([
                'ok' => false,
                'message' => $context->error ?: 'No autorizado.',
                'errors' => [],
            ])->setStatus($context->status)
              ->setHeader('WWW-Authenticate', 'Bearer');
        }

        Auth::setApiContext($context);

        try {
            return $next($request);
        } finally {
            Auth::forgetApiContext();
        }
    }

    protected function attempt(Request $request): ApiAuthContext
    {
        $bearer = $request->bearerToken();

        if (! $bearer) {
            return ApiAuthContext::deny('Token ausente.');
        }

        $preferJwt = substr_count($bearer, '.') === 2;

        $guards = $preferJwt
            ? $this->prioritize('jwt')
            : $this->prioritize('token');

        $last = ApiAuthContext::deny('Token inválido.');

        foreach ($guards as $guard) {
            $result = match ($guard) {
                'jwt' => app(JwtGuard::class)->attempt($request),
                'token' => app(ApiTokenGuard::class)->attempt($request),
                default => ApiAuthContext::deny('Guard de API no soportado.'),
            };

            if ($result->check()) {
                return $result;
            }

            $last = $result;
        }

        return $last;
    }

    protected function prioritize(string $first): array
    {
        $guards = array_values(array_unique($this->guards));

        if (! in_array($first, $guards, true)) {
            return $guards;
        }

        return array_values(array_unique([
            $first,
            ...array_filter($guards, fn($guard) => $guard !== $first),
        ]));
    }
}
