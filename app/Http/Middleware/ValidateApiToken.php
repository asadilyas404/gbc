<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ValidateApiToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        $expectedToken = config('services.sync_api.token');

        if (empty($token) || $token != $expectedToken) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Invalid API token: ' . $token . ' & ' . $expectedToken
            ], 401);
        }

        return $next($request);
    }
}

