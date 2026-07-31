<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->header('Accept-Language', config('app.locale'));
        $locale = in_array($locale, ['pt_BR', 'en']) ? $locale : config('app.fallback_locale');
        App::setLocale($locale);

        return $next($request);
    }
}