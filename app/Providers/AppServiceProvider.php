<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
        Http::globalOptions([
            'connect_timeout' => max(1, (float) config('scanner.http.connect_timeout_seconds', 3)),
        ]);

        LogViewer::auth(function (Request $request): bool {
            $routePath = trim((string) config('log-viewer.route_path', ''), '/');
            $requestPath = trim($request->path(), '/');

            return $routePath !== ''
                && ($requestPath === $routePath || str_starts_with($requestPath, $routePath.'/'));
        });
    }
}
