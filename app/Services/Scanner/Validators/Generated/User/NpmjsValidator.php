<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class NpmjsValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'npmjs';
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
        return 'Npmjs';
    }

    public function siteUrl(): string
    {
        return 'https://www.npmjs.com/~{user}';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://registry.npmjs.org/-/v1/search';
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 15;
    }

    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'application/json',
        ];
    }

    /** @return array<string, mixed> */
    protected function requestQuery(string $target): array
    {
        return [
            'text' => 'maintainer:'.$target,
            'size' => 1,
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        $data = $response->json();

        if ($status !== 200 || ! is_array($data)) {
            return ['Error', 'Unexpected npm registry response (HTTP '.$status.')'];
        }

        $objects = is_array($data['objects'] ?? null) ? $data['objects'] : null;
        if ($objects === null || ! is_numeric($data['total'] ?? null)) {
            return ['Error', 'Malformed npm registry search response'];
        }

        if ((int) $data['total'] === 0 && $objects === []) {
            return ['Available', ''];
        }

        if ($this->containsExactMaintainer($objects, $target)) {
            return ['Taken', ''];
        }

        return ['Error', 'npm returned packages without confirming the requested maintainer'];
    }

    /** @return array<string, mixed> */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        $data = $response->json();
        $objects = is_array($data['objects'] ?? null) ? $data['objects'] : [];
        if ($status !== 'Taken' || ! $this->containsExactMaintainer($objects, $target)) {
            return [];
        }

        $metadata = [
            'username' => $target,
            'sources' => ['api_json'],
            'packages_count' => (int) ($data['total'] ?? 0),
        ];

        $packageNames = [];
        foreach ($objects as $object) {
            $name = data_get($object, 'package.name');
            if (is_string($name) && $name !== '') {
                $packageNames[] = $name;
            }
        }
        if ($packageNames !== []) {
            $metadata['sample_packages'] = array_values(array_unique($packageNames));
        }

        return $metadata;
    }

    /** @param array<int, mixed> $objects */
    private function containsExactMaintainer(array $objects, string $target): bool
    {
        foreach ($objects as $object) {
            $package = is_array($object) && is_array($object['package'] ?? null)
                ? $object['package']
                : [];

            foreach ((array) ($package['maintainers'] ?? []) as $maintainer) {
                if (is_array($maintainer)
                    && strcasecmp((string) ($maintainer['username'] ?? ''), $target) === 0) {
                    return true;
                }
            }

            if (strcasecmp((string) data_get($package, 'publisher.username', ''), $target) === 0) {
                return true;
            }
        }

        return false;
    }
}
