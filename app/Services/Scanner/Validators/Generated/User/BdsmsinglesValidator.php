<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class BdsmsinglesValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'bdsmsingles';
    }

    public function category(): string
    {
        return 'adult';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Bdsmsingles';
    }

    public function siteUrl(): string
    {
        return 'https://www.bdsmsingles.com/members/{user}/';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $url = 'https://www.bdsmsingles.com/members/'.rawurlencode($target).'/';

        try {
            $client = Http::timeout(15)->withOptions([
                'allow_redirects' => false,
                'verify' => (bool) config('scanner.verify_ssl', false),
            ])->withHeaders(['User-Agent' => (string) config('scanner.user_agent')]);
            if (! empty($options['proxy'])) {
                $client = $client->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $client->get($url);
            if ($response->status() === 202) {
                if (preg_match('/prefix="([0-9a-f]+)",win="(\d+)",diff=(\d+)/', $response->body(), $challenge) !== 1
                    || $challenge[3] !== '4') {
                    return $this->result($target, $url, 'Error', 'Unsupported BDSM Singles browser challenge');
                }

                $nonce = $this->solveChallenge($challenge[1]);
                if ($nonce === null) {
                    return $this->result($target, $url, 'Error', 'Could not solve BDSM Singles browser challenge');
                }

                $response = $client
                    ->withCookies(['_jscp' => $challenge[2].'.'.$nonce], 'www.bdsmsingles.com')
                    ->get($url);
            }

            if ($response->status() === 200 && str_contains($response->body(), '<title>Profile')) {
                return $this->result($target, $url, 'Taken');
            }
            if ($response->status() === 302 && $response->header('Location') === '/') {
                return $this->result($target, $url, 'Available');
            }
            if (in_array($response->status(), [401, 403, 429], true)) {
                return $this->result(
                    $target,
                    $url,
                    'Error',
                    'BDSM Singles blocked/rate-limited the request (HTTP '.$response->status().')',
                );
            }

            return $this->result($target, $url, 'Error', 'Unexpected BDSM Singles response');
        } catch (\Throwable $e) {
            return $this->result($target, $url, 'Error', $e->getMessage());
        }
    }

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'BDSM Singles uses a browser challenge flow'];
    }

    private function solveChallenge(string $prefix): ?int
    {
        for ($nonce = 0; $nonce < (1 << 20); $nonce++) {
            if (str_starts_with(hash('sha256', $prefix.$nonce), '0000')) {
                return $nonce;
            }
        }

        return null;
    }

    private function result(string $target, string $url, string $status, string $reason = ''): ScanResult
    {
        return new ScanResult(
            $target,
            $this->category(),
            $this->siteName(),
            $url,
            $status,
            $reason,
            mode: $this->mode(),
            key: $this->key(),
            profileUrl: $status === 'Taken' ? $url : null,
        );
    }
}
