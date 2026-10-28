<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {

        $response = $next($request);


        $response->headers->set('X-Frame-Options', 'DENY', false);
        $response->headers->set('X-Content-Type-Options', 'nosniff', false);
        $response->headers->set('Referrer-Policy', 'no-referrer', false);
        $response->headers->set('X-XSS-Protection', '1; mode=block', false);
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()', false);

        return $response;
    }
}