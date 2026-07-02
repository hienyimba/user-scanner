<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class ChessComValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'chess_com';
    }

    public function category(): string
    {
        return 'gaming';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'ChessCom';
    }

    public function siteUrl(): string
    {
        return 'https://chess.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        try {
            $request = Http::timeout(10)->withOptions([
                'verify' => (bool) config('scanner.verify_ssl', false),
            ])->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36',
                'Accept' => 'application/json, text/plain, */*',
                'Accept-Encoding' => 'identity',
                'Content-Type' => 'application/json',
                'sec-ch-ua-platform' => '"Android"',
                'accept-language' => 'en_US',
                'connect-protocol-version' => '1',
                'origin' => 'https://www.chess.com',
                'sec-fetch-site' => 'same-origin',
                'sec-fetch-mode' => 'cors',
                'referer' => 'https://www.chess.com/register',
                'priority' => 'u=1, i',
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->post('https://www.chess.com/rpc/chesscom.authentication.v1.EmailValidationService/Validate', [
                'email' => $target,
            ]);

            if ($response->status() !== 200) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Status ' . $response->status() . ', report is via GitHub issues', mode: $this->mode(), key: $this->key());
            }

            $status = $response->json('status');
            if ($status === 'EMAIL_STATUS_TAKEN') {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
            }
            if ($status === 'EMAIL_STATUS_AVAILABLE') {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unknown status: ' . (string) $status . ', report is via GitHub issues', mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'unexpected exception: ' . $e->getMessage(), mode: $this->mode(), key: $this->key());
        }
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Unexpected response'];
    }
}
