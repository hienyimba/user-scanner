<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/dev/pypi.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

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

    try {
        $response = $this->makeRequest($target, $options);
        if ($response->status() !== 200) {
            return new ScanResult(
                $target,
                $this->category(),
                $this->siteName(),
                $this->siteUrl(),
                'Error',
                'XML-RPC endpoint returned status code: ' . $response->status(),
                mode: $this->mode(),
                key: $this->key(),
            );
        }

        $packageNames = $this->extractPackageNames($response->body());
        if ($packageNames === []) {
            return new ScanResult(
                $target,
                $this->category(),
                $this->siteName(),
                $this->siteUrl(),
                'Available',
                '',
                mode: $this->mode(),
                key: $this->key(),
            );
        }

        $metadata = [
            'username' => $target,
            'packages_count' => count($packageNames),
            'posts_count' => count($packageNames),
            'packages' => array_slice($packageNames, 0, 5),
            'sources' => ['api_json'],
        ];

        [$displayName, $publicEmail] = $this->lookupAuthorMetadata($packageNames, $options);
        if ($displayName !== null) {
            $metadata['display_name'] = $displayName;
        }
        if ($publicEmail !== null) {
            $metadata['public_email'] = $publicEmail;
        }

        $summary = [];
        if ($displayName !== null) {
            $summary['Name'] = $displayName;
        }
        if ($publicEmail !== null) {
            $summary['Email'] = $publicEmail;
        }
        $summary['Packages'] = (string) count($packageNames);

        return new ScanResult(
            $target,
            $this->category(),
            $this->siteName(),
            $this->siteUrl(),
            'Taken',
            '',
            $this->metadataSummary($summary),
            mode: $this->mode(),
            key: $this->key(),
            metadata: $metadata,
        );
    } catch (\Throwable $e) {
        $message = strtolower($e->getMessage());
        $reason = match (true) {
            str_contains($message, 'timed out') => 'Request timeout',
            default => $e->getMessage(),
        };

        return new ScanResult(
            $target,
            $this->category(),
            $this->siteName(),
            $this->siteUrl(),
            'Error',
            $reason,
            mode: $this->mode(),
            key: $this->key(),
        );
    }
}

protected function parseConnectorResponse(Response $response, string $target): array
{
    if ($response->status() !== 200) {
        return ['Error', 'XML-RPC endpoint returned status code: ' . $response->status()];
    }

    $body = $response->body();
    if ($this->extractPackageNames($body) === []) {
        return ['Available', ''];
    }
    if (str_contains($body, '<methodResponse')) {
        return ['Taken', ''];
    }

    return ['Error', 'System error checking XML-RPC'];
}

    /**
     * @return array<int, string>
     */
    private function extractPackageNames(string $xml): array
    {
        $trimmed = trim($xml);
        if ($trimmed === '' || !str_contains($trimmed, '<methodResponse')) {
            return [];
        }

        $document = @simplexml_load_string($trimmed);
        if ($document === false) {
            throw new \RuntimeException('System error checking XML-RPC');
        }

        $packageNames = [];
        $nodes = $document->xpath('//array/data/value/array/data/value[2]/string');
        if (is_array($nodes)) {
            foreach ($nodes as $node) {
                $name = trim((string) $node);
                if ($name !== '') {
                    $packageNames[] = $name;
                }
            }
        }

        return array_values(array_unique($packageNames));
    }

    /**
     * @param array<int, string> $packageNames
     * @param array<string, mixed> $options
     * @return array{0:?string,1:?string}
     */
    private function lookupAuthorMetadata(array $packageNames, array $options): array
    {
        $displayName = null;
        $publicEmail = null;

        foreach ($packageNames as $packageName) {
            if ($displayName !== null && $publicEmail !== null) {
                break;
            }

            try {
                $request = Http::timeout($this->timeoutSeconds())
                    ->withOptions([
                        'allow_redirects' => true,
                        'verify' => (bool) config('scanner.verify_ssl', false),
                    ])
                    ->withHeaders([
                        'User-Agent' => config('scanner.user_agent'),
                        'Accept' => 'application/json,text/html,*/*;q=0.8',
                    ]);

                if (!empty($options['proxy']) && is_string($options['proxy'])) {
                    $request = $request->withOptions(['proxy' => $options['proxy']]);
                }

                $response = $request->get('https://pypi.org/pypi/' . rawurlencode($packageName) . '/json');
                if ($response->status() !== 200) {
                    continue;
                }

                $info = $response->json('info');
                if (!is_array($info)) {
                    continue;
                }

                foreach ([(string) ($info['author_email'] ?? ''), (string) ($info['maintainer_email'] ?? '')] as $candidate) {
                    [$nameCandidate, $emailCandidate] = $this->extractNameAndEmail($candidate);
                    if ($displayName === null && $nameCandidate !== null) {
                        $displayName = $nameCandidate;
                    }
                    if ($publicEmail === null && $emailCandidate !== null) {
                        $publicEmail = $emailCandidate;
                    }
                }

                foreach ([(string) ($info['author'] ?? ''), (string) ($info['maintainer'] ?? '')] as $candidate) {
                    $candidate = trim($candidate);
                    if ($displayName === null && $candidate !== '' && strtolower($candidate) !== 'none') {
                        $displayName = $candidate;
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return [$displayName, $publicEmail];
    }

    /**
     * @return array{0:?string,1:?string}
     */
    private function extractNameAndEmail(string $value): array
    {
        $candidate = trim($value);
        if ($candidate === '' || !str_contains($candidate, '@')) {
            return [null, null];
        }

        if (preg_match('/^(.*?)<([^>]+)>$/', $candidate, $matches) === 1) {
            $name = trim($matches[1]);
            $email = trim($matches[2]);

            return [$name !== '' ? $name : null, $email !== '' ? $email : null];
        }

        return [null, $candidate];
    }
}
