<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {

        $user = $request->user('api');


        if (! $user) {
            return ApiResponse::error('Unauthenticated.', null, 401);
        }

        if (! in_array($user->role, $roles, true)) {
            return ApiResponse::error(
                'Forbidden. You do not have permission to access this resource.',
                ['role' => ['Required role: ' . implode(' or ', $roles)]],
                403
            );
        }


        return $next($request);
    }
}
