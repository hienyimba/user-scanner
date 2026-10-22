<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\September;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use App\Services\Scanner\XhamsterProfileEvidence;
use DOMDocument;
use DOMXPath;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Executes the repeated username-probe families added in user-scanner v1.5.2.
 *
 * The September release contains more than a thousand instances of the same
 * Discourse, Gitea, and Lemmy contracts. Keeping their endpoints in data and
 * their response semantics here makes every instance independently
 * addressable without duplicating an entire PHP class per hostname.
 */
final class DeclarativeUsernameValidator extends BaseGeneratedValidator
{
    /** @param array<string, mixed> $definition */
    public function __construct(private readonly array $definition) {}

    public function key(): string
    {
        return (string) $this->definition['key'];
    }

    public function supportsAsyncPilot(): bool
    {
        return in_array($this->definition['family'] ?? '', ['discourse', 'gitea', 'lemmy'], true)
            && $this->requestMethod() === 'GET';
    }

    public function category(): string
    {
        return (string) $this->definition['category'];
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return (string) $this->definition['site_name'];
    }

    public function siteUrl(): string
    {
        return (string) $this->definition['site_url'];
    }

    protected function requestMethod(): string
    {
        return strtoupper((string) data_get($this->definition, 'request.method', 'GET'));
    }

    protected function requestUrl(string $target): string
    {
        return $this->interpolate((string) data_get($this->definition, 'request.url', ''), $target);
    }

    protected function requestHeadersForTarget(string $target): array
    {
        $headers = [];
        foreach ((array) data_get($this->definition, 'request.headers', []) as $name => $value) {
            if (is_scalar($value)) {
                $headers[(string) $name] = $this->interpolate((string) $value, $target);
            }
        }

        return $headers;
    }

    protected function requestQuery(string $target): array
    {
        return $this->interpolateArray((array) data_get($this->definition, 'request.query', []), $target);
    }

    protected function requestBody(string $target): array
    {
        return $this->interpolateArray((array) data_get($this->definition, 'request.body', []), $target);
    }

    protected function requestBodyMode(): string
    {
        return (string) data_get($this->definition, 'request.body_mode', 'form');
    }

    protected function timeoutSeconds(): int
    {
        return max(1, (int) data_get($this->definition, 'request.timeout', 10));
    }

    protected function followRedirects(): bool
    {
        return (bool) data_get($this->definition, 'request.follow_redirects', true);
    }

