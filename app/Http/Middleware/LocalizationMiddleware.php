<?php

namespace App\Http\Middleware;
use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

class LocalizationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $local = ($request->hasHeader('X-localization')) ? (strlen($request->header('X-localization'))>0?$request->header('X-localization'): 'en'): 'en';
        Cache::forget('business_settings_language');
        App::setLocale($local);
        // dd($local);
        return $next($request);
    }
}
