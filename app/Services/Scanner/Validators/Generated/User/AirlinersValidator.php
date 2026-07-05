<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/community/airliners.py
// parity-class: manual-june

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class AirlinersValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'airliners';
    }

    public function category(): string
    {
        return 'community';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Airliners';
    }

    public function siteUrl(): string
    {
        return 'https://www.airliners.net/user/profile';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.airliners.net/user/{$target}/profile";
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 20;
    }

    protected function makeRequest(string $target, array $options = []): Response
    {
        $url = $this->requestUrl($target);
        $response = $this->sendRequest($url, $options);

        if ($response->status() !== 202) {
            return $response;
        }

        $challenge = $response->body();
        $nonce = $this->matchChallengeValue($challenge, "challenge_nonce:'([^']+)'");
        $hmac = $this->matchChallengeValue($challenge, "challenge_hmac:'([^']+)'");
        $difficulty = $this->matchChallengeValue($challenge, "difficulty:'([^']+)'");
        $difficultyChar = $this->matchChallengeValue($challenge, "difficulty_char:'([^']+)'");
        $issuedAt = $this->matchChallengeValue($challenge, "issued_at:'([^']+)'");

        if ($nonce === null || $hmac === null || $difficulty === null || $difficultyChar === null || $issuedAt === null) {
            return $response;
        }

        $cookie = $this->solvePowBypassCookie($nonce, $hmac, (int) $difficulty, $difficultyChar, $issuedAt);
        if ($cookie === null) {
            return $response;
        }

        return $this->sendRequest($url, $options, ['Cookie' => 'pow_bypass=' . $cookie]);
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        if ($status === 404) {
            return ['Available', ''];
        }

        if ($status === 200) {
            return ['Taken', ''];
        }

        return ['Error', 'HTTP ' . $status];
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, string> $headers
     */
    private function sendRequest(string $url, array $options = [], array $headers = []): Response
    {
        $request = $this->baseRequest($headers);

        if (!empty($options['proxy'])) {
            $request = $request->withOptions(['proxy' => $options['proxy']]);
        }

        return $request->get($url);
    }

    /** @param array<string, string> $headers */
    private function baseRequest(array $headers = []): PendingRequest
    {
        return Http::timeout($this->timeoutSeconds())
            ->withOptions([
                'allow_redirects' => $this->followRedirects(),
                'verify' => (bool) config('scanner.verify_ssl', false),
            ])
            ->withHeaders(array_merge([
                'User-Agent' => config('scanner.user_agent'),
                'Accept' => 'text/html,application/json,*/*;q=0.8',
            ], $headers));
    }

    private function matchChallengeValue(string $body, string $pattern): ?string
    {
        return preg_match("/{$pattern}/", $body, $matches) === 1 ? $matches[1] : null;
    }

    private function solvePowBypassCookie(string $nonce, string $hmac, int $difficulty, string $difficultyChar, string $issuedAt): ?string
    {
        if ($difficulty < 1 || $difficulty > 6 || $difficultyChar === '') {
            return null;
        }

        $targetPrefix = str_repeat($difficultyChar, $difficulty);
        $prefix = $nonce . $issuedAt;

        for ($i = 1; $i < 10000000; $i++) {
            $candidate = (string) $i;
            $hash = hash('sha256', $prefix . $candidate);
            if (str_starts_with($hash, $targetPrefix)) {
                return implode('|', [$nonce, $issuedAt, $candidate, $hash, $hmac]);
            }
        }

        return null;
    }
}
