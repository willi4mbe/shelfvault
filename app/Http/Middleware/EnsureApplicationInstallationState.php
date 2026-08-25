<?php

namespace App\Http\Middleware;

use App\Services\Installer\InstallationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApplicationInstallationState
{
    public function __construct(private readonly InstallationState $installationState) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $installed = $this->installationState->installed();
        $installRoute = $request->is('install') || $request->is('install/*');

        if ($installed && $installRoute) {
            return redirect()->route('login');
        }

        if (! $installed && ! $installRoute && ! $this->isPublicApplicationAsset($request)) {
            return redirect()->to('/install.php');
        }

        return $next($request);
    }

    private function isPublicApplicationAsset(Request $request): bool
    {
        return $request->is('build/*')
            || $request->is('branding/*')
            || $request->is('favicon.ico')
            || $request->is('robots.txt')
            || $request->is('up');
    }
}
