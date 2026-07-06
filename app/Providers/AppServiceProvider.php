<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        LogViewer::auth(function (Request $request): bool {
            $routePath = trim((string) config('log-viewer.route_path', ''), '/');
            $requestPath = trim($request->path(), '/');

            return $routePath !== ''
                && ($requestPath === $routePath || str_starts_with($requestPath, $routePath.'/'));
        });
    }
}
