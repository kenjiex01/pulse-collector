<?php

namespace App\Http\Middleware;

use App\Services\DesktopInstallerUpdateService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class EnsureDesktopInstallerUpdate
{
    public function handle(Request $request, Closure $next): Response
    {
        // Skip S3 re-check on the download/stream endpoints themselves.
        if ($request->routeIs('desktop.update.download', 'desktop.update.stream')) {
            return $next($request);
        }

        $service = app(DesktopInstallerUpdateService::class);

        if ($service->isEnabled()) {
            View::share('desktopInstallerUpdate', $service->pendingUpdateForUi());
        }

        return $next($request);
    }
}