    protected function makeRequest(string $target, array $options = []): Response
    {
        if ($this->key() !== 'chaturbate') {
            return parent::makeRequest($target, $options);
        }

        // Validate only the username field. Never submit a complete registration.
        $client = Http::timeout($this->timeoutSeconds())->withOptions([
            'allow_redirects' => true,
            'verify' => (bool) config('scanner.verify_ssl', false),
            'cookies' => new CookieJar,
        ])->withHeaders(['User-Agent' => (string) config('scanner.user_agent')]);
        if (! empty($options['proxy'])) {
            $client->withOptions(['proxy' => $options['proxy']]);
        }
        $page = $client->get('https://chaturbate.com/accounts/register/');
        if ($page->status() !== 200) {
            return $page;
        }
        $xpath = $this->htmlXPath($page->body());
        $csrf = $xpath->evaluate('string(//input[@name="csrfmiddlewaretoken"]/@value)');
        if (! is_string($csrf) || $csrf === '') {
            throw new RuntimeException('Chaturbate registration page missing CSRF token; no username verdict');
        }

        return $client->asForm()->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Origin' => 'https://chaturbate.com',
            'Referer' => 'https://chaturbate.com/accounts/register/',
        ])->post('https://chaturbate.com/accounts/ajax_validate_register_form/', [
            'csrfmiddlewaretoken' => $csrf,
            'username' => $target,
        ]);
    }

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        if ($this->key() === 'xhamster') {
            return $this->parseXhamster($response, $target);
        }
        $json = $this->decodeJson($response);

        return match ((string) ($this->definition['family'] ?? '')) {
            'discourse' => $this->parseDiscourse($response, $json),
            'gitea' => $this->parseGitea($response, $json, $target),
            'lemmy' => $this->parseLemmy($response, $json, $target),
            'generic_profile' => $this->parseGenericProfile($response, $target),
            'custom_profile' => $this->parseCustomProfile($response, $target),
            'annaabi' => $this->parseAnnaabi($response),
            'bugcrowd' => $this->parseBugcrowd($response, $json, $target),
            'characterai' => $this->parseCharacterAi($response, $json, $target),
            'devhunt' => $this->parseDevHunt($response, $json, $target),
            'matrix' => $this->parseMatrix($response, $json),
            'medal' => $this->parseMedal($response, $json),
            'neanky' => $this->parseNeanky($response, $json, $target),
            'xenforo_search' => $this->parseXenForoSearch($response),
            'api_lookup' => $this->parseApiLookup($response, $json, $target),
            'html_lookup' => $this->parseHtmlLookup($response, $target),
            default => ['Error', 'Unsupported September username response family'],
        };
    }

    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        if ($status !== 'Taken') {
            return [];
        }

        if ($this->key() === 'xhamster') {
            return app(XhamsterProfileEvidence::class)->inspect($response->body(), $target)['metadata'];
        }

        $json = $this->decodeJson($response);
        if ($json === null) {
            return [];
        }

        return match ((string) ($this->definition['family'] ?? '')) {
            'discourse' => $this->discourseMetadata($json),
            'gitea' => $this->giteaMetadata($json),
            'lemmy' => $this->lemmyMetadata($json),
            'generic_profile' => $this->genericProfileMetadata($response, $target),
            'custom_profile' => $this->customProfileMetadata($response, $target),
            'bugcrowd' => $this->metadata($json, [
                'username' => 'username',
                'country' => 'countryCode',
                'twitter' => 'twitterUsername',
                'linkedin' => 'linkedinUrl',
                'website_url' => 'website',
                'bio' => 'biography',
                'verified' => 'identityVerified',
                'avatar_url' => 'avatarUrl',
                'banner_url' => 'bannerImageUrl',
            ]),
            'characterai' => $this->characterAiMetadata($json),
            'devhunt' => $this->devHuntMetadata($json),
            'matrix' => $this->matrixMetadata($json),
            default => [],
        };
    }

    protected function detectBlockedOrChallenged(Response $response): ?array
    {
        $blocked = parent::detectBlockedOrChallenged($response);
        if ($this->key() !== 'xhamster' || ! in_array($response->status(), [200, 404], true)) {
            return $blocked;
        }

        $evidence = app(XhamsterProfileEvidence::class)->inspect($response->body(), '');
        if ($blocked !== null && ! str_contains($blocked[1], 'regional age-verification wall')) {
            return $blocked;
        }
        if ($evidence['status'] !== 'Error') {
            return null;
        }
        if ($evidence['age_gate']) {
            // The existing policy retries an IP block on one alternate proxy.
            return ['Error', 'xhamster: regional IP block (age-verification wall); account existence unknown'];
        }

        return $blocked;
    }

    /** @return array{0:string,1:string} */
    private function parseXhamster(Response $response, string $target): array
    {
        if (! in_array($response->status(), [200, 404], true)) {
            return ['Error', 'Unexpected Xhamster status: '.$response->status()];
        }
        $inspector = app(XhamsterProfileEvidence::class);
        $effectiveUrl = (string) ($response->effectiveUri() ?? '');
        if ($effectiveUrl !== '' && strcasecmp($inspector->targetFromUrl($effectiveUrl) ?? '', $target) !== 0) {
            return ['Error', 'Xhamster redirected away from the requested user profile'];
        }
        $evidence = $inspector->inspect($response->body(), $target);
        if ($evidence['status'] === 'Available') {
            return ['Available', 'Xhamster explicitly reports user not found'];
        }
        if ($response->status() === 200 && $evidence['status'] === 'Taken') {
            return ['Taken', $evidence['age_gate'] ? 'Profile confirmed by server page type; age gate hides profile details' : ''];
        }

        return ['Error', 'Xhamster response lacked verified profile or user-not-found evidence'];
    }

    /** @return array{0:string,1:string} */
    private function parseAnnaabi(Response $response): array
    {
        if ($response->status() !== 200) {
            return ['Error', 'Unexpected response status: '.$response->status()];
        }

        $taken = str_contains($response->body(), 'value="2" id="psname"')
            && str_contains($response->body(), 'Selline kasutajanimi on juba võetud');
        $available = str_contains($response->body(), 'value="1" id="psname"')
            && str_contains($response->body(), 'See kasutajanimi on vaba');

        return match (true) {
            $taken && ! $available => ['Taken', ''],
            $available && ! $taken => ['Available', ''],
            default => ['Error', 'Unexpected response body'],
        };
    }

    /** @param array<string, mixed>|null $json @return array{0:string,1:string} */
    private function parseBugcrowd(Response $response, ?array $json, string $target): array
    {
        if ($response->status() === 404 && ($json['error'] ?? null) === 'Not Found') {
            return ['Available', ''];
        }
        if ($response->status() === 404 && ($json === null || $json === [])) {
            return ['Taken', ''];
        }
        if ($response->status() !== 200) {
            return ['Error', 'Unexpected response status: '.$response->status()];
        }

        return is_string($json['username'] ?? null) && strcasecmp((string) $json['username'], $target) === 0
            ? ['Taken', '']
            : ['Error', 'Profile response did not match the requested username'];
    }

    /** @param array<string, mixed>|null $json @return array{0:string,1:string} */
    private function parseCharacterAi(Response $response, ?array $json, string $target): array
    {
        $error = data_get($json, 'error.json.data');
        if ($response->status() === 500
            && is_array($error)
            && ($error['path'] ?? null) === 'social.publicProfile'
            && data_get($error, 'axiosErrorData.upstreamStatus') === 404) {
            return ['Available', ''];
        }
        if ($response->status() !== 200) {
            return ['Error', 'Unexpected response status: '.$response->status()];
        }
        $profile = data_get($json, 'result.data.json');

        return is_array($profile) && ($profile['username'] ?? null) === $target
            ? ['Taken', '']
            : ['Error', 'Character.AI profile data was missing'];
    }

    /** @param array<string, mixed>|null $json @return array{0:string,1:string} */
    private function parseDevHunt(Response $response, ?array $json, string $target): array
    {
        if ($response->status() !== 200) {
            return ['Error', 'Unexpected status: '.$response->status()];
        }
        if ($json === []) {
            return ['Available', ''];
        }
        $profile = is_array($json[0] ?? null) ? $json[0] : null;

        return is_array($profile) && ($profile['username'] ?? null) === $target
            ? ['Taken', '']
            : ['Error', 'Profile did not match the requested username'];
    }

    /** @param array<string, mixed>|null $json @return array{0:string,1:string} */
    private function parseMatrix(Response $response, ?array $json): array
    {
        if ($response->status() === 404) {
            $error = (string) ($json['error'] ?? '');

            return in_array($json['errcode'] ?? null, ['M_UNKNOWN', 'M_NOT_FOUND'], true) || str_contains($error, 'No row found')
                ? ['Available', '']
                : ['Error', 'Matrix 404 response missing expected error payload'];
        }
        if ($response->status() === 400) {
            return ($json['errcode'] ?? null) === 'M_INVALID_PARAM' || str_contains((string) ($json['error'] ?? ''), 'Invalid user ID')
                ? ['Available', '']
                : ['Error', 'Matrix 400 response missing expected error payload'];
        }
        if ($response->status() === 200 && is_array($json) && ! array_key_exists('errcode', $json)) {
            return ['Taken', ''];
        }

        return ['Error', 'Unexpected Matrix response format'];
    }

    /** @param array<string, mixed>|null $json @return array{0:string,1:string} */
    private function parseMedal(Response $response, ?array $json): array
    {
        if ($response->status() !== 200) {
            return ['Error', 'Unexpected response status: '.$response->status()];
        }
        if (($json['valid'] ?? null) !== true) {
            return ['Error', 'Medal rejected the username'];
        }

        return match ($json['exists'] ?? null) {
            false => ['Available', ''],
            true => ['Taken', ''],
            default => ['Error', 'Unexpected Medal username response'],
        };
    }

    /** @param array<string, mixed>|null $json @return array{0:string,1:string} */
    private function parseNeanky(Response $response, ?array $json, string $target): array
    {
        if ($response->status() !== 200) {
            return ['Error', 'Unexpected response status: '.$response->status()];
        }
        if (! is_array($json) || strcasecmp((string) data_get($json, 'user.username', ''), $target) !== 0) {
            return ['Error', 'Availability response does not match the requested handle'];
        }

        return match ($json['state'] ?? null) {
            1 => ['Available', ''],
            0 => ['Taken', ''],
            default => ['Error', 'Could not verify username availability'],
        };
    }

    /** @return array{0:string,1:string} */
    private function parseXenForoSearch(Response $response): array
    {
        if (in_array($response->status(), [301, 302, 303], true)) {
            $location = (string) $response->header('Location');

            return preg_match('~/members/[^/]+\.\d+/?(?:\?.*)?$~i', $location) === 1
                ? ['Taken', '']
                : ['Error', 'Unexpected redirect target: '.$location];
        }
        if ($response->status() === 200 && str_contains($response->body(), 'The specified member cannot be found')) {
            return ['Available', ''];
        }
        if ($response->status() === 202) {
            return ['Error', 'Proof-of-work challenge requires a replay'];
        }

        return ['Error', 'Unexpected status code: '.$response->status()];
    }

    /** @param array<string, mixed>|null $json @return array{0:string,1:string} */
    private function parseApiLookup(Response $response, ?array $json, string $target): array
    {
        return match ($this->key()) {
            'lesswrong' => $this->parseLessWrong($response, $json),
            'civitai' => $this->parseCollectionCount($response, $json, 'items'),
            'dblp' => $this->parseDblp($response, $json),
            'kitsu' => $this->parseKitsu($response, $json, $target),
            'openalex' => $this->parseOpenAlex($response, $json),
            'orcid' => $this->parseOrcid($response, $json),
            'fandom' => $this->parseFandom($response, $json),
            'polarsteps' => $this->parsePolarsteps($response, $json, $target),
            'buicha' => $this->parseBuicha($response, $json, $target),
            'memory_lol' => $this->parseCollectionCount($response, $json, 'accounts'),
            default => ['Error', 'Unsupported September API lookup contract'],
        };
    }

    /** @param array<string, mixed>|null $json @return array{0:string,1:string} */
    private function parseLessWrong(Response $response, ?array $json): array
    {
        if ($response->status() === 404) {
            return ['Available', ''];
        }
        if ($response->status() !== 200 || ! is_array($json)) {
            return ['Error', 'Unexpected LessWrong response status: '.$response->status()];
        }
        $user = data_get($json, 'data.user');
        if ($user === null) {
            return ['Available', ''];
        }

        return is_array(data_get($json, 'data.user.result'))
            ? ['Taken', '']
            : ['Error', 'LessWrong GraphQL response missing user result'];
    }

    /** @param array<string, mixed>|null $json @return array{0:string,1:string} */
    private function parseCollectionCount(Response $response, ?array $json, string $field): array
    {
        if ($response->status() === 404) {
            return ['Available', ''];
        }
        if ($response->status() !== 200 || ! is_array($json) || ! is_array($json[$field] ?? null)) {
            return ['Error', 'Unexpected collection API response'];
        }

        return $json[$field] === [] ? ['Available', ''] : ['Taken', ''];
    }

    /** @param array<string, mixed>|null $json @return array{0:string,1:string} */
    private function parseDblp(Response $response, ?array $json): array
    {
        if ($response->status() === 404) {
            return ['Available', ''];
        }
        if ($response->status() !== 200 || ! is_numeric(data_get($json, 'result.hits.@total'))) {
            return ['Error', 'Unexpected DBLP response'];
        }

        return (int) data_get($json, 'result.hits.@total') === 0 ? ['Available', ''] : ['Taken', ''];
    }

    /** @param array<string, mixed>|null $json @return array{0:string,1:string} */
    private function parseKitsu(Response $response, ?array $json, string $target): array
    {
        if ($response->status() === 404) {
            return ['Available', ''];
        }
        $items = $json['data'] ?? null;
        if ($response->status() !== 200 || ! is_array($items)) {
            return ['Error', 'Kitsu 200 response missing valid user payload'];
        }
        foreach ($items as $item) {
            if (is_array($item) && strcasecmp((string) data_get($item, 'attributes.name', ''), $target) === 0) {
                return ['Taken', ''];
            }
        }

        return ['Available', ''];
    }

    /** @param array<string, mixed>|null $json @return array{0:string,1:string} */
    private function parseOpenAlex(Response $response, ?array $json): array
    {
        if ($response->status() === 404) {
            return ['Available', ''];
        }
        $count = data_get($json, 'meta.count');
        if ($response->status() !== 200 || ! is_numeric($count)) {
            return ['Error', 'Unexpected OpenAlex response'];
        }

        return (int) $count === 0 ? ['Available', ''] : ['Taken', ''];
    }

    /** @param array<string, mixed>|null $json @return array{0:string,1:string} */
    private function parseOrcid(Response $response, ?array $json): array
    {
        if ($response->status() === 404) {
            return ['Available', ''];
        }
        $count = $json['num-found'] ?? null;
        if ($response->status() !== 200 || ! is_numeric($count)) {
            return ['Error', 'Unexpected ORCID response'];
        }

        return (int) $count === 0 ? ['Available', ''] : ['Taken', ''];
    }

    /** @param array<string, mixed>|null $json @return array{0:string,1:string} */
    private function parseFandom(Response $response, ?array $json): array
    {
        if ($response->status() === 404) {
            return ['Available', ''];
        }
        $users = data_get($json, 'query.users');
        if ($response->status() !== 200 || ! is_array($users)) {
            return ['Error', 'Unexpected Fandom API response'];
        }
        $user = is_array($users[0] ?? null) ? $users[0] : null;

        return $user === null || array_key_exists('missing', $user)
            ? ['Available', '']
            : (array_key_exists('userid', $user) ? ['Taken', ''] : ['Error', 'Fandom user payload was ambiguous']);
    }

    /** @param array<string, mixed>|null $json @return array{0:string,1:string} */
    private function parsePolarsteps(Response $response, ?array $json, string $target): array
    {
        if (($json['error'] ?? null) === 'api_version_too_old_for_endpoint') {
            return ['Error', 'Polarsteps API version is outdated'];
        }
        if ($response->status() === 404 && ($json['detail'] ?? null) === 'Not Found') {
            return ['Available', ''];
        }
        if ($response->status() === 200 && strcasecmp((string) ($json['username'] ?? ''), $target) === 0) {
            return ['Taken', ''];
        }

        return ['Error', 'Unexpected Polarsteps response status: '.$response->status()];
    }

    /** @param array<string, mixed>|null $json @return array{0:string,1:string} */
    private function parseBuicha(Response $response, ?array $json, string $target): array
    {
        if ($response->status() === 404) {
            $code = data_get($json, 'error.code');
            $message = strtolower((string) data_get($json, 'error.message', ''));

            return $code === 'NO_SUCH_USER' || str_contains($message, 'no such user')
                ? ['Available', '']
                : ['Error', 'Misskey 404 response missing expected error payload'];
        }
        if ($response->status() === 200
            && ! empty($json['id'])
            && strcasecmp((string) ($json['username'] ?? ''), $target) === 0) {
            return ['Taken', ''];
        }

        return ['Error', 'Misskey response missing valid user payload'];
    }

    /** @return array{0:string,1:string} */
    private function parseHtmlLookup(Response $response, string $target): array
    {
        $body = $response->body();
        $lower = strtolower($body);

        return match ($this->key()) {
            'allthelyrics' => match (true) {
                str_contains($body, 'Invalid User specified'), str_contains($body, 'This user has not registered') => ['Available', ''],
                str_contains($body, 'The server is too busy') => ['Error', 'Forum server is currently down/too busy'],
                $response->status() === 200 => ['Taken', ''],
                default => ['Error', 'Unexpected status: '.$response->status()],
            },
            'odysee' => match (true) {
                $response->status() === 404 => ['Available', ''],
                $response->status() !== 200 => ['Error', 'Unexpected status code: '.$response->status()],
                preg_match('~<link\s+rel=["\']canonical["\']\s+href=["\'][^"\']*/@~i', $body) === 1,
                str_contains($lower, '/@'.strtolower($target)) => ['Taken', ''],
                default => ['Available', ''],
            },
            'pedsovet' => match (true) {
                str_contains($body, 'Пользователь не найден') => ['Available', ''],
                $response->status() !== 200 => ['Error', 'Unexpected response status: '.$response->status()],
                preg_match('~<p>Логин:\s*'.preg_quote($target, '~').'\s*</p>~iu', $body) === 1
                    && str_contains($body, 'Ссылка на профиль:') => ['Taken', ''],
                default => ['Error', 'Unexpected response body'],
            },
            'taplink' => match (true) {
                $response->status() === 404,
                str_contains($lower, 'landing page that drives your sales on instagram'),
                str_contains($lower, 'link in bio tool for instagram and tiktok - taplink') => ['Available', ''],
                $response->status() === 200
                    && (str_contains($lower, 'property="og:title"') || str_contains($lower, 'window.account')) => ['Taken', ''],
                default => ['Error', 'Could not verify profile details on Taplink'],
            },
            'livejasmin' => match (true) {
                $response->status() !== 200 => ['Error', 'Unexpected status: '.$response->status()],
                str_contains($lower, strtolower($target).' is not available') => ['Available', ''],
                preg_match('~<meta[^>]+(?:name|property)=["\']performerName["\'][^>]+content=["\']'.preg_quote($target, '~').'["\']~i', $body) === 1 => ['Taken', ''],
                default => ['Error', 'Profile confirmation not found'],
            },
            'pornhub' => match (true) {
                $response->status() === 404 && str_contains($body, 'Error Page Not Found') => ['Available', ''],
                $response->status() !== 200 => ['Error', 'Unexpected status: '.$response->status()],
                str_contains($lower, strtolower($target)) && (str_contains($lower, "'s profile - pornhub") || str_contains($lower, 'itemprop="name"')) => ['Taken', ''],
                str_ends_with(rtrim((string) $response->effectiveUri(), '/'), 'pornhub.com') => ['Error', 'Upstream redirected profile lookup to generic landing page'],
                default => ['Error', 'Profile confirmation not found'],
            },
            default => ['Error', 'Unsupported September HTML lookup contract'],
        };
    }

    /** @param array<string, mixed>|null $json @return array{0:string,1:string} */
    private function parseDiscourse(Response $response, ?array $json): array
    {
        if ($response->status() === 404) {
            $errors = is_array($json['errors'] ?? null) ? $json['errors'] : [];
            $notFound = ($json['error_type'] ?? null) === 'not_found';
            foreach ($errors as $error) {
                $notFound = $notFound || str_contains(strtolower((string) $error), 'not be found');
            }

            return $notFound
                ? ['Available', '']
                : ['Error', 'Discourse 404 response missing expected error payload'];
        }

        if ($response->status() === 200) {
            $user = is_array($json['user'] ?? null) ? $json['user'] : null;

            return $user !== null && is_scalar($user['username'] ?? null) && (string) $user['username'] !== ''
                ? ['Taken', '']
                : ['Error', 'Discourse 200 response missing valid user payload'];
        }

        return ['Error', 'Unexpected status code: '.$response->status()];
    }

    /** @param array<string, mixed>|null $json @return array{0:string,1:string} */
    private function parseGitea(Response $response, ?array $json, string $target): array
    {
        if ($response->status() === 404) {
            $message = strtolower((string) ($json['message'] ?? ''));

            return str_contains($message, 'does not exist') || str_contains($message, 'not found')
                ? ['Available', '']
                : ['Error', 'Gitea 404 response missing expected error payload'];
        }

        if ($response->status() === 200) {
            $login = (string) ($json['login'] ?? '');

            return $login !== '' && strcasecmp($login, $target) === 0
                ? ['Taken', '']
                : ['Error', 'Gitea 200 response missing valid user payload'];
        }

        return ['Error', 'Unexpected status code: '.$response->status()];
    }

    /** @param array<string, mixed>|null $json @return array{0:string,1:string} */
    private function parseLemmy(Response $response, ?array $json, string $target): array
    {
        if ($response->status() === 404) {
            $error = strtolower((string) ($json['error'] ?? ''));

            return str_contains($error, 'couldnt_find') || str_contains($error, 'not_found')
                ? ['Available', '']
                : ['Error', 'Lemmy 404 response missing expected error payload'];
        }

        if ($response->status() === 200) {
            $person = data_get($json, 'person_view.person');
            $name = is_array($person) ? (string) ($person['name'] ?? '') : '';

            return $name !== '' && strcasecmp($name, $target) === 0
                ? ['Taken', '']
                : ['Error', 'Lemmy 200 response missing valid person payload'];
        }

        return ['Error', 'Unexpected status code: '.$response->status()];
    }

    /** @return array{0:string,1:string} */
    private function parseGenericProfile(Response $response, string $target): array
    {
        if ($response->status() === 404) {
            return ['Available', ''];
        }
        if ($response->status() !== 200) {
            return ['Error', 'Unexpected status code: '.$response->status()];
        }

        $body = strtolower($response->body());
        foreach ([
            'user not found',
            'member not found',
            'profile does not exist',
            'user does not exist',
            'page not found',
            'no user found',
            'could not be found',
        ] as $marker) {
            if (str_contains($body, $marker)) {
                return ['Available', ''];
            }
        }

        return str_contains($body, strtolower($target))
            ? ['Taken', '']
            : ['Error', 'Profile page returned 200 but target username was not found'];
    }

    /** @return array{0:string,1:string} */
    private function parseCustomProfile(Response $response, string $target): array
    {
        if (in_array($this->key(), ['cssbattle', 'fragment', 'paypal', 'iha', 'chaturbate'], true)) {
            return $this->parseVerifiedCustomProfile($response, $target);
        }
        if ($response->status() === 404) {
            return ['Available', ''];
        }
        if ($response->status() < 200 || $response->status() >= 300) {
            return ['Error', 'Unexpected status code: '.$response->status()];
        }

        $body = strtolower($response->body());
        foreach ([
            'user not found',
            'profile not found',
            'member not found',
            'account not found',
            'profile does not exist',
            'user does not exist',
            'page not found',
            'no user found',
            'could not be found',
            'requested page could not be found',
            'requested page does not exist',
        ] as $marker) {
            if (str_contains($body, $marker)) {
                return ['Available', ''];
            }
        }

        return str_contains($body, strtolower($target))
            ? ['Taken', '']
            : ['Error', 'Profile response did not contain target evidence'];
    }

    /** @return array{0:string,1:string} */
    private function parseVerifiedCustomProfile(Response $response, string $target): array
    {
        if ($response->status() === 404 && $this->key() !== 'chaturbate') {
            return ['Available', ''];
        }
        if ($response->status() !== 200) {
            return ['Error', 'Unexpected '.$this->siteName().' status: '.$response->status()];
        }
        if ($this->key() === 'chaturbate') {
            $json = $this->decodeJson($response);
            if (! is_bool($json['valid'] ?? null) || ! is_array($json['errors'] ?? null)) {
                return ['Error', 'Chaturbate response missing form-validation errors'];
            }
            $errors = $json['errors']['username'] ?? [];
            if ($errors === [] || $errors === '') {
                $unknownFields = array_diff(array_keys($json['errors']), ['username', 'gender', 'terms', 'privacy_policy', 'password', 'password2', 'email']);

                return $unknownFields === []
                    ? ['Available', ''] : ['Error', 'Chaturbate returned non-field validation errors; account status unknown'];
            }
            foreach ((array) $errors as $error) {
                if (is_string($error) && stripos($error, 'already taken') !== false) {
                    return ['Taken', ''];
                }
            }

            return ['Error', 'Chaturbate rejected the username without an availability verdict'];
        }

        $xpath = $this->htmlXPath($response->body());
        $title = trim((string) $xpath->evaluate('string(//title)'));
        if ($this->key() === 'cssbattle') {
            $json = json_decode((string) $xpath->evaluate('string(//script[@id="__NEXT_DATA__"])'), true);
            $page = data_get($json, 'props.pageProps');
            if (is_array($page) && array_key_exists('player', $page)) {
                if ($page['player'] === null) {
                    return ['Available', ''];
                }
                if (is_array($page['player']) && strcasecmp((string) ($page['player']['username'] ?? ''), $target) === 0) {
                    return ['Taken', ''];
                }
            }

            return ['Error', 'CSSBattle player payload missing or did not match the requested username'];
        }
        if ($this->key() === 'fragment') {
            $status = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " tm-section-header-status ")]');
            $canonical = (string) $xpath->evaluate('string(//link[@rel="canonical"]/@href)');
            $ogUrl = (string) $xpath->evaluate('string(//meta[@property="og:url"]/@content)');
            $expected = 'https://fragment.com/username/'.rawurlencode($target);
            if ($status->length > 0 && (strcasecmp(rtrim($canonical, '/'), $expected) === 0
                || strcasecmp(rtrim($ogUrl, '/'), $expected) === 0)) {
                return ['Taken', ''];
            }
            if (strcasecmp($title, 'Fragment') === 0 && $status->length === 0) {
                return ['Available', ''];
            }

            return ['Error', 'Fragment page lacked a verified username listing'];
        }
        if ($this->key() === 'paypal') {
            $description = (string) $xpath->evaluate('string(//meta[@property="og:description"]/@content)');
            $ogTitle = trim((string) $xpath->evaluate('string(//meta[@property="og:title"]/@content)'));
            if (preg_match('~paypal\.me/'.preg_quote($target, '~').'(?![a-z0-9._-])~i', $description) === 1) {
                return ['Taken', ''];
            }
            if (in_array(strtolower($ogTitle), ['get your very own paypal.me link', 'paypal.me', 'paypal'], true)) {
                return ['Available', ''];
            }

            return ['Error', 'PayPal.Me response lacked profile or explicit landing-page evidence'];
        }

        $username = trim((string) $xpath->evaluate('string(//*[contains(concat(" ", normalize-space(@class), " "), " member_username ")])'));
        if ($username !== '') {
            return strcasecmp($username, $target) === 0
                ? ['Taken', ''] : ['Error', 'Iha profile did not match the requested username'];
        }
        $visible = (string) $xpath->evaluate('string(//body)');
        if (str_contains($visible, 'Sellist kasutajat ei leitud')
            || ($title === 'Iha.ee - Seksikate inimeste kohtumispaik' && $xpath->query('//*[@id="online_list"]')->length > 0)) {
            return ['Available', ''];
        }

        return ['Error', 'Iha response lacked a verified member profile'];
    }

    private function htmlXPath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return new DOMXPath($document);
    }

    /** @param array<string, mixed> $json @return array<string, mixed> */
    private function discourseMetadata(array $json): array
    {
        $user = is_array($json['user'] ?? null) ? $json['user'] : [];
        $metadata = $this->metadata($user, [
            'username' => 'username',
            'display_name' => 'name',
            'title' => 'title',
            'location' => 'location',
            'website_url' => 'website',
            'trust_level' => 'trust_level',
            'badge_count' => 'badge_count',
            'created_at' => 'created_at',
            'bio' => 'bio_raw',
        ]);
        if (! isset($metadata['website_url']) && is_scalar($user['website_name'] ?? null)) {
            $metadata['website_url'] = (string) $user['website_name'];
        }
        if (! isset($metadata['bio']) && is_scalar($user['bio_cooked'] ?? null)) {
            $metadata['bio'] = trim(strip_tags((string) $user['bio_cooked']));
        }
        $avatar = is_scalar($user['avatar_template'] ?? null) ? (string) $user['avatar_template'] : '';
        if ($avatar !== '') {
            $avatar = str_replace('{size}', '240', $avatar);
            $metadata['avatar_url'] = str_starts_with($avatar, '/') ? $this->origin().$avatar : $avatar;
        }
        $metadata['sources'] = ['discourse_user_api'];

        return $metadata;
    }

    /** @param array<string, mixed> $json @return array<string, mixed> */
    private function giteaMetadata(array $json): array
    {
        $metadata = $this->metadata($json, [
            'username' => 'login',
            'display_name' => 'full_name',
            'website_url' => 'website',
            'location' => 'location',
            'bio' => 'description',
            'created_at' => 'created',
            'avatar_url' => 'avatar_url',
        ]);
        $email = is_scalar($json['email'] ?? null) ? (string) $json['email'] : '';
        if ($email !== '' && ! str_ends_with(strtolower($email), '@noreply.localhost')) {
            $metadata['email'] = $email;
        }
        $metadata['sources'] = ['gitea_user_api'];

        return $metadata;
    }

    /** @param array<string, mixed> $json @return array<string, mixed> */
    private function lemmyMetadata(array $json): array
    {
        $person = data_get($json, 'person_view.person');
        if (! is_array($person)) {
            return [];
        }
        $metadata = $this->metadata($person, [
            'username' => 'name',
            'display_name' => 'display_name',
            'bio' => 'bio',
            'created_at' => 'published',
            'matrix_user_id' => 'matrix_user_id',
            'actor_id' => 'actor_id',
            'avatar_url' => 'avatar',
        ]);
        $metadata['sources'] = ['lemmy_user_api'];

        return $metadata;
    }

    /** @return array<string, mixed> */
    private function genericProfileMetadata(Response $response, string $target): array
    {
        $metadata = ['username' => $target, 'sources' => ['public_profile_html']];
        if (preg_match('~<title>(.*?)</title>~is', $response->body(), $title) === 1) {
            $value = trim(html_entity_decode(strip_tags($title[1]), ENT_QUOTES | ENT_HTML5));
            if ($value !== '' && mb_strlen($value) < 120) {
                $metadata['title'] = $value;
            }
        }
        if (preg_match('~<img[^>]+src=["\'](https?://[^"\']*(?:avatar|profile|user)[^"\']*)["\']~i', $response->body(), $avatar) === 1) {
            $metadata['avatar_url'] = html_entity_decode($avatar[1], ENT_QUOTES | ENT_HTML5);
        }

        return $metadata;
    }

    /** @return array<string, mixed> */
    private function customProfileMetadata(Response $response, string $target): array
    {
        $metadata = ['username' => $target, 'sources' => ['public_profile']];
        if (preg_match('~<title>(.*?)</title>~is', $response->body(), $title) === 1) {
            $value = trim(html_entity_decode(strip_tags($title[1]), ENT_QUOTES | ENT_HTML5));
            if ($value !== '' && mb_strlen($value) < 160) {
                $metadata['title'] = $value;
            }
        }

        return $metadata;
    }

    /** @param array<string, mixed> $json @return array<string, mixed> */
    private function characterAiMetadata(array $json): array
    {
        $profile = data_get($json, 'result.data.json');
        if (! is_array($profile)) {
            return [];
        }

        return $this->metadata($profile, [
            'username' => 'username',
            'display_name' => 'name',
            'bio' => 'bio',
            'followers' => 'num_followers',
            'following' => 'num_following',
            'subscription' => 'subscription_type',
        ]);
    }

    /** @param array<string, mixed> $json @return array<string, mixed> */
    private function devHuntMetadata(array $json): array
    {
        $profile = is_array($json[0] ?? null) ? $json[0] : null;
        if (! is_array($profile)) {
            return [];
        }

        return $this->metadata($profile, [
            'username' => 'username',
            'display_name' => 'full_name',
            'headline' => 'headline',
            'bio' => 'about',
            'website_url' => 'website_url',
            'social_url' => 'social_url',
            'user_id' => 'id',
            'updated_at' => 'updated_at',
            'avatar_url' => 'avatar_url',
        ]);
    }

    /** @param array<string, mixed> $json @return array<string, mixed> */
    private function matrixMetadata(array $json): array
    {
        $metadata = $this->metadata($json, [
            'display_name' => 'displayname',
            'mxc_avatar' => 'avatar_url',
        ]);
        $avatar = (string) ($json['avatar_url'] ?? '');
        if (str_starts_with($avatar, 'mxc://')) {
            $metadata['avatar_url'] = 'https://matrix.org/_matrix/media/v3/download/'.substr($avatar, 6);
        }

        return $metadata;
    }

    /** @param array<string, mixed> $source @param array<string, string> $fields @return array<string, mixed> */
    private function metadata(array $source, array $fields): array
    {
        $metadata = [];
        foreach ($fields as $output => $input) {
            $value = $source[$input] ?? null;
            if (is_scalar($value) && $value !== '') {
                $metadata[$output] = $value;
            }
        }

        return $metadata;
    }

    /** @return array<string, mixed>|null */
    private function decodeJson(Response $response): ?array
    {
        try {
            $json = $response->json();

            return is_array($json) ? $json : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function interpolate(string $value, string $target): string
    {
        return str_replace(['{target}', '{user}', '{username}'], rawurlencode($target), $value);
    }

    /** @param array<string, mixed> $values @return array<string, mixed> */
    private function interpolateArray(array $values, string $target): array
    {
        foreach ($values as $key => $value) {
            if (is_string($value)) {
                $values[$key] = str_replace(['{target}', '{user}', '{username}'], $target, $value);
            }
        }

        return $values;
    }

    private function origin(): string
    {
        $parts = parse_url((string) data_get($this->definition, 'request.url', ''));
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return '';
        }

        return $parts['scheme'].'://'.$parts['host'];
    }
}
