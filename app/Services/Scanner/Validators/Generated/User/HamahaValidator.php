<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class HamahaValidator extends BaseGeneratedValidator
{
    public function key(): string { return 'hamaha'; }
    public function category(): string { return 'finance'; }
    public function mode(): string { return 'username'; }
    public function siteName(): string { return 'Hamaha'; }
    public function siteUrl(): string { return 'https://hamaha.net'; }
    protected function requestUrl(string $target): string { return "https://hamaha.net/{$target}"; }
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $body = $response->body();
        if (str_contains($body, 'id="profile"')) {
            return ['Taken', ''];
        }
        if (str_contains($body, 'content="HAMAHA  Биткоин форум. Торговля на бирже - ➨ Обучение Криптовалютам, Биткоин и NYSE "')) {
            return ['Available', ''];
        }
        return ['Error', 'Unexpected response body, report it via GitHub issues.'];
    }
}
