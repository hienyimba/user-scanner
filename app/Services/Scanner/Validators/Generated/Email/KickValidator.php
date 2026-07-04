<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Symfony\Component\Process\Process;

final class KickValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'kick';
    }

    public function category(): string
    {
        return 'creator';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Kick';
    }

    public function siteUrl(): string
    {
        return 'https://kick.com/';
    }

    protected function requestMethod(): string
    {
        return 'POST';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://kick.com/api/v1/signup/verify/email';
    }

    protected function requestHeadersForTarget(string $target): array
    {
        return [
            'User-Agent' => 'KickMobile/40.18.1 (com.kick.mobile; platform: android; build:60006868)',
            'Accept' => 'application/json',
            'Accept-Encoding' => 'gzip',
            'Content-Type' => 'application/json',
            'cache-control' => 'no-store',
            'x-app-platform' => 'Android',
            'x-app-version' => '40.18.1',
            'x-kick-app' => 'mobile',
            'x-req-trace' => bin2hex(random_bytes(16)),
        ];
    }

    protected function requestBodyMode(): string
    {
        return 'json';
    }

    protected function requestBody(string $target): array
    {
        return [
            'email' => $target,
        ];
    }

    protected function timeoutSeconds(): int
    {
        return 7;
    }

    public function check(string $target, array $options = []): ScanResult
    {
        if (!empty($options['proxy'])) {
            try {
                [$status, $body] = $this->performCurlBinaryRequest($target, (string) $options['proxy']);
                [$resultStatus, $reason] = $this->parseResponsePayload($status, $body, json_decode($body, true));

                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), $resultStatus, $reason, mode: $this->mode(), key: $this->key());
            } catch (\Throwable $e) {
                if (function_exists('curl_init')) {
                    try {
                        [$status, $body] = $this->performPhpCurlRequest($target, (string) $options['proxy']);
                        [$resultStatus, $reason] = $this->parseResponsePayload($status, $body, json_decode($body, true));

                        return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), $resultStatus, $reason, mode: $this->mode(), key: $this->key());
                    } catch (\Throwable $fallback) {
                        return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $fallback->getMessage(), mode: $this->mode(), key: $this->key());
                    }
                }

                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $e->getMessage(), mode: $this->mode(), key: $this->key());
            }
        }

        return parent::check($target, $options);
    }

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return $this->parseResponsePayload($response->status(), $response->body(), $response->json());
    }

    /**
     * @param array<string, mixed>|null $json
     * @return array{0:string,1:string}
     */
    private function parseResponsePayload(int $status, string $body, ?array $json = null): array
    {
        if ($status === 403) {
            return ['Error', 'Caught by Cloudflare WAF (403)'];
        }
        if ($status === 429) {
            return ['Error', 'Rate limited by Kick (429)'];
        }
        if ($status === 204) {
            return ['Not Registered', ''];
        }
        if ($status === 422) {
            foreach ((array) data_get($json, 'errors.email', []) as $error) {
                if (str_contains(strtolower((string) $error), 'already been taken')) {
                    return ['Registered', ''];
                }
            }

            return ['Error', 'Failed to parse 422 validation content'];
        }

        return ['Error', 'Unexpected response state (HTTP ' . $status . ')'];
    }

    /**
     * @return array{0:int,1:string}
     */
    private function performCurlBinaryRequest(string $target, string $proxy): array
    {
        $curlBinary = $this->resolveCurlBinary();
        if ($curlBinary === null) {
            throw new \RuntimeException('curl binary is not available');
        }

        $payload = json_encode(['email' => $target], JSON_THROW_ON_ERROR);
        $payloadFile = tempnam(sys_get_temp_dir(), 'kick-email-');
        if ($payloadFile === false) {
            throw new \RuntimeException('Failed to allocate temporary payload file');
        }

        try {
            if (file_put_contents($payloadFile, $payload) === false) {
                throw new \RuntimeException('Failed to write temporary payload file');
            }

            $command = [
                $curlBinary,
                '-sS',
                '--max-time',
                (string) $this->timeoutSeconds(),
                '--proxy',
                $this->binaryProxyTarget($proxy),
                '--data-binary',
                '@' . $payloadFile,
                '--write-out',
                "\n__CURL_HTTP_CODE__:%{http_code}",
                $this->requestUrl($target),
            ];

            if ($this->proxyCredentials($proxy) !== null) {
                array_splice($command, 6, 0, ['--proxy-user', $this->proxyCredentials($proxy)]);
            }

            foreach ($this->requestHeadersForTarget($target) as $name => $value) {
                array_splice($command, count($command) - 3, 0, ['-H', $name . ': ' . $value]);
            }

            $process = new Process($command);
            $process->setTimeout($this->timeoutSeconds() + 3);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \RuntimeException(trim($process->getErrorOutput()) !== '' ? trim($process->getErrorOutput()) : 'curl binary transport failed');
            }

            $output = $process->getOutput();
            $marker = "\n__CURL_HTTP_CODE__:";
            $markerPos = strrpos($output, $marker);
            if ($markerPos === false) {
                throw new \RuntimeException('curl binary transport returned an unreadable response');
            }

            $body = substr($output, 0, $markerPos);
            $status = (int) trim(substr($output, $markerPos + strlen($marker)));

            return [$status, $body];
        } finally {
            @unlink($payloadFile);
        }
    }

    /**
     * @return array{0:int,1:string}
     */
    private function performPhpCurlRequest(string $target, string $proxy): array
    {
        $payload = json_encode(['email' => $target], JSON_THROW_ON_ERROR);
        $headers = [];
        foreach ($this->requestHeadersForTarget($target) as $name => $value) {
            $headers[] = $name . ': ' . $value;
        }
        $headers[] = 'Content-Length: ' . strlen($payload);

        $curl = curl_init($this->requestUrl($target));
        if ($curl === false) {
            throw new \RuntimeException('Failed to initialize curl transport');
        }

        try {
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT => $this->timeoutSeconds(),
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_PROXY => $this->proxyTarget($proxy),
                CURLOPT_SSL_VERIFYPEER => (bool) config('scanner.verify_ssl', false),
                CURLOPT_SSL_VERIFYHOST => (bool) config('scanner.verify_ssl', false) ? 2 : 0,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_HEADER => true,
                CURLOPT_ENCODING => '',
            ]);

            if ($this->proxyCredentials($proxy) !== null) {
                curl_setopt($curl, CURLOPT_PROXYUSERPWD, $this->proxyCredentials($proxy));
            }

            $response = curl_exec($curl);
            if ($response === false) {
                throw new \RuntimeException(curl_error($curl));
            }

            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);

            return [$status, substr($response, $headerSize)];
        } finally {
            curl_close($curl);
        }
    }

    private function resolveCurlBinary(): ?string
    {
        foreach (['curl.exe', 'curl'] as $candidate) {
            $resolved = (string) @shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where ' . $candidate : 'command -v ' . $candidate);
            $resolved = trim(strtok($resolved, PHP_EOL) ?: '');
            if ($resolved !== '') {
                return $resolved;
            }
        }

        return null;
    }

    private function proxyTarget(string $proxy): string
    {
        $parts = parse_url($proxy);
        if (!is_array($parts) || empty($parts['host']) || empty($parts['port'])) {
            return $proxy;
        }

        return $parts['host'] . ':' . $parts['port'];
    }

    private function proxyCredentials(string $proxy): ?string
    {
        $parts = parse_url($proxy);
        if (!is_array($parts) || empty($parts['user']) || !array_key_exists('pass', $parts)) {
            return null;
        }

        return rawurldecode((string) $parts['user']) . ':' . rawurldecode((string) $parts['pass']);
    }

    private function binaryProxyTarget(string $proxy): string
    {
        $parts = parse_url($proxy);
        if (!is_array($parts) || empty($parts['host']) || empty($parts['port'])) {
            return $proxy;
        }

        $scheme = !empty($parts['scheme']) ? $parts['scheme'] . '://' : '';

        return $scheme . $parts['host'] . ':' . $parts['port'];
    }
}
