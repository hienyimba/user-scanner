<?php

namespace Tests;

use App\Services\Scanner\PhpFfiTransport;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Parser suites retain their Http::fake fixtures. Native transport tests
        // instantiate the real transport directly or replace this binding.
        $this->app->instance(PhpFfiTransport::class, new HttpFakeFfiTransport);
    }
}

/** Keeps parser fixtures on Http::fake without a production HTTP fallback. */
final class HttpFakeFfiTransport extends PhpFfiTransport
{
    public function send(array $spec, CookieJar $jar): Response
    {
        $pending = Http::timeout($spec['timeout'] ?? 15)
            ->withHeaders($spec['headers'] ?? [])
            ->withOptions(['cookies' => $jar, 'allow_redirects' => $spec['follow_redirects'] ?? true,
                'verify' => true, 'proxy' => $spec['proxy'] ?? null]);
        $method = strtoupper($spec['method'] ?? 'GET');
        $query = $spec['query'] ?? [];
        if ($method === 'GET') {
            return $pending->get($spec['url'], $query);
        }
        if ($query !== []) {
            $pending = $pending->withOptions(['query' => $query]);
        }
        $body = $spec['body'] ?? [];
        if (($spec['body_mode'] ?? 'json') === 'raw') {
            return $pending->withBody(is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR),
                (string) ($spec['headers']['Content-Type'] ?? 'text/plain'))->send($method, $spec['url']);
        }
        if (($spec['body_mode'] ?? 'json') === 'form') {
            return $pending->asForm()->send($method, $spec['url'], ['form_params' => $body]);
        }

        return $pending->send($method, $spec['url'], ['json' => $body]);
    }
}
