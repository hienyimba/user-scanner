<?php

declare(strict_types=1);

namespace App\Services\Scanner;

use DOMDocument;
use DOMXPath;

/** Read server-provided profile evidence without submitting age verification. */
final class XhamsterProfileEvidence
{
    public function targetFromUrl(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);
        if (! in_array($host, ['xhamster.com', 'www.xhamster.com'], true)
            || preg_match('~^/users/(?:profiles/)?([^/]+)/?$~i', $path, $match) !== 1) {
            return null;
        }

        return rawurldecode($match[1]);
    }

    /** @return array{status:string, age_gate:bool, metadata:array<string,mixed>} */
    public function inspect(string $html, string $target): array
    {
        $unknown = ['status' => 'Error', 'age_gate' => false, 'metadata' => []];
        if (trim($html) === '') {
            return $unknown;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (! $loaded) {
            return $unknown;
        }
        $xpath = new DOMXPath($document);
        $initials = [];
        foreach ($xpath->query('//script[not(@src)]') as $script) {
            if (preg_match('~^\s*window\.initials\s*=\s*(\{.*\})\s*;?\s*$~s', $script->textContent, $match) !== 1) {
                continue;
            }
            $decoded = json_decode($match[1], true);
            if (is_array($decoded)) {
                $initials = $decoded;
                break;
            }
        }

        $lower = strtolower($html);
        $ageGate = ($initials['isBlurred'] ?? null) === true;
        foreach (['pass free age verification', 'no id required. no data stored.', 'requiring us to verify your age', 'age-verification', 'age verification | xhamster'] as $marker) {
            $ageGate = $ageGate || str_contains($lower, $marker);
        }

        // Translation dictionaries and recommendation cards can mention missing
        // users. Only the page's own error state/title/heading is authoritative.
        $missing = ($initials['is404'] ?? null) === true;
        foreach ($xpath->query('//title | //h1') as $heading) {
            $text = trim(html_entity_decode($heading->textContent, ENT_QUOTES | ENT_HTML5));
            $missing = $missing || preg_match('~^(?:user|profile|member) not found(?:\s*[-|:]\s*xhamster)?$~i', $text) === 1;
        }
        if ($missing) {
            return ['status' => 'Available', 'age_gate' => $ageGate, 'metadata' => []];
        }

        // The live server distinguishes a resolved profile (including a blurred
        // profile) from a generic regional wall with pageType="default". A URL,
        // echoed username, HTTP 200 or age popup by itself proves nothing.
        if (($initials['layoutPage']['pageType'] ?? null) !== 'profile') {
            return ['status' => 'Error', 'age_gate' => $ageGate, 'metadata' => []];
        }

        return [
            'status' => 'Taken',
            'age_gate' => $ageGate,
            'metadata' => [
                'username' => $target,
                'age_gate_present' => $ageGate,
                'sources' => ['xhamster_profile_page'],
                // Do not turn popup copy, regional location, generic page titles
                // or navigation hydration into a person's profile metadata.
                'metadata_strategy' => 'xhamster_server_profile_marker',
            ],
        ];
    }
}
