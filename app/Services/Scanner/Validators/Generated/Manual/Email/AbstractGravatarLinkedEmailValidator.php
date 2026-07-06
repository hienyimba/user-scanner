<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Manual\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\ProfileMetadataExtractor;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

abstract class AbstractGravatarLinkedEmailValidator extends BaseGeneratedValidator
{
    public function mode(): string
    {
        return 'email';
    }

    /**
     * @return array<int, string>
     */
    abstract protected function profileHosts(): array;

    protected function successConfidence(): float
    {
        return 0.94;
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $startedAt = microtime(true);

        try {
            $request = Http::timeout(10)
                ->withOptions([
                    'allow_redirects' => true,
                    'verify' => (bool) config('scanner.verify_ssl', false),
                ])
                ->withHeaders([
                    'User-Agent' => (string) config('scanner.user_agent'),
                    'Accept' => 'application/json,text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $hashMd5 = md5(strtolower(trim($target)));
            $gravatarResponse = $request->get('https://en.gravatar.com/' . $hashMd5 . '.json');

            if ($blocked = $this->detectBlockedOrChallenged($gravatarResponse)) {
                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    $blocked[0],
                    $blocked[1],
                    mode: $this->mode(),
                    key: $this->key(),
                    metadata: $this->mergeRequestDiagnostics([], $options, $gravatarResponse, $startedAt),
                );
            }

            $entry = data_get($gravatarResponse->json(), 'entry.0');
            if (!is_array($entry)) {
                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    'Skipped',
                    'No public Gravatar evidence linking this email to ' . $this->siteName(),
                    mode: $this->mode(),
                    key: $this->key(),
                    metadata: $this->mergeRequestDiagnostics([], $options, $gravatarResponse, $startedAt),
                );
            }

            $profileUrl = $this->matchingProfileUrl($entry);
            if ($profileUrl === null) {
                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    'Skipped',
                    'No public Gravatar evidence linking this email to ' . $this->siteName(),
                    mode: $this->mode(),
                    key: $this->key(),
                    metadata: $this->mergeRequestDiagnostics([], $options, $gravatarResponse, $startedAt),
                );
            }

            $metadata = [
                'sources' => ['gravatar_profile', 'public_profile_link'],
                'evidence_types' => ['gravatar_profile', 'public_profile_link'],
                'gravatar_hash_md5' => $hashMd5,
            ];

            $linkedMetadata = $this->buildLinkedProfileMetadata($request, $profileUrl, $entry);
            if (($linkedMetadata['metadata'] ?? null) instanceof \Illuminate\Http\Client\Response) {
                // no-op guard, impossible but keeps static analyzers happy if shapes drift.
            }

            $profileMetadata = is_array($linkedMetadata['metadata'] ?? null) ? $linkedMetadata['metadata'] : [];
            $confidence = is_numeric($linkedMetadata['confidence'] ?? null)
                ? (float) $linkedMetadata['confidence']
                : $this->successConfidence();

            $metadata = array_merge($metadata, $profileMetadata);
            if (!isset($metadata['username'])) {
                $username = $this->usernameFromProfileUrl($profileUrl);
                if ($username !== null) {
                    $metadata['username'] = $username;
                }
            }

            return new ScanResult(
                target: $target,
                category: $this->category(),
                siteName: $this->siteName(),
                url: $this->siteUrl(),
                status: 'Registered',
                reason: '',
                mode: $this->mode(),
                key: $this->key(),
                profileUrl: $profileUrl,
                confidence: $confidence,
                metadata: $this->mergeRequestDiagnostics($metadata, $options, $gravatarResponse, $startedAt),
            );
        } catch (\Throwable $e) {
            $message = strtolower($e->getMessage());
            $reason = str_contains($message, 'timed out') ? 'Connection timed out' : $e->getMessage();

            return new ScanResult(
                target: $target,
                category: $this->category(),
                siteName: $this->siteName(),
                url: $this->siteUrl(),
                status: 'Error',
                reason: $reason,
                mode: $this->mode(),
                key: $this->key(),
                metadata: $this->requestDiagnostics($options, null, $startedAt),
            );
        }
    }

    /**
     * @param array<string, mixed> $gravatarEntry
     * @return array{metadata: array<string, mixed>, confidence?: float}
     */
    protected function buildLinkedProfileMetadata(PendingRequest $request, string $profileUrl, array $gravatarEntry): array
    {
        $response = $request->get($profileUrl);
        if ($blocked = $this->detectBlockedOrChallenged($response)) {
            return [
                'metadata' => [
                    'sources' => ['gravatar_profile', 'public_profile_link'],
                    'status_detail' => 'blocked',
                    'linked_profile_reason' => $blocked[1],
                ],
            ];
        }

        if ($response->status() !== 200) {
            return [
                'metadata' => [
                    'sources' => ['gravatar_profile', 'public_profile_link'],
                    'linked_profile_http_status' => $response->status(),
                ],
            ];
        }

        /** @var ProfileMetadataExtractor $extractor */
        $extractor = app(ProfileMetadataExtractor::class);
        $metadata = $extractor->extractProfileHtmlMetadata(
            substr($response->body(), 0, (int) config('scanner.metadata.max_html_bytes', 262144)),
            $profileUrl,
        );
        $metadata['sources'] = array_values(array_unique(array_merge(
            (array) ($metadata['sources'] ?? []),
            ['gravatar_profile', 'public_profile_link', 'profile_html'],
        )));

        return [
            'metadata' => $metadata,
        ];
    }

    /**
     * @param array<string, mixed> $entry
     */
    protected function matchingProfileUrl(array $entry): ?string
    {
        $links = [];

        foreach ((array) ($entry['accounts'] ?? []) as $account) {
            if (!is_array($account)) {
                continue;
            }

            $url = $this->normalizeAbsoluteUrlValue($account['url'] ?? null, 'https://gravatar.com');
            if ($url !== null) {
                $links[] = $url;
            }
        }

        foreach ((array) ($entry['urls'] ?? []) as $urlEntry) {
            if (!is_array($urlEntry)) {
                continue;
            }

            $url = $this->normalizeAbsoluteUrlValue($urlEntry['value'] ?? null, 'https://gravatar.com');
            if ($url !== null) {
                $links[] = $url;
            }
        }

        foreach (array_values(array_unique($links)) as $url) {
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            foreach ($this->profileHosts() as $allowedHost) {
                $allowedHost = strtolower($allowedHost);
                if ($host === $allowedHost || str_ends_with($host, '.' . $allowedHost)) {
                    return $url;
                }
            }
        }

        return null;
    }

    protected function usernameFromProfileUrl(string $profileUrl): ?string
    {
        $path = trim((string) parse_url($profileUrl, PHP_URL_PATH), '/');
        if ($path === '') {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $path), static fn (string $segment): bool => $segment !== ''));
        if ($segments === []) {
            return null;
        }

        return end($segments) ?: null;
    }
}
