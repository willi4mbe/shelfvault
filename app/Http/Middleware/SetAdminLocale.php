<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetAdminLocale
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $locale = Auth::user()?->preferred_locale;

            if (! is_string($locale) || ! array_key_exists($locale, config('shelfvault.locales'))) {
                $locale = config('app.locale', 'en');
            }

            app()->setLocale($locale);
        }

        return $next($request);
    }
}
