<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated;

use App\Contracts\ValidatorContract;
use App\DTO\ScanResult;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

abstract class BaseGeneratedValidator implements ValidatorContract
{
    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return $this->siteUrl();
    }

    /** @return array<string,string> */
    protected function requestHeaders(): array
    {
        return [];
    }

    /** @return array<string,mixed> */
    protected function requestQuery(string $target): array
    {
        return [];
    }

    /** @return array<string,mixed> */
    protected function requestBody(string $target): array
    {
        return [];
    }

    /**
     * @return array{0:string,1:string}
     */
    abstract protected function parseConnectorResponse(Response $response, string $target): array;

    protected function makeRequest(string $target, array $options = []): Response
    {
        $request = Http::timeout(10)->withHeaders(array_merge([
            'User-Agent' => config('scanner.user_agent'),
            'Accept' => 'text/html,application/json,*/*;q=0.8',
        ], $this->requestHeaders()));

        if (!empty($options['proxy'])) {
            $request = $request->withOptions(['proxy' => $options['proxy']]);
        }

        $method = strtoupper($this->requestMethod());
        $url = $this->requestUrl($target);
        $query = $this->requestQuery($target);
        $body = $this->requestBody($target);

        if ($method === 'GET') {
            return $request->get($url, $query);
        }

        if ($method === 'POST') {
            $headers = array_change_key_case($this->requestHeaders(), CASE_LOWER);
            $contentType = (string) ($headers['content-type'] ?? '');
            $expectsJson = str_contains(strtolower($contentType), 'application/json');

            if ($body !== []) {
                return $expectsJson ? $request->post($url, $body) : $request->asForm()->post($url, $body);
            }

            return $request->post($url, $query);
        }

        /** @var Response $response */
        $response = $request->send($method, $url, [
            'query' => $query,
            'form_params' => $body,
        ]);

        return $response;
    }

    public function check(string $target, array $options = []): ScanResult
    {
        try {
            $response = $this->makeRequest($target, $options);
            [$status, $reason] = $this->parseConnectorResponse($response, $target);

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), $status, $reason);
        } catch (\Throwable $e) {
            $reason = str_contains(strtolower($e->getMessage()), 'timed out')
                ? 'Request timeout'
                : $e->getMessage();

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $reason);
        }
    }
}
