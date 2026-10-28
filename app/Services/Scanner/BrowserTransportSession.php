<?php

declare(strict_types=1);

namespace App\Services\Scanner;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use WeakMap;

/** Shared single-hop redirect policy. Native transports never follow redirects. */
final class BrowserTransportSession
{
    /** @var WeakMap<CookieJar,array<string,string|null>>|null Host-only metadata the Guzzle jar does not retain. */
    private static ?WeakMap $cookieScopes = null;

    public function send(array $spec, CookieJar $jar, callable $singleHop): Response
    {
        $sensitive = (array) ($spec['redact_values'] ?? []);
        try {
            $url = (string) ($spec['url'] ?? '');
            if (($spec['query'] ?? []) !== []) {
                $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($spec['query']);
            }
            unset($spec['query'], $spec['cookie']);
            $spec['url'] = $url;
            $spec['method'] = strtoupper((string) ($spec['method'] ?? 'GET'));
            $originHost = strtolower((string) parse_url($url, PHP_URL_HOST));
            $allowedHosts = array_unique([$originHost, str_starts_with($originHost, 'www.')
                ? substr($originHost, 4) : 'www.'.$originHost, ...((array) ($spec['allowed_redirect_hosts'] ?? []))]);
            $timeout = min(max(0.05, (float) ($spec['timeout'] ?? 15)),
                max(1, (float) config('scanner.browser_transport.max_request_seconds', 15)));
            $deadline = min((int) ($spec['deadline_ns'] ?? PHP_INT_MAX), hrtime(true) + (int) ($timeout * 1e9));
            $maxRedirects = max(0, min(10, (int) config('scanner.browser_transport.max_redirects', 5)));
            $follow = (bool) ($spec['follow_redirects'] ?? true);

            for ($redirects = 0; ; $redirects++) {
                $this->validateUrl($spec['url'], $allowedHosts);
                $remaining = ($deadline - hrtime(true)) / 1e9;
                if ($remaining <= 0) {
                    throw new ConnectionException('Browser transport deadline exceeded');
                }
                $spec['timeout'] = $remaining;
                $spec['connect_timeout'] = min($remaining, max(1, (float) config('scanner.http.connect_timeout_seconds', 10)));
                $spec['follow_redirects'] = false;
                $request = new Request($spec['method'], $spec['url']);
                $headers = (array) ($spec['headers'] ?? []);
                $jarHeader = $this->cookieHeader($jar, $request);
                // An explicit module cookie is kept only on its original origin.
                $explicitCookie = '';
                foreach ($headers as $name => $value) {
                    if (strcasecmp((string) $name, 'Cookie') === 0) {
                        $explicitCookie = (string) $value;
                        unset($headers[$name]);
                    }
                }
                $cookie = implode('; ', array_filter([$explicitCookie, $jarHeader], static fn ($v) => $v !== ''));
                if ($cookie !== '') {
                    $headers['Cookie'] = $cookie;
                }
                $hop = $spec;
                $hop['headers'] = $headers;
                $response = $singleHop($hop);
                if (! $response instanceof Response) {
                    throw new ConnectionException('Browser transport returned an invalid response');
                }
                if (strlen($response->body()) > max(1024, (int) config('scanner.browser_transport.max_body_bytes', 8388608))) {
                    throw new ConnectionException('Browser transport response exceeded its byte limit');
                }
                $this->storeCookies($jar, $request, $response);
                if (hrtime(true) > $deadline) {
                    throw new ConnectionException('Browser transport deadline exceeded');
                }
                $location = $response->header('Location');
                if (! $follow || ! in_array($response->status(), [301, 302, 303, 307, 308], true) || $location === '') {
                    return $response;
                }
                if ($redirects >= $maxRedirects) {
                    throw new ConnectionException('Browser transport redirect limit exceeded');
                }
                $next = (string) UriResolver::resolve(new Uri($spec['url']), new Uri($location));
                $oldOrigin = $this->origin($spec['url']);
                $crossOrigin = $oldOrigin !== $this->origin($next);
                $switchToGet = ($response->status() === 303 && $spec['method'] !== 'HEAD')
                    || (in_array($response->status(), [301, 302], true) && $spec['method'] === 'POST');
                if ($crossOrigin && ! $switchToGet && ! in_array($spec['method'], ['GET', 'HEAD'], true)) {
                    throw new ConnectionException('Cross-origin redirect with a request body was refused');
                }
                if ($crossOrigin) {
                    $spec['headers'] = array_filter((array) ($spec['headers'] ?? []), static fn ($name) => in_array(strtolower((string) $name), ['user-agent', 'accept', 'accept-language', 'accept-encoding'], true), ARRAY_FILTER_USE_KEY);
                }
                if ($switchToGet) {
                    $spec['method'] = 'GET';
                    $spec['body'] = null;
                    foreach (array_keys((array) ($spec['headers'] ?? [])) as $name) {
                        if (in_array(strtolower((string) $name), ['content-type', 'content-length', 'transfer-encoding'], true)) {
                            unset($spec['headers'][$name]);
                        }
                    }
                }
                $spec['url'] = $next;
            }
        } catch (\Throwable $error) {
            // Never include raw child stdout/stderr, payloads or exception objects.
            $reason = ScannerDiagnostics::exception($error, 'browser.transport', ['redact_values' => $sensitive]);
            if ($error instanceof NativeTransportException) {
                throw new NativeTransportException($reason, $error->diagnostics);
            }
            throw new ConnectionException($reason);
        }
    }

