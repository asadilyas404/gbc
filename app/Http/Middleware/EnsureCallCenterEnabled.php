<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureCallCenterEnabled
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('callcenter.enabled')) {
            abort(404);
        }

        return $next($request);
    }
}
