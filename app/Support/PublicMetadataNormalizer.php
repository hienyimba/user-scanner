<?php

declare(strict_types=1);

namespace App\Support;

final class PublicMetadataNormalizer
{
    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public static function normalize(array $metadata): array
    {
        if ($metadata === []) {
            return $metadata;
        }

        if (array_key_exists('avatar_url', $metadata)) {
            $metadata['avatar_url'] = self::normalizePublicUrl($metadata['avatar_url']);
        }

        if (array_key_exists('website_url', $metadata)) {
            $metadata['website_url'] = self::normalizePublicUrl($metadata['website_url']);
        }

        if (array_key_exists('public_email', $metadata)) {
            $metadata['public_email'] = self::normalizePublicEmail($metadata['public_email']);
        }

        if (array_key_exists('external_links', $metadata)) {
            $metadata['external_links'] = self::normalizePublicLinks($metadata['external_links']);
        }

        return $metadata;
    }

    /**
     * @return array<int, string>
     */
    public static function normalizePublicLinks(mixed $value): array
    {
        $candidates = [];

        if (is_string($value)) {
            $candidates = preg_split('/\s*,\s*/', $value) ?: [];
        } elseif (is_array($value)) {
            $candidates = $value;
        }

        $links = [];
        foreach ($candidates as $candidate) {
            $normalized = self::normalizePublicUrl($candidate);
            if ($normalized !== null) {
                $links[] = $normalized;
            }
        }

        return array_values(array_unique($links));
    }

    public static function normalizePublicEmail(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $email = trim((string) $value);
        if ($email === '') {
            return null;
        }

        $email = preg_replace('/^mailto:/i', '', $email);
        if (!is_string($email)) {
            return null;
        }

        $email = trim($email);
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $email;
    }

    public static function normalizePublicUrl(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $url = html_entity_decode(trim((string) $value), ENT_QUOTES | ENT_HTML5);
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }

        if (preg_match('#^https?://#i', $url) !== 1) {
            return null;
        }

        $parsed = parse_url($url);
        if (!is_array($parsed)) {
            return null;
        }

        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
        $host = trim((string) ($parsed['host'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        $normalized = $scheme . '://' . $host;
        if (isset($parsed['port']) && is_int($parsed['port'])) {
            $normalized .= ':' . $parsed['port'];
        }

        $normalized .= (string) ($parsed['path'] ?? '');

        if (($parsed['query'] ?? '') !== '') {
            $normalized .= '?' . $parsed['query'];
        }

        return $normalized;
    }
}
