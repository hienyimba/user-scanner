<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Client\Response;

final class HackernewsValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'hackernews';
    }

    public function category(): string
    {
        return 'community';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Hackernews';
    }

    public function siteUrl(): string
    {
        return 'https://news.ycombinator.com/user?id=';
    }

    protected function requestUrl(string $target): string
    {
        return "https://news.ycombinator.com/user?id={$target}";
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        $body = strtolower($response->body());

        if (str_contains($body, 'no such user.')) {
            return ['Available', ''];
        }

        if ($status === 200) {
            return ['Taken', ''];
        }

        return ['Error', 'Unexpected status: ' . $status];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        $metadata = parent::buildStructuredMetadata($response, $target, $status);
        if (!in_array($status, ['Taken', 'Found'], true) || $response->status() !== 200) {
            return $metadata;
        }

        $document = new DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($response->body());
        libxml_clear_errors();
        if ($loaded === false) {
            return $metadata;
        }

        $xpath = new DOMXPath($document);
        $rows = $xpath->query('//tr[td[@valign="top"]]');
        if ($rows === false) {
            return $metadata;
        }

        $byLabel = [];
        foreach ($rows as $row) {
            $cells = $xpath->query('./td', $row);
            if ($cells === false || $cells->length < 2) {
                continue;
            }

            $label = strtolower(trim(str_replace(':', '', $cells->item(0)?->textContent ?? '')));
            if ($label === '') {
                continue;
            }

            $byLabel[$label] = $cells->item(1);
        }

        $user = trim((string) ($byLabel['user']?->textContent ?? ''));
        if ($user !== '') {
            $metadata['display_name'] ??= $user;
        }

        $created = trim((string) ($byLabel['created']?->textContent ?? ''));
        if ($created !== '') {
            try {
                $metadata['created_at'] ??= (new \DateTimeImmutable($created))
                    ->setTimezone(new \DateTimeZone('UTC'))
                    ->format(DATE_ATOM);
            } catch (\Throwable) {
                $metadata['created_at'] ??= $created;
            }
        }

        $about = trim(preg_replace('/\s+/', ' ', (string) ($byLabel['about']?->textContent ?? '')) ?? '');
        if ($about !== '') {
            $metadata['bio'] ??= $about;
        }

        $karma = trim((string) ($byLabel['karma']?->textContent ?? ''));
        if ($karma !== '' && ctype_digit(str_replace(',', '', $karma))) {
            $metadata['karma'] = (int) str_replace(',', '', $karma);
        } elseif ($karma !== '') {
            $metadata['karma'] = $karma;
        }

        $aboutLinks = $xpath->query('.//a[@href]/@href', $byLabel['about'] ?? null);
        if ($aboutLinks !== false) {
            $links = $metadata['external_links'] ?? [];
            foreach ($aboutLinks as $linkNode) {
                $href = trim((string) $linkNode->nodeValue);
                if ($href !== '' && (str_starts_with($href, 'http://') || str_starts_with($href, 'https://'))) {
                    $links[] = $href;
                }
            }
            $metadata['external_links'] = array_values(array_unique($links));
        }

        return $metadata;
    }

    protected function buildExtraMetadata(Response $response, string $target, string $status): string
    {
        if (!in_array($status, ['Taken', 'Found'], true) || $response->status() !== 200) {
            return '';
        }

        $metadata = $this->buildStructuredMetadata($response, $target, $status);
        $summary = [];
        if (is_string($metadata['display_name'] ?? null) && $metadata['display_name'] !== '') {
            $summary['Name'] = $metadata['display_name'];
        }
        if (isset($metadata['karma'])) {
            $summary['Karma'] = (string) $metadata['karma'];
        }
        if (is_string($metadata['created_at'] ?? null) && $metadata['created_at'] !== '') {
            $summary['Created At'] = $metadata['created_at'];
        }
        if (is_string($metadata['bio'] ?? null) && $metadata['bio'] !== '') {
            $summary['Bio'] = $metadata['bio'];
        }

        return $this->metadataSummary($summary);
    }
}
