<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/social/fotka.py
// parity-class: manual-june

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class FotkaValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'fotka';
    }

    public function category(): string
    {
        return 'social';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Fotka';
    }

    public function siteUrl(): string
    {
        return 'https://fotka.com/profil/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://api.fotka.com/v2/user/dataStatic?login='.rawurlencode($target);
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

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();

        if ($blocked = $this->detectBlockedOrChallenged($response)) {
            return $blocked;
        }

        if ($status === 404) {
            return ['Available', ''];
        }

        if ($status !== 200) {
            return ['Error', 'HTTP '.$status];
        }

        $data = $response->json();
        if (! is_array($data)) {
            return ['Error', 'Fotka returned invalid JSON'];
        }

        $apiStatus = strtoupper(trim((string) ($data['status'] ?? '')));
        $info = strtolower(trim((string) ($data['info'] ?? '')));
        if ($apiStatus === 'ERROR' && str_contains($info, 'nie istnieje')) {
            return ['Available', ''];
        }

        $profile = $data['profil'] ?? null;
        $login = is_array($profile) ? trim((string) ($profile['login'] ?? '')) : '';
        $id = is_array($profile) ? ($profile['id'] ?? null) : null;
        $hasId = is_int($id) ? $id > 0 : (is_string($id) && ctype_digit($id) && (int) $id > 0);
        if ($hasId && $login !== '' && strcasecmp($login, $target) === 0) {
            return ['Taken', ''];
        }

        return ['Error', 'Fotka response did not contain matching profile evidence'];
    }
}
