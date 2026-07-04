<?php

namespace App\Middlewares;

use Closure;
use Whis\Http\Middleware;
use Whis\Http\Request;
use Whis\Http\Response;

class ApiCorsMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->method()->value === 'OPTIONS') {
            return $this->withCors(Response::text('')->setStatus(204));
        }

        return $this->withCors($next($request));
    }

    protected function withCors(Response $response): Response
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';

        return $response
            ->setHeader('Access-Control-Allow-Origin', (string) (config('api.cors.origin') ?: $origin))
            ->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
            ->setHeader('Access-Control-Allow-Credentials', 'false')
            ->setHeader('Vary', 'Origin');
    }
}
