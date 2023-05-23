<?php

namespace App\Middlewares;
use Whis\Http\Middleware;
use Whis\Http\Request;
use Whis\Http\Response;
use Closure;

class CsrfSaverMiddleware implements Middleware {
    public function handle(Request $request, Closure $next): Response {
        $token=session()->get('_token');
        if(!$token){
            return redirect('/');
        }
        if($request->data('_token')!=$token || !$request->data('_token')){
            return redirect('/');
        }
        session()->remove('_token');
        return $next($request);
    }
}