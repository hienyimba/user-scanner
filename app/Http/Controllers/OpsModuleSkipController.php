<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Scanner\ModuleSkipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class OpsModuleSkipController extends Controller
{
    public function update(Request $request, ModuleSkipService $skips): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', 'string', 'in:username,email'],
            'module_key' => ['required', 'string', 'max:100'],
            'action' => ['required', 'string', Rule::in(['set', 'clear'])],
            'duration' => ['nullable', 'string', Rule::in(['permanent', '6h'])],
            'window' => ['nullable', 'string', Rule::in(['30d', '7d', '1d', '6h'])],
        ]);

        if ($data['action'] === 'clear') {
            $skips->clearSkip($data['mode'], $data['module_key']);

            return redirect()
                ->route('ops.metrics', ['window' => $data['window'] ?? '30d'])
                ->with('status', sprintf('Cleared skip flag for %s.', $data['module_key']));
        }

        $skips->setSkip($data['mode'], $data['module_key'], (string) ($data['duration'] ?? 'permanent'));

        return redirect()
            ->route('ops.metrics', ['window' => $data['window'] ?? '30d'])
            ->with('status', sprintf('Updated skip flag for %s.', $data['module_key']));
    }
}
