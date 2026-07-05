<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\User;

use App\Contracts\ValidatorContract;
use App\DTO\ScanResult;
use Illuminate\Support\Facades\Http;

final class GithubValidator implements ValidatorContract
{
    public function key(): string
    {
        return 'github';
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
        return 'Github';
    }

    public function siteUrl(): string
    {
        return 'https://github.com';
    }

    public function publicProfileUrl(string $target): string
    {
        return 'https://github.com/' . rawurlencode($target);
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $apiUrl = 'https://api.github.com/users/' . rawurlencode($target);
        $showUrl = $this->publicProfileUrl($target);

        $request = Http::timeout(8)->withHeaders([
            'User-Agent' => config('scanner.user_agent'),
            'Accept' => 'application/vnd.github.v3+json',
        ])->withOptions([
            'verify' => (bool) config('scanner.verify_ssl', false),
        ]);

        if (!empty($options['proxy'])) {
            $request = $request->withOptions(['proxy' => $options['proxy']]);
        }

        try {
            $apiResponse = $request->get($apiUrl);

            if ($apiResponse->status() === 200 && is_array($apiResponse->json())) {
                $data = $apiResponse->json();
                $extra = $this->buildApiMetadata($request, $target, $showUrl, is_array($data) ? $data : []);

                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Taken', '', $extra, mode: $this->mode(), key: $this->key());
            }

            if ($apiResponse->status() === 404) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Available', mode: $this->mode(), key: $this->key());
            }

            $htmlRequest = Http::timeout(8)->withHeaders([
                'User-Agent' => config('scanner.user_agent'),
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9',
                'Accept-Language' => 'en-US,en;q=0.9',
            ])->withOptions([
                'verify' => (bool) config('scanner.verify_ssl', false),
            ]);

            if (!empty($options['proxy'])) {
                $htmlRequest = $htmlRequest->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $htmlRequest->get($showUrl);
            if ($response->status() === 404) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Available', mode: $this->mode(), key: $this->key());
            }

            if ($response->status() === 200) {
                $extra = $this->buildHtmlMetadata($response->body());

                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Taken', '', $extra, mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'Unexpected status: ' . $response->status(), mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', $e->getMessage(), mode: $this->mode(), key: $this->key());
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildApiMetadata($request, string $target, string $showUrl, array $data): string
    {
        $metadata = [];

        foreach ([
            'name' => 'Name',
            'bio' => 'Bio',
            'company' => 'Company',
            'location' => 'Location',
        ] as $field => $label) {
            $value = $data[$field] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $metadata[$label] = trim($value);
            }
        }

        $blog = is_string($data['blog'] ?? null) ? trim((string) $data['blog']) : '';
        if ($blog !== '') {
            $metadata['Website'] = $blog;
        }

        $email = is_string($data['email'] ?? null) ? trim((string) $data['email']) : '';
        if ($email === '') {
            $email = $this->extractEmailFromHtml($request, $showUrl);
        }
        if ($email !== '') {
            $metadata['Email'] = $email;
        }

        foreach ([
            'followers' => 'Followers',
            'following' => 'Following',
            'public_repos' => 'Public Repos',
        ] as $field => $label) {
            $value = $data[$field] ?? null;
            if (is_numeric($value)) {
                $metadata[$label] = (string) $value;
            }
        }

        $avatar = is_string($data['avatar_url'] ?? null) ? trim((string) $data['avatar_url']) : '';
        if ($avatar !== '') {
            $metadata['Avatar'] = $avatar;
        }

        $twitter = is_string($data['twitter_username'] ?? null) ? trim((string) $data['twitter_username']) : '';
        if ($twitter !== '') {
            $metadata['Twitter'] = $twitter;
        }

        $createdAt = is_string($data['created_at'] ?? null) ? trim((string) $data['created_at']) : '';
        if ($createdAt !== '') {
            $metadata['Created At'] = $createdAt;
        }

        $links = [];
        if ($blog !== '') {
            $links[] = $blog;
        }

        try {
            $socialResponse = $request->get($apiUrl = 'https://api.github.com/users/' . rawurlencode($target) . '/social_accounts');
            if ($socialResponse->status() === 200 && is_array($socialResponse->json())) {
                foreach ($socialResponse->json() as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $url = $item['url'] ?? null;
                    if (is_string($url) && trim($url) !== '') {
                        $links[] = trim($url);
                    }
                }
            }
        } catch (\Throwable) {
        }

        if ($links !== []) {
            $metadata['Links'] = implode(', ', array_values(array_unique($links)));
        }

        return $this->summarizeMetadata($metadata);
    }

    private function buildHtmlMetadata(string $html): string
    {
        $metadata = [];

        $patterns = [
            'Name' => '/itemprop="name">\s*([^<\n\r]+)\s*</',
            'Bio' => '/class="p-note user-profile-bio[^"]*"[^>]*><div>([^<]+)</',
            'Company' => '/itemprop="worksFor"[^>]*aria-label="Organization:\s*([^"]+)"/',
            'Location' => '/itemprop="homeLocation"[^>]*aria-label="Home location:\s*([^"]+)"/',
            'Email' => '/href="mailto:([^"]+)"/',
            'Followers' => '/class="text-bold color-fg-default">([0-9.]+[kM]?)<\/span>\s*followers/i',
            'Following' => '/class="text-bold color-fg-default">([0-9.]+[kM]?)<\/span>\s*following/i',
            'Avatar' => '/<meta property="og:image" content="([^"]+)"/',
        ];

        foreach ($patterns as $label => $pattern) {
            if (preg_match($pattern, $html, $match) === 1) {
                $value = trim(str_replace('&amp;', '&', $match[1]));
                if ($value !== '') {
                    $metadata[$label] = $value;
                }
            }
        }

        if (preg_match_all('/<li\s+[^>]*itemprop="(url|social)"[^>]*>([\s\S]*?)<\/li>/', $html, $matches, PREG_SET_ORDER) !== false) {
            $links = [];
            foreach ($matches as $match) {
                if (preg_match('/href="([^"]+)"/', $match[2], $hrefMatch) === 1) {
                    $url = trim(str_replace('&amp;', '&', $hrefMatch[1]));
                    if ($url !== '') {
                        $links[] = $url;
                        if ($match[1] === 'url' && !isset($metadata['Website'])) {
                            $metadata['Website'] = $url;
                        }
                    }
                }
            }

            if ($links !== []) {
                $metadata['Links'] = implode(', ', array_values(array_unique($links)));
            }
        }

        if (preg_match_all('/data-hovercard-type="organization"[^>]*href="\/([^\/"]+)"/', $html, $matches) === 1 && $matches[1] !== []) {
            $metadata['Organizations'] = implode(', ', array_values(array_unique($matches[1])));
        }

        return $this->summarizeMetadata($metadata);
    }

    private function extractEmailFromHtml($request, string $showUrl): string
    {
        try {
            $response = $request->withHeaders([
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9',
            ])->get($showUrl);
            if ($response->status() !== 200) {
                return '';
            }

            if (preg_match('/href="mailto:([^"]+)"/', $response->body(), $match) === 1) {
                return trim($match[1]);
            }
        } catch (\Throwable) {
        }

        return '';
    }

    /**
     * @param array<string, string> $metadata
     */
    private function summarizeMetadata(array $metadata): string
    {
        $lines = [];
        foreach ($metadata as $label => $value) {
            $value = trim($value);
            if ($value !== '') {
                $lines[] = $label . ': ' . $value;
            }
        }

        return implode("\n", $lines);
    }
}
