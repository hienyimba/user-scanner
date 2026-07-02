<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class RenderValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'render';
    }

    public function category(): string
    {
        return 'hosting';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Render';
    }

    public function siteUrl(): string
    {
        return 'https://render.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        try {
            $request = Http::timeout(5)->withOptions([
                'verify' => (bool) config('scanner.verify_ssl', false),
            ])->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',
                'Content-Type' => 'application/json',
                'origin' => 'https://dashboard.render.com',
                'referer' => 'https://dashboard.render.com/register',
                'accept-language' => 'en-US,en;q=0.9',
                'Accept-Encoding' => 'identity',
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->post('https://api.render.com/graphql', [
                'operationName' => 'signUp',
                'variables' => [
                    'signup' => [
                        'email' => $target,
                        'githubId' => '',
                        'name' => '',
                        'githubToken' => '',
                        'googleId' => '',
                        'gitlabId' => '',
                        'bitbucketId' => '',
                        'inviteCode' => '',
                        'password' => 'StandardPassword123!',
                        'newsletterOptIn' => false,
                        'next' => '',
                    ],
                ],
                'query' => "mutation signUp(\$signup: SignupInput!) {\n  signUp(signup: \$signup) {\n    idToken\n    __typename\n  }\n}\n",
            ]);

            if ($response->status() === 429) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', "Rate limited, use '-d' flag to avoid bot detection", mode: $this->mode(), key: $this->key());
            }

            $errors = is_array($response->json('errors')) ? $response->json('errors') : [];
            if ($errors !== []) {
                $message = (string) (($errors[0]['message'] ?? '') ?: '');
                if (str_contains($message, '"email":"exists"')) {
                    return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
                }
                if (str_contains($message, '"hcaptcha_token":"invalid"')) {
                    return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
                }
                if (str_contains($message, '"email":"invalid"') && filter_var($target, FILTER_VALIDATE_EMAIL) !== false) {
                    return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
                }
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Render Error: ' . $message, mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected error, report it via GitHub issues', mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $e->getMessage(), mode: $this->mode(), key: $this->key());
        }
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Unexpected response'];
    }
}
