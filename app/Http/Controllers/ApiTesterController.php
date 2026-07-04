<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Scanner\ScannerEngineService;

final class ApiTesterController extends Controller
{
    public function index(ScannerEngineService $engine)
    {
        $mode = 'username';

        return view('api-tester.index', [
            'moduleCatalog' => $engine->listModules($mode),
            'categoryCatalog' => $engine->listCategories($mode),
            'defaultProxyList' => implode(PHP_EOL, config('scanner.proxy_list', [])),
            'juneOnlyModuleKeys' => config('scanner_june_only', ['username' => [], 'email' => []]),
        ]);
    }

    public function external()
    {
        $defaultBase = rtrim((string) config('app.url', 'http://userscan.local'), '/') . '/api';

        return view('api-tester.external', [
            'defaultApiBase' => $defaultBase,
        ]);
    }
}
