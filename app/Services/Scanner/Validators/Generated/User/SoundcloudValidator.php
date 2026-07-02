<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class SoundcloudValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'soundcloud';
    }

    public function category(): string
    {
        return 'music';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Soundcloud';
    }

    public function siteUrl(): string
    {
        return 'https://soundcloud.com';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://soundcloud.com/{$target}";
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
            // No connector-specific headers inferred.
        ];
    }
    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();

        // Python parity: soundcloud has explicit status mapping and body markers.
        if ($status === 403) {
            return ['Error', '[403] Request forbidden try using proxy or VPN'];
        }

        if ($status === 404) {
            return ['Available', ''];
        }

        if ($status === 200) {
            $text = $response->body();

            if (str_contains($text, "soundcloud://users:{$target}")) {
                return ['Taken', ''];
            }
            if (str_contains($text, '"username":"' . $target . '"')) {
                return ['Taken', ''];
            }
            if (str_contains($text, 'soundcloud://users:') && str_contains($text, '"username":"')) {
                return ['Taken', ''];
            }

            return ['Error', 'Unexpected response, report it via GitHub issues'];
        }

        return ['Error', 'Unknown Error report it via GitHub issues'];
    }
}
