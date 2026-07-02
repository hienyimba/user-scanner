<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class TwitchValidator extends BaseGeneratedValidator
{
    public function key(): string { return 'twitch'; }
    public function category(): string { return 'creator'; }
    public function mode(): string { return 'username'; }
    public function siteName(): string { return 'Twitch'; }
    public function siteUrl(): string { return 'https://twitch.tv'; }
    protected function requestMethod(): string { return 'POST'; }
    protected function requestUrl(string $target): string { return 'https://gql.twitch.tv/gql'; }
    protected function followRedirects(): bool { return false; }
    protected function requestHeaders(): array
    {
        return [
            'Accept-Encoding' => 'identity',
            'Content-Type' => 'application/json',
            'sec-ch-ua-platform' => '"Android"',
            'accept-language' => 'en-US',
            'client-id' => 'kimne78kx3ncx6brgo4mv6wki5h1ko',
            'client-version' => '7bb0442d-1175-4ab5-9d32-b1f370536cbf',
            'origin' => 'https://m.twitch.tv',
            'referer' => 'https://m.twitch.tv/',
        ];
    }
    protected function requestBody(string $target): array
    {
        return [[
            'operationName' => 'ChannelLayout',
            'variables' => ['channelLogin' => $target, 'includeIsDJ' => true],
            'extensions' => ['persistedQuery' => ['version' => 1, 'sha256Hash' => '4c361fa1874dc8f6a49e62b56aa1032eccb31311bdb653918a924f96a8b2d1a6']],
        ]];
    }
    public function check(string $target, array $options = []): ScanResult
    {
        if (strlen($target) < 4 || strlen($target) > 25) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Username must be between 4 and 25 characters long', mode: $this->mode(), key: $this->key());
        }
        if (!preg_match('/^[a-zA-Z0-9]+$/', $target)) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Username can only contain alphanumeric characters (a-z, 0-9)', mode: $this->mode(), key: $this->key());
        }
        return parent::check($target, $options);
    }
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        if ($response->status() !== 200) {
            return ['Error', 'Unexpected status code: ' . $response->status()];
        }
        try {
            $data = $response->json();
        } catch (\Throwable $e) {
            return ['Error', 'Failed to decode JSON response: ' . $e->getMessage()];
        }
        $userData = $data[0]['data']['user'] ?? [];
        $typename = $userData['__typename'] ?? null;
        return match ($typename) {
            'User' => ['Taken', ''],
            'UserDoesNotExist' => ['Available', ''],
            default => ['Error', 'Unexpected GraphQL response structure or type.'],
        };
    }
}
