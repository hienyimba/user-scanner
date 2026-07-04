<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/dev/pypi.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class PypiValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'pypi';
    }

    public function category(): string
    {
        return 'dev';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Pypi';
    }

    public function siteUrl(): string
    {
        return 'https://pypi.org/user/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://pypi.org/pypi';
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 10;
    }

    protected function requestMethod(): string
{
    return 'POST';
}

protected function requestHeadersForTarget(string $target): array
{
    return [
        'Content-Type' => 'text/xml',
    ];
}

protected function requestRawBody(string $target): ?string
{
    return '<?xml version="1.0"?><methodCall><methodName>user_packages</methodName><params><param><value><string>' . $target . '</string></value></param></params></methodCall>';
}

public function check(string $target, array $options = []): ScanResult
{
    if (!preg_match('/^(?!_+$)[A-Za-z0-9._-]+$/', $target)) {
        return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Username may only contain letters, numbers, periods, underscores, and hyphens, and cannot consist solely of underscores', mode: $this->mode(), key: $this->key());
    }

    return parent::check($target, $options);
}

protected function parseConnectorResponse(Response $response, string $target): array
{
    if ($response->status() !== 200) {
        return ['Error', 'XML-RPC endpoint returned status code: ' . $response->status()];
    }

    $body = $response->body();
    if (preg_match('/<array><data>\s*<\/data><\/array>/', $body) === 1 || str_contains($body, '<value><array><data/></array></value>')) {
        return ['Available', ''];
    }
    if (str_contains($body, '<methodResponse')) {
        return ['Taken', ''];
    }

    return ['Error', 'System error checking XML-RPC'];
}
}
