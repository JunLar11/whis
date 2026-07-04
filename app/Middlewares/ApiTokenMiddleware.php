<?php

namespace App\Middlewares;

class ApiTokenMiddleware extends ApiAuthMiddleware
{
    public function __construct()
    {
        parent::__construct(['token']);
    }
}