    private function validateUrl(string $url, array $allowedHosts): void
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $loopback = in_array($host, ['127.0.0.1', 'localhost', '::1', '[::1]'], true)
            && (bool) config('scanner.browser_transport.allow_loopback', false);
        if ($host === '' || ! in_array($host, $allowedHosts, true) || isset($parts['user']) || isset($parts['pass'])
            || preg_match('/[\x00-\x20\x7F]/', $url) || ($scheme !== 'https' && ! ($loopback && $scheme === 'http'))
            || (! $loopback && filter_var($host, FILTER_VALIDATE_IP)
                && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
            throw new ConnectionException('Browser transport URL/redirect policy refused the destination');
        }
    }

    private function origin(string $url): string
    {
        $parts = parse_url($url);

        return strtolower(($parts['scheme'] ?? '').'://'.($parts['host'] ?? '')).':'.($parts['port'] ?? (($parts['scheme'] ?? '') === 'https' ? 443 : 80));
    }

    private function cookieKey(SetCookie $cookie): string
    {
        return $cookie->getName().'|'.strtolower((string) $cookie->getDomain()).'|'.$cookie->getPath();
    }

    private function storeCookies(CookieJar $jar, Request $request, Response $response): void
    {
        self::$cookieScopes ??= new WeakMap;
        $scopes = self::$cookieScopes[$jar] ?? [];
        $host = strtolower($request->getUri()->getHost());
        foreach ($response->toPsrResponse()->getHeader('Set-Cookie') as $line) {
            $cookie = SetCookie::fromString($line);
            $hostOnly = ! $cookie->getDomain();
            if ($hostOnly) {
                $cookie->setDomain($host);
            }
            $domain = ltrim(strtolower((string) $cookie->getDomain()), '.');
            if (! preg_match('/^[a-z0-9.-]+$/D', $domain) || str_ends_with($domain, '.')
                || ! $cookie->matchesDomain($host) || (! $hostOnly && ! str_contains($domain, '.') && $domain !== 'localhost')) {
                continue;
            }
            $cookie->setDomain($domain);
            if ((str_starts_with($cookie->getName(), '__Secure-') && (! $cookie->getSecure() || $request->getUri()->getScheme() !== 'https'))
                || (str_starts_with($cookie->getName(), '__Host-') && (! $hostOnly || ! $cookie->getSecure() || $cookie->getPath() !== '/'
                    || ! preg_match('/;\s*path=\//i', $line) || $request->getUri()->getScheme() !== 'https'))) {
                continue;
            }
            if (! preg_match('/;\s*path=/i', $line) || ! str_starts_with((string) $cookie->getPath(), '/')) {
                $path = $request->getUri()->getPath();
                $cookie->setPath(str_contains($path, '/') ? (substr($path, 0, (int) strrpos($path, '/')) ?: '/') : '/');
            }
            // Guzzle's SetCookie treats Max-Age=0 as falsy, not as deletion.
            if ((preg_match('/;\s*max-age\s*=\s*(-?\d+)/i', $line, $age) && (int) $age[1] <= 0) || $cookie->isExpired()) {
                $jar->clear($cookie->getDomain(), $cookie->getPath(), $cookie->getName());
                unset($scopes[$this->cookieKey($cookie)]);

                continue;
            }
            // Replace even when only expiry/attributes changed; the legacy
            // Guzzle conflict heuristic can otherwise retain stale attributes.
            $jar->clear($cookie->getDomain(), $cookie->getPath(), $cookie->getName());
            $jar->setCookie($cookie);
            $scopes[$this->cookieKey($cookie)] = $hostOnly ? $host : null;
        }
        self::$cookieScopes[$jar] = $scopes;
    }

    private function cookieHeader(CookieJar $jar, Request $request): string
    {
        $scopes = self::$cookieScopes[$jar] ?? [];
        $pairs = [];
        foreach ($jar as $cookie) {
            // Unknown externally populated cookies default to the safer host-only scope.
            $hostOnly = $scopes[$this->cookieKey($cookie)] ?? (array_key_exists($this->cookieKey($cookie), $scopes) ? null : ltrim((string) $cookie->getDomain(), '.'));
            if ($cookie->isExpired() || ! $cookie->matchesDomain($request->getUri()->getHost())
                || ($hostOnly !== null && strcasecmp($hostOnly, $request->getUri()->getHost()) !== 0)
                || ! $cookie->matchesPath($request->getUri()->getPath() ?: '/')
                || ($cookie->getSecure() && $request->getUri()->getScheme() !== 'https')) {
                continue;
            }
            $pairs[] = ['path_length' => strlen($cookie->getPath()), 'value' => $cookie->getName().'='.$cookie->getValue()];
        }
        usort($pairs, static fn ($left, $right) => $right['path_length'] <=> $left['path_length']);

        return implode('; ', array_column($pairs, 'value'));
    }
}
