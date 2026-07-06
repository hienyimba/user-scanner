<?php

declare(strict_types=1);

namespace App\Services\Scanner;

use DOMDocument;
use DOMXPath;

final class ProfileMetadataExtractor
{
    /**
     * @return array<string, mixed>
     */
    public function extractProfileHtmlMetadata(string $html, string $profileUrl): array
    {
        $document = new DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($html);
        libxml_clear_errors();

        if ($loaded === false) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $metadata = [
            'display_name' => $this->metaContent($xpath, [
                '//meta[@property="og:title"]/@content',
                '//meta[@name="twitter:title"]/@content',
            ]),
            'bio' => $this->metaContent($xpath, [
                '//meta[@property="og:description"]/@content',
                '//meta[@name="description"]/@content',
                '//meta[@name="twitter:description"]/@content',
            ]),
            'avatar_url' => $this->metaContent($xpath, [
                '//meta[@property="og:image"]/@content',
                '//meta[@name="twitter:image"]/@content',
            ]),
            'public_email' => $this->firstMailto($xpath),
            'external_links' => $this->extractExternalLinks($xpath, $profileUrl),
            'sources' => [],
        ];

        $websiteUrl = $this->metaContent($xpath, [
            '//link[@rel="canonical"]/@href',
            '//meta[@property="og:url"]/@content',
        ]);
        if ($websiteUrl !== null && $websiteUrl !== '' && $websiteUrl !== $profileUrl) {
            $metadata['website_url'] = $websiteUrl;
        }

        if (
            $metadata['display_name'] !== null
            || $metadata['bio'] !== null
            || $metadata['avatar_url'] !== null
            || isset($metadata['website_url'])
        ) {
            $metadata['sources'] = $this->mergeStringList($metadata['sources'], ['opengraph']);
        }
        if ($metadata['public_email'] !== null) {
            $metadata['sources'] = $this->mergeStringList($metadata['sources'], ['mailto']);
        }
        if ($metadata['external_links'] !== []) {
            $metadata['sources'] = $this->mergeStringList($metadata['sources'], ['html_links']);
        }

        $semanticHtml = $this->extractSemanticHtmlMetadata($xpath, $profileUrl);
        if ($semanticHtml !== []) {
            $metadata['sources'] = $this->mergeStringList($metadata['sources'], ['semantic_html']);
        }

        $hydration = $this->extractHydrationMetadata($xpath);
        if ($hydration !== []) {
            $metadata['sources'] = $this->mergeStringList($metadata['sources'], ['html_hydration']);
        }

        $attributeHydration = $this->extractAttributeHydrationMetadata($xpath);
        if ($attributeHydration !== []) {
            $metadata['sources'] = $this->mergeStringList($metadata['sources'], ['html_data_attributes']);
        }

        $jsonLd = $this->extractJsonLdMetadata($xpath);
        if ($jsonLd !== []) {
            $metadata['sources'] = $this->mergeStringList($metadata['sources'], ['jsonld']);
        }

        $metadata = $this->mergeExtractedMetadata($metadata, $semanticHtml);
        $metadata = $this->mergeExtractedMetadata($metadata, $hydration);
        $metadata = $this->mergeExtractedMetadata($metadata, $attributeHydration);

        return $this->mergeExtractedMetadata($metadata, $jsonLd);
    }

    /**
     * @param array<int, string>|string $value
     * @return array<int, string>
     */
    public function mergeStringList(array|string $existing, array|string $value): array
    {
        return $this->mergeLinks($existing, $value);
    }

    /**
     * @param array<int, string>|string $value
     * @return array<int, string>
     */
    public function mergeLinks(array|string $existing, array|string $value): array
    {
        $merged = [];
        foreach ([$existing, $value] as $input) {
            if (is_string($input)) {
                $parts = preg_split('/\s*,\s*/', $input) ?: [];
                foreach ($parts as $part) {
                    $part = trim($part);
                    if ($part !== '') {
                        $merged[] = $part;
                    }
                }
                continue;
            }

            foreach ($input as $part) {
                $link = $this->extractLinkCandidate($part);
                if ($link !== null) {
                    $merged[] = $link;
                }
            }
        }

        return array_values(array_unique($merged));
    }

    /**
     * @return array<int, string>
     */
    public function normalizeExternalLinks(mixed $value): array
    {
        if (!is_array($value)) {
            return $this->mergeLinks([], is_string($value) ? $value : []);
        }

        return $this->mergeLinks([], $value);
    }

