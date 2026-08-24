<?php

namespace App\Http\Middleware;

use App\Services\Installer\InstallationState;
use App\Services\Library\LibrarySettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePublicLibraryVisibility
{
    public function __construct(
        private readonly InstallationState $installationState,
        private readonly LibrarySettings $librarySettings,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->installationState->installed() || ! $this->isLibraryRoute($request)) {
            return $next($request);
        }

        if ($this->librarySettings->isPrivate() && ! Auth::check()) {
            return redirect()->guest(route('login'));
        }

        return $next($request);
    }

    private function isLibraryRoute(Request $request): bool
    {
        $routeName = (string) $request->route()?->getName();

        return str_starts_with($routeName, 'library.');
    }
}
