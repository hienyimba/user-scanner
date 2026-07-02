<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class FlickrValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'flickr';
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
        return 'Flickr';
    }

    public function siteUrl(): string
    {
        return 'https://flickr.com';
    }

    protected function requestMethod(): string
    {
        return 'POST';
    }

    protected function requestUrl(string $target): string
    {
        return "https://cognito-idp.us-east-1.amazonaws.com";
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
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',
            'x-amz-target' => 'AWSCognitoIdentityProviderService.SignUp',
            'content-type' => 'application/x-amz-json-1.1',
            'origin' => 'https://identity.flickr.com',
            'referer' => 'https://identity.flickr.com/sign-up',
        ];
    }

    protected function requestRawBody(string $target): ?string
    {
        return json_encode([
            'ClientId' => '3ck15a1ov4f0d3o97vs3tbjb52',
            'Username' => $target,
            'Password' => 'You#are-a-n80',
            'UserAttributes' => [
                ['Name' => 'email', 'Value' => $target],
                ['Name' => 'birthdate', 'Value' => '1983-02-05'],
                ['Name' => 'given_name', 'Value' => 'John'],
                ['Name' => 'family_name', 'Value' => 'Doe'],
                ['Name' => 'locale', 'Value' => 'en-us'],
            ],
            'ValidationData' => [
                ['Name' => 'recaptchaToken', 'Value' => 'Not-required'],
            ],
            'ClientMetadata' => ['referrerUrl' => ''],
        ], JSON_UNESCAPED_SLASHES);
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $body = $response->body();
        if (str_contains($body, 'An account with the given email already exists')) {
            return ['Registered', ''];
        }
        if (str_contains($body, 'PreSignUp failed with error Sign Up failure')) {
            return ['Not Registered', ''];
        }

        return ['Error', 'Unexpected response body'];
    }
}