    /**
     * @return int|float|string
     */
    public function extractMetricValue(string $value): int|float|string
    {
        $normalized = strtolower(trim(str_replace([',', ' '], '', $value)));
        if (preg_match('/(-?\d+(?:\.\d+)?)([kmb])?/', $normalized, $match) === 1) {
            $number = (float) $match[1];
            $multiplier = match ($match[2] ?? '') {
                'k' => 1_000,
                'm' => 1_000_000,
                'b' => 1_000_000_000,
                default => 1,
            };

            $resolved = $number * $multiplier;

            return floor($resolved) === $resolved ? (int) $resolved : round($resolved, 2);
        }

        return $value;
    }

    public function normalizeDateValue(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return $value;
        }

        try {
            return (new \DateTimeImmutable($trimmed))
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format(DATE_ATOM);
        } catch (\Throwable) {
            return $value;
        }
    }

    /**
     * @param array<int, string> $queries
     */
    private function metaContent(DOMXPath $xpath, array $queries): ?string
    {
        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes === false || $nodes->length === 0) {
                continue;
            }

            $value = trim((string) $nodes->item(0)?->nodeValue);
            if ($value !== '') {
                return html_entity_decode($value, ENT_QUOTES | ENT_HTML5);
            }
        }

        return null;
    }

    private function firstMailto(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query('//a[starts-with(@href, "mailto:")]/@href');
        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $href = trim((string) $nodes->item(0)?->nodeValue);

        return $href !== '' ? preg_replace('/^mailto:/i', '', $href) : null;
    }

    /**
     * @return array<int, string>
     */
    private function extractExternalLinks(DOMXPath $xpath, string $profileUrl): array
    {
        $host = (string) parse_url($profileUrl, PHP_URL_HOST);
        $links = [];

        $nodes = $xpath->query('//a[@href]/@href');
        if ($nodes === false) {
            return [];
        }

        foreach ($nodes as $node) {
            $href = trim((string) $node->nodeValue);
            if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'mailto:')) {
                continue;
            }

            if (!str_starts_with($href, 'http://') && !str_starts_with($href, 'https://')) {
                continue;
            }

            $linkHost = (string) parse_url($href, PHP_URL_HOST);
            if ($linkHost === '' || ($host !== '' && $linkHost === $host)) {
                continue;
            }

            $links[] = html_entity_decode($href, ENT_QUOTES | ENT_HTML5);
            if (count($links) >= (int) config('scanner.metadata.max_external_links', 10)) {
                break;
            }
        }

        return array_values(array_unique($links));
    }

    /**
     * @return array<string, mixed>
     */
    private function extractJsonLdMetadata(DOMXPath $xpath): array
    {
        $nodes = $xpath->query('//script[@type="application/ld+json"]');
        if ($nodes === false) {
            return [];
        }

        $metadata = [];
        foreach ($nodes as $node) {
            $payload = trim((string) $node->textContent);
            if ($payload === '') {
                continue;
            }

            $decoded = json_decode($payload, true);
            if (!is_array($decoded)) {
                continue;
            }

            foreach ($this->flattenJsonLdNodes($decoded) as $item) {
                if (($metadata['display_name'] ?? null) === null && is_string($item['name'] ?? null)) {
                    $metadata['display_name'] = trim($item['name']);
                }
                if (($metadata['bio'] ?? null) === null && is_string($item['description'] ?? null)) {
                    $metadata['bio'] = trim($item['description']);
                }
                if (($metadata['avatar_url'] ?? null) === null && is_string($item['image'] ?? null)) {
                    $metadata['avatar_url'] = trim($item['image']);
                }
                if (($metadata['website_url'] ?? null) === null && is_string($item['url'] ?? null)) {
                    $metadata['website_url'] = trim($item['url']);
                }
                if (($metadata['public_email'] ?? null) === null && is_string($item['email'] ?? null)) {
                    $metadata['public_email'] = preg_replace('/^mailto:/i', '', trim($item['email']));
                }
                if (($metadata['location'] ?? null) === null && is_array($item['homeLocation'] ?? null) && is_string($item['homeLocation']['name'] ?? null)) {
                    $metadata['location'] = trim((string) $item['homeLocation']['name']);
                }
                if (($metadata['account_type'] ?? null) === null && is_string($item['@type'] ?? null)) {
                    $metadata['account_type'] = trim((string) $item['@type']);
                }
                if (is_array($item['sameAs'] ?? null)) {
                    $metadata['external_links'] = $this->mergeLinks($metadata['external_links'] ?? [], $item['sameAs']);
                }
            }
        }

        return $metadata;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractSemanticHtmlMetadata(DOMXPath $xpath, string $profileUrl): array
    {
        $metadata = [
            'display_name' => $this->metaContent($xpath, [
                '//*[@itemprop="name"]/@content',
                '//*[@itemprop="name"][1]',
                '//h1[1]',
            ]),
            'bio' => $this->metaContent($xpath, [
                '//*[@itemprop="description"]/@content',
                '//*[@itemprop="description"][1]',
            ]),
            'avatar_url' => $this->metaContent($xpath, [
                '//*[@itemprop="image"]/@content',
                '//*[@itemprop="image"]/@src',
                '//*[@itemprop="image"]/@href',
                '//img[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "avatar")][1]/@src',
            ]),
            'location' => $this->metaContent($xpath, [
                '//*[@itemprop="homeLocation"]/@content',
                '//*[@itemprop="homeLocation"][1]',
                '//*[@itemprop="location"]/@content',
                '//*[@itemprop="location"][1]',
            ]),
            'public_email' => $this->normalizeEmailValue($this->metaContent($xpath, [
                '//*[@itemprop="email"]/@content',
                '//*[@itemprop="email"]/@href',
                '//*[@itemprop="email"][1]',
            ])),
        ];

        $websiteUrl = $this->metaContent($xpath, [
            '//*[@itemprop="url"]/@href',
            '//*[@itemprop="url"]/@content',
        ]);
        if ($websiteUrl !== null && $websiteUrl !== '' && $websiteUrl !== $profileUrl) {
            $metadata['website_url'] = $websiteUrl;
        }

        $accountType = $this->metaContent($xpath, [
            '//*[@itemtype][1]/@itemtype',
        ]);
        if ($accountType !== null && $accountType !== '') {
            $metadata['account_type'] = $this->normalizeSchemaType($accountType);
        }

        $searchText = $this->collectSearchText($xpath);

        $followers = $this->extractNamedMetric($searchText, ['followers?', 'subscribers?']);
        if ($followers !== null) {
            $metadata['followers'] = $followers;
        }

        $following = $this->extractNamedMetric($searchText, ['following']);
        if ($following !== null) {
            $metadata['following'] = $following;
        }

        $posts = $this->extractNamedMetric($searchText, ['posts?', 'repositories', 'repos?', 'articles?', 'uploads?']);
        if ($posts !== null) {
            $metadata['posts_count'] = $posts;
        }

        $createdAt = $this->extractDateValue($xpath, [
            '//*[@itemprop="dateCreated"]/@datetime',
            '//*[@itemprop="dateCreated"]/@content',
            '//*[@itemprop="dateCreated"][1]',
            '//*[@itemprop="datePublished"]/@datetime',
            '//*[@itemprop="datePublished"]/@content',
            '//*[@itemprop="datePublished"][1]',
        ]);
        if ($createdAt === null) {
            $createdAt = $this->extractContextualDate($xpath, ['joined', 'member since', 'created', 'registered']);
        }
        if ($createdAt !== null) {
            $metadata['created_at'] = $createdAt;
        }

        $lastActiveAt = $this->extractDateValue($xpath, [
            '//*[@itemprop="dateModified"]/@datetime',
            '//*[@itemprop="dateModified"]/@content',
            '//*[@itemprop="dateModified"][1]',
        ]);
        if ($lastActiveAt === null) {
            $lastActiveAt = $this->extractContextualDate($xpath, ['last active', 'updated', 'last seen']);
        }
        if ($lastActiveAt !== null) {
            $metadata['last_active_at'] = $lastActiveAt;
        }

        if (
            ($metadata['followers'] ?? null) !== null
            || ($metadata['following'] ?? null) !== null
            || ($metadata['posts_count'] ?? null) !== null
        ) {
            $metadata['sources'] = $this->mergeStringList($metadata['sources'] ?? [], ['html_stats']);
        }

        if (
            ($metadata['created_at'] ?? null) !== null
            || ($metadata['last_active_at'] ?? null) !== null
        ) {
            $metadata['sources'] = $this->mergeStringList($metadata['sources'] ?? [], ['html_dates']);
        }

        return $metadata;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractHydrationMetadata(DOMXPath $xpath): array
    {
        $nodes = $xpath->query('//script');
        if ($nodes === false) {
            return [];
        }

        $metadata = [];
        $seenPayloads = [];

        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $type = strtolower(trim($node->getAttribute('type')));
            $id = strtolower(trim($node->getAttribute('id')));
            $payload = trim((string) $node->textContent);
            if ($payload === '') {
                continue;
            }

            if (!$this->isLikelyHydrationScript($type, $id, $payload)) {
                continue;
            }

            $decoded = $this->decodeHydrationPayload($payload);
            if (!is_array($decoded)) {
                continue;
            }

            $fingerprint = md5(json_encode($decoded, JSON_UNESCAPED_SLASHES));
            if (isset($seenPayloads[$fingerprint])) {
                continue;
            }
            $seenPayloads[$fingerprint] = true;

            foreach ($this->flattenHydrationNodes($decoded) as $candidate) {
                if (!$this->isLikelyProfileNode($candidate)) {
                    continue;
                }

                $metadata = $this->mergeExtractedMetadata($metadata, $this->metadataFromHydrationNode($candidate));
            }
        }

        return $metadata;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractAttributeHydrationMetadata(DOMXPath $xpath): array
    {
        $nodes = $xpath->query('//*[@*]');
        if ($nodes === false) {
            return [];
        }

        $metadata = [];
        $seenPayloads = [];

        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement || !$node->hasAttributes()) {
                continue;
            }

            foreach ($node->attributes as $attribute) {
                if (!$attribute instanceof \DOMAttr) {
                    continue;
                }

                $name = strtolower(trim($attribute->name));
                $value = trim((string) $attribute->value);
                if (!$this->isLikelyHydrationAttribute($name, $value)) {
                    continue;
                }

                $decoded = $this->decodeHydrationPayload(html_entity_decode($value, ENT_QUOTES | ENT_HTML5));
                if (!is_array($decoded)) {
                    continue;
                }

                $fingerprint = md5(json_encode($decoded, JSON_UNESCAPED_SLASHES));
                if (isset($seenPayloads[$fingerprint])) {
                    continue;
                }
                $seenPayloads[$fingerprint] = true;

                foreach ($this->flattenHydrationNodes($decoded) as $candidate) {
                    if (!$this->isLikelyProfileNode($candidate)) {
                        continue;
                    }

                    $metadata = $this->mergeExtractedMetadata($metadata, $this->metadataFromHydrationNode($candidate));
                }
            }
        }

        return $metadata;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function flattenJsonLdNodes(array $decoded): array
    {
        if (array_is_list($decoded)) {
            return array_values(array_filter($decoded, 'is_array'));
        }

        if (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
            return array_values(array_filter($decoded['@graph'], 'is_array'));
        }

        return [$decoded];
    }

    private function normalizeEmailValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/^mailto:/i', '', trim($value));

        return $normalized !== null && $normalized !== '' ? $normalized : null;
    }

    private function normalizeSchemaType(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return $value;
        }

        $segments = preg_split('/[\/#]/', $trimmed) ?: [];
        $last = trim((string) end($segments));

        return $last !== '' ? $last : $trimmed;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeHydrationPayload(string $payload): ?array
    {
        $trimmed = trim($payload);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $patterns = [
            '/^\s*(?:window\.)?__NUXT__\s*=\s*(.+?)\s*;?\s*$/s',
            '/^\s*(?:window\.)?__sc_hydration\s*=\s*(.+?)\s*;?\s*$/s',
            '/^\s*(?:var|let|const)\s+[A-Za-z0-9_$]+(?:\.[A-Za-z0-9_$]+)*\s*=\s*(.+?)\s*;?\s*$/s',
            '/^\s*(?:window\.)?[A-Za-z0-9_$]+(?:\.[A-Za-z0-9_$]+)*\s*=\s*(.+?)\s*;?\s*$/s',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $trimmed, $matches) !== 1) {
                continue;
            }

            $decoded = json_decode(trim($matches[1]), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed>|array<int, mixed> $decoded
     * @return array<int, array<string, mixed>>
     */
    private function flattenHydrationNodes(array $decoded): array
    {
        $stack = [$decoded];
        $nodes = [];
        $visited = 0;

        while ($stack !== [] && $visited < 2000) {
            $current = array_pop($stack);
            $visited++;

            if (!is_array($current)) {
                continue;
            }

            if (!array_is_list($current)) {
                $nodes[] = $current;
            }

            foreach ($current as $value) {
                if (is_array($value)) {
                    $stack[] = $value;
                }
            }
        }

        return $nodes;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function isLikelyProfileNode(array $node): bool
    {
        $score = 0;

        foreach ([
            'displayName', 'display_username', 'full_name', 'fullName', 'name', 'title', 'username',
            'user_id', 'userId', 'uid', 'id', 'externalId',
            'description', 'about', 'bio', 'summary',
            'avatar_url', 'avatarUrl', 'image', 'image_url', 'imageUrl', 'photo', 'picture',
            'location', 'city', 'website', 'website_url', 'websiteUrl', 'vanityChannelUrl', 'channel_url', 'url', 'portfolio',
            'followers', 'follower_count', 'followers_count', 'followersCount',
            'following', 'following_count', 'followings_count', 'followingCount',
            'post_count', 'posts_count', 'pin_count', 'board_count', 'track_count', 'repo_count',
            'playlist_count', 'playlistCount', 'friends_count', 'friend_count', 'tips_count', 'tip_count',
            'stations_count', 'station_count', 'countries_count', 'country_count',
            'verified', 'isVerified', 'is_paid', 'is_premium', 'isPremium', 'is_private', 'isPrivate',
            'private', 'protected', 'locked', 'show_pro_badge', 'verified_type', 'jid', 'is_community', 'email', 'gender',
        ] as $key) {
            if (!array_key_exists($key, $node) || $node[$key] === null || $node[$key] === '' || $node[$key] === []) {
                continue;
            }

            $score++;
        }

        $typeHints = [
            strtolower((string) ($node['__typename'] ?? '')),
            strtolower((string) ($node['type'] ?? '')),
            strtolower((string) ($node['entityType'] ?? '')),
        ];

        foreach ($typeHints as $hint) {
            if ($hint !== '' && preg_match('/user|person|profile|member|channel|creator|account/', $hint) === 1) {
                $score++;
                break;
            }
        }

        return $score >= 2;
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private function metadataFromHydrationNode(array $node): array
    {
        $metadata = [];

        $displayName = $this->firstScalar($node, ['displayName', 'display_username', 'full_name', 'fullName', 'name', 'title', 'real_name']);
        if ($displayName !== null) {
            $metadata['display_name'] = $displayName;
        }

        $username = $this->firstScalar($node, ['username', 'login', 'handle', 'slug', 'screen_name', 'preferredUsername']);
        if ($username !== null) {
            $metadata['username'] = ltrim($username, '@');
        }

        $userId = $this->firstIdentifier($node, ['user_id', 'userId', 'uid', 'id', 'externalId', 'member_id', 'profile_id', 'channelId']);
        if ($userId !== null) {
            $metadata['user_id'] = $userId;
        }

        $bio = $this->firstScalar($node, ['description', 'about', 'bio', 'summary', 'aboutMe', 'tagline']);
        if ($bio !== null) {
            $metadata['bio'] = $bio;
        }

        $avatarUrl = $this->firstScalar($node, [
            'avatar_url', 'avatarUrl', 'image', 'image_url', 'imageUrl',
            'photo', 'photoURL', 'picture', 'pictureUrl', 'profile_picture',
            'image_xlarge_url', 'snapcodeImageUrl',
        ]);
        if ($avatarUrl === null) {
            $avatarUrl = $this->resolveHydrationAvatarUrl($node);
        }
        if ($avatarUrl !== null) {
            $metadata['avatar_url'] = $avatarUrl;
        }

        $websiteUrl = $this->firstScalar($node, ['website_url', 'websiteUrl', 'website', 'vanityChannelUrl', 'channel_url', 'url', 'portfolio', 'site']);
        if ($websiteUrl !== null) {
            $metadata['website_url'] = $websiteUrl;
        }

        $publicEmail = $this->normalizeEmailValue($this->firstScalar($node, ['email', 'public_email']));
        if ($publicEmail !== null) {
            $metadata['public_email'] = $publicEmail;
        }

        $location = $this->resolveHydrationLocation($node);
        if ($location !== null) {
            $metadata['location'] = $location;
        }

        $gender = $this->firstScalar($node, ['gender']);
        if ($gender !== null) {
            $metadata['gender'] = $gender;
        }

        $verifiedType = $this->firstScalar($node, ['verified_type', 'verifiedType']);
        if ($verifiedType !== null) {
            $metadata['verified_type'] = $verifiedType;
        }

        $jid = $this->firstScalar($node, ['jid', 'profile_jid', 'user_jid']);
        if ($jid !== null) {
            $metadata['jid'] = $jid;
        }

        $followers = $this->firstMetric($node, ['followers', 'follower_count', 'followers_count', 'followersCount']);
        if ($followers !== null) {
            $metadata['followers'] = $followers;
        }

        $following = $this->firstMetric($node, ['following', 'following_count', 'followingCount', 'followings_count', 'followingsCount']);
        if ($following !== null) {
            $metadata['following'] = $following;
        }

        $postsCount = $this->firstMetric($node, [
            'posts_count', 'post_count', 'postsCount', 'postCount',
            'pin_count', 'board_count', 'track_count', 'repo_count',
            'repositories_count', 'article_count', 'articles_count',
            'uploads_count', 'playlist_count',
        ]);
        if ($postsCount !== null) {
            $metadata['posts_count'] = $postsCount;
        }

        $playlistCount = $this->firstMetric($node, ['playlist_count', 'playlistCount']);
        if ($playlistCount !== null) {
            $metadata['playlist_count'] = $playlistCount;
        }

        $friends = $this->firstMetric($node, ['friends_count', 'friend_count', 'friendsCount', 'friendCount', 'friends']);
        if ($friends !== null) {
            $metadata['friends'] = $friends;
        }

        $tips = $this->firstMetric($node, ['tips_count', 'tip_count', 'tipsCount', 'tipCount']);
        if ($tips !== null) {
            $metadata['tips'] = $tips;
        }

        $stations = $this->firstMetricOrList($node, ['stations', 'station_count', 'stations_count', 'stationCount', 'stationsCount']);
        if ($stations !== null) {
            $metadata['stations'] = $stations;
        }

        $countries = $this->firstMetricOrList($node, ['countries', 'country_count', 'countries_count', 'countryCount', 'countriesCount']);
        if ($countries !== null) {
            $metadata['countries'] = $countries;
        }

        $accountType = $this->firstScalar($node, ['__typename', 'type', 'entityType']);
        if ($accountType !== null) {
            $metadata['account_type'] = $this->normalizeSchemaType($accountType);
        }

        $verified = $this->firstBoolean($node, ['verified', 'is_verified', 'isVerified']);
        if ($verified !== null) {
            $metadata['is_verified'] = $verified;
        }

        $isPrivate = $this->firstPrivacyFlag($node, ['is_private', 'isPrivate', 'private', 'protected', 'locked']);
        if ($isPrivate !== null) {
            $metadata['is_private'] = $isPrivate;
        }

        $isPremium = $this->firstBoolean($node, ['is_premium', 'isPremium', 'is_paid', 'isPaid', 'premium']);
        if ($isPremium !== null) {
            $metadata['is_premium'] = $isPremium;
        }

        $showProBadge = $this->firstBoolean($node, ['show_pro_badge', 'showProBadge']);
        if ($showProBadge !== null) {
            $metadata['show_pro_badge'] = $showProBadge;
        }

        if (($metadata['account_type'] ?? null) === null && is_bool($node['is_community'] ?? null)) {
            $metadata['account_type'] = $node['is_community'] ? 'community' : 'user';
        }

        $createdAt = $this->firstDate($node, ['created_at', 'createdAt', 'joined_at', 'joinedAt', 'join_date', 'joinDate', 'registrationDate']);
        if ($createdAt !== null) {
            $metadata['created_at'] = $createdAt;
        }

        $lastActiveAt = $this->firstDate($node, ['last_active_at', 'lastActiveAt', 'updated_at', 'updatedAt', 'last_seen_at', 'lastSeenAt']);
        if ($lastActiveAt !== null) {
            $metadata['last_active_at'] = $lastActiveAt;
        }

        $externalLinks = [];
        foreach (['sameAs', 'external_links', 'social_links', 'socialLinks', 'links'] as $key) {
            if (is_array($node[$key] ?? null)) {
                $externalLinks = $this->mergeLinks($externalLinks, $node[$key]);
            }
        }
        foreach (['vanityChannelUrl', 'channel_url'] as $key) {
            if (is_scalar($node[$key] ?? null) && trim((string) $node[$key]) !== '') {
                $externalLinks = $this->mergeLinks($externalLinks, [trim((string) $node[$key])]);
            }
        }
        if ($externalLinks !== []) {
            $metadata['external_links'] = $externalLinks;
        }

        return $metadata;
    }

    private function isLikelyHydrationScript(string $type, string $id, string $payload): bool
    {
        if ($type === 'application/json') {
            return true;
        }

        foreach (['next', 'nuxt', 'state', 'props'] as $needle) {
            if (str_contains($id, $needle)) {
                return true;
            }
        }

        foreach (['__NEXT_DATA__', '__NUXT__', '__sc_hydration', 'INITIAL_STATE', 'apollo', 'ytInitialData', 'Site.journal'] as $needle) {
            if (str_contains($payload, $needle)) {
                return true;
            }
        }

        return preg_match('/^\s*(?:var|let|const)\s+[A-Za-z0-9_$]+(?:\.[A-Za-z0-9_$]+)*\s*=\s*[\[{]/s', $payload) === 1
            || preg_match('/^\s*(?:window\.)?[A-Za-z0-9_$]+(?:\.[A-Za-z0-9_$]+)*\s*=\s*[\[{]/s', $payload) === 1;
    }

    private function isLikelyHydrationAttribute(string $name, string $value): bool
    {
        if ($value === '' || !str_starts_with($name, 'data-')) {
            return false;
        }

        if (!str_contains($value, '{') && !str_contains($value, '[') && !str_contains($value, '&quot;')) {
            return false;
        }

        foreach (['blob', 'props', 'state', 'store', 'json', 'hydrat', 'boot', 'initial', 'profile'] as $needle) {
            if (str_contains($name, $needle)) {
                return true;
            }
        }

        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5);

        return str_contains($decoded, 'displayName')
            || str_contains($decoded, 'followers')
            || str_contains($decoded, 'website')
            || str_contains($decoded, 'avatar');
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, string> $keys
     */
    private function firstScalar(array $node, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $node[$key] ?? null;
            if (!is_scalar($value)) {
                continue;
            }

            $string = trim((string) $value);
            if ($string !== '') {
                return $string;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, string> $keys
     * @return int|float|string|null
     */
    private function firstMetric(array $node, array $keys): int|float|string|null
    {
        foreach ($keys as $key) {
            $value = $node[$key] ?? null;
            if (is_array($value)) {
                $normalizedList = $this->normalizeScalarList($value);
                if ($normalizedList !== []) {
                    return count($normalizedList);
                }

                continue;
            }

            if (!is_scalar($value)) {
                continue;
            }

            $string = trim((string) $value);
            if ($string !== '') {
                return $this->extractMetricValue($string);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, string> $keys
     * @return int|float|string|array<int, string>|null
     */
    private function firstMetricOrList(array $node, array $keys): int|float|string|array|null
    {
        foreach ($keys as $key) {
            $value = $node[$key] ?? null;
            if (is_array($value)) {
                $normalizedList = $this->normalizeScalarList($value);
                if ($normalizedList !== []) {
                    return $normalizedList;
                }

                continue;
            }

            if (!is_scalar($value)) {
                continue;
            }

            $string = trim((string) $value);
            if ($string !== '') {
                return $this->extractMetricValue($string);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, string> $keys
     */
    private function firstBoolean(array $node, array $keys): ?bool
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $node)) {
                continue;
            }

            $value = $node[$key];
            if (is_bool($value)) {
                return $value;
            }

            if (is_scalar($value)) {
                $lower = strtolower(trim((string) $value));
                if (in_array($lower, ['true', '1', 'yes'], true)) {
                    return true;
                }
                if (in_array($lower, ['false', '0', 'no'], true)) {
                    return false;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, string> $keys
     * @return int|string|null
     */
    private function firstIdentifier(array $node, array $keys): int|string|null
    {
        $value = $this->firstScalar($node, $keys);
        if ($value === null) {
            return null;
        }

        return is_numeric($value) ? (int) $value : $value;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, string> $keys
     */
    private function firstPrivacyFlag(array $node, array $keys): ?bool
    {
        $boolean = $this->firstBoolean($node, $keys);
        if ($boolean !== null) {
            return $boolean;
        }

        foreach ($keys as $key) {
            if (!array_key_exists($key, $node) || !is_scalar($node[$key])) {
                continue;
            }

            $value = strtolower(trim((string) $node[$key]));
            if (in_array($value, ['private', 'locked', 'protected'], true)) {
                return true;
            }
            if (in_array($value, ['public', 'unlocked', 'open'], true)) {
                return false;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, string> $keys
     */
    private function firstDate(array $node, array $keys): ?string
    {
        $value = $this->firstScalar($node, $keys);
        if ($value === null) {
            return null;
        }

        return $this->normalizeDateValue($value);
    }

    /**
     * @param array<string, mixed> $node
     */
    private function resolveHydrationLocation(array $node): ?string
    {
        $direct = $this->firstScalar($node, ['location']);
        if ($direct !== null) {
            return $direct;
        }

        $parts = array_values(array_filter([
            $this->firstScalar($node, ['city']),
            $this->firstScalar($node, ['country', 'country_code']),
        ]));

        if ($parts === []) {
            return null;
        }

        return implode(', ', $parts);
    }

    private function collectSearchText(DOMXPath $xpath): string
    {
        $chunks = [];
        $body = $xpath->query('//body');
        if ($body !== false && $body->length > 0) {
            $chunks[] = trim((string) $body->item(0)?->textContent);
        }

        foreach (['//@aria-label', '//@title'] as $query) {
            $nodes = $xpath->query($query);
            if ($nodes === false) {
                continue;
            }

            foreach ($nodes as $node) {
                $value = trim((string) $node->nodeValue);
                if ($value !== '') {
                    $chunks[] = $value;
                }
            }
        }

        return implode("\n", $chunks);
    }

    /**
     * @param array<int, string> $labels
     * @return int|float|string|null
     */
    private function extractNamedMetric(string $searchText, array $labels): int|float|string|null
    {
        $metricPattern = '([0-9][0-9,\.]*(?:\s?[kmb])?)';

        foreach ($labels as $label) {
            if (preg_match('/' . $metricPattern . '[ \t]+' . $label . '\b/i', $searchText, $match) === 1) {
                return $this->extractMetricValue($match[1]);
            }
            if (preg_match('/\b' . $label . '\s*[:\-]?\s*' . $metricPattern . '\b/i', $searchText, $match) === 1) {
                return $this->extractMetricValue($match[1]);
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $queries
     */
    private function extractDateValue(DOMXPath $xpath, array $queries): ?string
    {
        $value = $this->metaContent($xpath, $queries);
        if ($value === null || trim($value) === '') {
            return null;
        }

        return $this->normalizeDateValue($value);
    }

    /**
     * @param array<int, string> $keywords
     */
    private function extractContextualDate(DOMXPath $xpath, array $keywords): ?string
    {
        $nodes = $xpath->query('//time');
        if ($nodes === false) {
            return null;
        }

        foreach ($nodes as $node) {
            $context = strtolower(trim((string) ($node->parentNode?->textContent ?? $node->textContent)));
            foreach ($keywords as $keyword) {
                if (!str_contains($context, $keyword)) {
                    continue;
                }

                $datetime = '';
                if ($node instanceof \DOMElement) {
                    $datetime = trim($node->getAttribute('datetime'));
                }

                $value = $datetime !== '' ? $datetime : trim((string) $node->textContent);
                if ($value !== '') {
                    return $this->normalizeDateValue($value);
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $newMetadata
     * @return array<string, mixed>
     */
    private function mergeExtractedMetadata(array $metadata, array $newMetadata): array
    {
        foreach ($newMetadata as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            if ($key === 'external_links') {
                $metadata[$key] = $this->mergeLinks($metadata[$key] ?? [], $value);
                continue;
            }

            if ($key === 'sources') {
                $metadata[$key] = $this->mergeStringList($metadata[$key] ?? [], $value);
                continue;
            }

            if (($metadata[$key] ?? null) === null || ($metadata[$key] ?? '') === '') {
                $metadata[$key] = $value;
            }
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function resolveHydrationAvatarUrl(array $node): ?string
    {
        foreach (['avatar', 'photo', 'picture', 'image'] as $key) {
            $value = $node[$key] ?? null;
            if (!is_array($value)) {
                continue;
            }

            $direct = $this->extractLinkCandidate($value);
            if ($direct !== null) {
                return $direct;
            }

            foreach (['thumbnails', 'urls', 'images'] as $nestedKey) {
                if (!is_array($value[$nestedKey] ?? null)) {
                    continue;
                }

                foreach ($value[$nestedKey] as $item) {
                    $candidate = $this->extractLinkCandidate($item);
                    if ($candidate !== null) {
                        return $candidate;
                    }
                }
            }
        }

        return null;
    }

    private function extractLinkCandidate(mixed $value): ?string
    {
        if (is_scalar($value)) {
            $link = trim((string) $value);

            return $link !== '' ? $link : null;
        }

        if (!is_array($value)) {
            return null;
        }

        foreach (['url', 'href', 'link', 'website', 'website_url', 'value'] as $key) {
            $candidate = $value[$key] ?? null;
            if (!is_scalar($candidate)) {
                continue;
            }

            $link = trim((string) $candidate);
            if ($link !== '') {
                return $link;
            }
        }

        return null;
    }

    /**
     * @param array<int|string, mixed> $values
     * @return array<int, string>
     */
    private function normalizeScalarList(array $values): array
    {
        $normalized = [];
        foreach ($values as $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $string = trim((string) $value);
            if ($string !== '') {
                $normalized[] = $string;
            }
        }

        return array_values(array_unique($normalized));
    }
}
