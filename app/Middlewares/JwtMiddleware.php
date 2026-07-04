<?php

namespace App\Middlewares;

class JwtMiddleware extends ApiAuthMiddleware
{
    public function __construct()
    {
        parent::__construct(['jwt']);
    }
}
