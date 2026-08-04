<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class RiotIdValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'riot_id';
    }

    public function category(): string
    {
        return 'gaming';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'RiotId';
    }

    public function siteUrl(): string
    {
        return 'https://www.riotid-lookup.com';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://www.riotid-lookup.com/api/lookup?riotId='.rawurlencode(trim($target));
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 10;
    }

    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ];
    }

    public function check(string $target, array $options = []): ScanResult
    {
        if (! $this->hasCompleteRiotId($target)) {
            return new ScanResult(
                target: $target,
                category: $this->category(),
                siteName: $this->siteName(),
                url: $this->siteUrl(),
                status: 'Skipped',
                reason: 'Riot ID format required: Name#Tag (for example, TenZ#00005)',
                mode: $this->mode(),
                key: $this->key(),
                confidence: 0.0,
                metadata: [
                    'status_detail' => 'incomplete_identifier',
                    'required_format' => 'Name#Tag',
                    'sources' => [],
                    'evidence' => [],
                ],
            );
        }

        return parent::check(trim($target), $options);
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();

        if ($blocked = $this->detectBlockedOrChallenged($response)) {
            return $blocked;
        }

        if ($status === 400) {
            return ['Error', 'Invalid Riot ID format'];
        }

        if ($status === 200) {
            $isTaken = data_get($response->json(), 'isTaken');
            if (is_bool($isTaken)) {
                return [$isTaken ? 'Taken' : 'Available', ''];
            }
        }

        return ['Error', $this->key().': indeterminate lookup response (HTTP '.$status.')'];
    }

    private function hasCompleteRiotId(string $target): bool
    {
        $parts = explode('#', trim($target));

        return count($parts) === 2
            && trim($parts[0]) !== ''
            && strlen(trim($parts[1])) >= 3
            && strlen(trim($parts[1])) <= 5;
    }
}
