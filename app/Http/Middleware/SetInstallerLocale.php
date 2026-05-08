<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetInstallerLocale
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('install') || $request->is('install/*')) {
            $locale = $request->session()->get('install.locale', 'en');

            if (! array_key_exists($locale, config('shelfvault.locales'))) {
                $locale = 'en';
            }

            app()->setLocale($locale);
        }

        return $next($request);
    }
}
