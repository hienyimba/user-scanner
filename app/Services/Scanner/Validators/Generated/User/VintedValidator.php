<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use App\DTO\ScanResult;
use Illuminate\Http\Client\Response;

final class VintedValidator extends BaseGeneratedValidator
{
    private string $domain = 'www.vinted.co.uk';

    public function key(): string
    {
        return 'vinted';
    }

    public function category(): string
    {
        return 'shopping';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Vinted';
    }

    public function siteUrl(): string
    {
        return 'https://www.vinted.co.uk/member/general/search?search_text=';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        $user = strtolower(trim($target));
        return "https://{$this->domain}/member/general/search?search_text={$user}";
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
            // Python parity: match the orchestrator's default browser-ish headers.
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Accept-Language' => 'en-US,en;q=0.9',
            'sec-fetch-dest' => 'document',
        ];
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $user = strtolower(trim($target));

        // Python parity: validate input before making the request.
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $user)) {
            return new ScanResult(
                $target,
                $this->category(),
                $this->siteName(),
                $this->siteUrl(),
                'Error',
                'Usernames can only contain letters, numbers, underscores, periods and dashes',
                mode: $this->mode(),
                key: $this->key()
            );
        }

        if (preg_match('/^[_\\-.]/', $user) || preg_match('/[_\\-.]$/', $user)) {
            return new ScanResult(
                $target,
                $this->category(),
                $this->siteName(),
                $this->siteUrl(),
                'Error',
                'Cannot start/end with a special character',
                mode: $this->mode(),
                key: $this->key()
            );
        }

        try {
            $domains = ['www.vinted.co.uk', 'www.vinted.pt'];
            $bestAvailableReason = '';
            $lastError = null;

            foreach ($domains as $domain) {
                $this->domain = $domain;
                $response = $this->makeRequest($user, $options);
                [$status, $reason] = $this->parseConnectorResponse($response, $user);

                if ($status === 'Taken') {
                    return new ScanResult(
                        $target,
                        $this->category(),
                        $this->siteName(),
                        $this->siteUrl(),
                        $status,
                        $reason,
                        mode: $this->mode(),
                        key: $this->key()
                    );
                }

                if ($status === 'Available') {
                    // Prefer a "closest:" hint if we got one, and prefer the first domain.
                    if ($bestAvailableReason === '' && $reason !== '') {
                        $bestAvailableReason = $reason;
                    }
                    if ($bestAvailableReason === '') {
                        $bestAvailableReason = $reason;
                    }
                }

                if ($status === 'Error') {
                    $lastError = $reason;
                }
            }

            return new ScanResult(
                $target,
                $this->category(),
                $this->siteName(),
                $this->siteUrl(),
                'Available',
                $bestAvailableReason,
                mode: $this->mode(),
                key: $this->key()
            );
        } catch (\Throwable $e) {
            $reason = str_contains(strtolower($e->getMessage()), 'timed out')
                ? 'Request timeout'
                : $e->getMessage();

            return new ScanResult(
                $target,
                $this->category(),
                $this->siteName(),
                $this->siteUrl(),
                'Error',
                $reason,
                mode: $this->mode(),
                key: $this->key()
            );
        }
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        if ($status !== 200) {
            return ['Error', 'Invalid status code'];
        }

        $text = $response->body();
        $logins = [];
        foreach ([
            '/"login":"([A-Za-z0-9_.-]+)"/',
            '/\\\\\"login\\\\\":\\\\\"([A-Za-z0-9_.-]+)\\\\\"/',
            '/"login\\\\":\\\\\\"([A-Za-z0-9_.-]+)/',
            // Sometimes the results only include profile URLs like /member/<id>-<login>
            '/member\\/[0-9]+-([A-Za-z0-9_.-]+)/i',
            // ...or fully-qualified escaped URLs.
            '/member\\\\\\/[0-9]+-([A-Za-z0-9_.-]+)/i',
        ] as $pattern) {
            $matches = [];
            preg_match_all($pattern, $text, $matches);
            foreach (($matches[1] ?? []) as $login) {
                $logins[] = $login;
            }
        }
        $logins = array_values(array_unique($logins));

        if (count($logins) === 0) {
            // If we got served a challenge page, don't misclassify as Not Found.
            $lower = strtolower($text);
            foreach (['captcha', 'challenge', 'verify you are human', 'cloudflare', 'bot check'] as $needle) {
                if ($needle !== '' && str_contains($lower, $needle)) {
                    return ['Error', $this->key() . ': anti-bot challenge detected'];
                }
            }
            return ['Available', ''];
        }

        $targetLower = strtolower($target);
        $loginLower = array_map('strtolower', $logins);
        if (in_array($targetLower, $loginLower, true)) {
            return ['Taken', ''];
        }

        // Python parity: return closest match when possible.
        $best = null;
        $bestScore = 0.0;
        foreach ($logins as $candidate) {
            $candLower = strtolower($candidate);
            $maxLen = max(strlen($targetLower), strlen($candLower));
            if ($maxLen === 0) {
                continue;
            }
            $dist = levenshtein($targetLower, $candLower);
            $score = 1.0 - ($dist / $maxLen);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        if ($best !== null && $bestScore >= 0.6) {
            return ['Available', 'closest: ' . $best];
        }

        return ['Available', ''];
    }
}
