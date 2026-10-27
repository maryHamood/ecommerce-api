<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Symfony\Component\HttpFoundation\Response;

class RefreshJwtToken
{
    public function handle(Request $request, Closure $next): Response
    {
        try {

            $user = JWTAuth::parseToken()->authenticate();
        } catch (TokenExpiredException $e) {

            try {
                $newToken = JWTAuth::refresh();

                $request->headers->set('Authorization', 'Bearer ' . $newToken);

                $response = $next($request);
                $response->headers->set('Authorization', 'Bearer ' . $newToken);
                return $response;
            } catch (\Exception $e) {
                return response()->json(['error' => 'Token cannot be refreshed.'], 401);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }


        $response = $next($request);
        return $response;
    }
}
