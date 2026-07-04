from __future__ import annotations

import importlib.util
import json
import re
import sys
from pathlib import Path
from textwrap import dedent

ROOT = Path(__file__).resolve().parents[1]
JUNE = ROOT / 'user-scanner-py-june-release' / 'user_scanner'
APRIL = ROOT / 'user-scanner-py-april-release' / 'user_scanner'
PHP_ROOT = ROOT / 'app' / 'Services' / 'Scanner' / 'Validators' / 'Generated'
FIXTURE_PATH = ROOT / 'tests' / 'Fixtures' / 'june_manual_validator_cases.php'
JUNE_ONLY_CONFIG = ROOT / 'config' / 'scanner_june_only.php'

SAFE_USER_KEYS = {
    '35photo', 'academia', 'amazon', 'appledeveloper', 'bitly', 'freepik',
    'googleplaystore', 'harvard', 'ifttt', 'myspace', 'ok', 'packagist',
    'rubygems', 'themeforest', 'vk', 'weforum',
}


def load_generator_module():
    spec = importlib.util.spec_from_file_location('june_gen_mod', ROOT / 'scripts' / 'generate_validators.py')
    module = importlib.util.module_from_spec(spec)
    sys.modules['june_gen_mod'] = module
    assert spec.loader is not None
    spec.loader.exec_module(module)
    module.PY_ROOT = JUNE
    return module


def php_escape(value: str) -> str:
    return value.replace('\\', '\\\\').replace("'", "\\'")


def render_php_value(value) -> str:
    if isinstance(value, dict):
        if not value:
            return '[]'
        lines = ['[']
        for key, item in value.items():
            lines.append(f"                    '{php_escape(str(key))}' => {render_php_value(item)},")
        lines.append('                ]')
        return '\n'.join(lines)

    if isinstance(value, list):
        if not value:
            return '[]'
        lines = ['[']
        for item in value:
            lines.append(f'                    {render_php_value(item)},')
        lines.append('                ]')
        return '\n'.join(lines)

    if isinstance(value, bool):
        return 'true' if value else 'false'

    if value is None:
        return 'null'

    if isinstance(value, (int, float)):
        return str(value)

    return "'" + php_escape(str(value)) + "'"


def studly(name: str) -> str:
    value = ''.join(part.capitalize() for part in name.replace('.', '_').split('_'))
    return 'Site' + value if value and value[0].isdigit() else value


def site_label(name: str) -> str:
    return ''.join(part.capitalize() for part in name.replace('.', '_').split('_'))


def source_for(spec) -> str:
    return spec.python_path.read_text(encoding='utf-8')


def inventory_keys(py_root: Path, folder: str) -> set[str]:
    keys: set[str] = set()
    for category_dir in sorted((py_root / folder).iterdir()):
        if not category_dir.is_dir() or category_dir.name.startswith('__') or category_dir.name.lower() == 'abandoned':
            continue
        for py_file in sorted(category_dir.glob('*.py')):
            if py_file.name == '__init__.py':
                continue
            keys.add(py_file.stem.replace('.', '_').lower())
    return keys


def first_match(pattern: str, text: str) -> str:
    match = re.search(pattern, text, re.S)
    return match.group(1) if match else ''


def extract_literal_url(text: str) -> str:
    show_url = first_match(r"show_url\s*=\s*f?[\"']([^\"']+)[\"']", text)
    if show_url:
        return show_url
    return first_match(r"url\s*=\s*f?[\"']([^\"']+)[\"']", text)


def to_php_interpolated(text: str) -> str:
    return text.replace('{user}', '{$target}').replace('{email}', '{$target}')


def render_assoc_array(values: dict[str, str]) -> str:
    if not values:
        return '[]'

    lines = ['[']
    for key, value in values.items():
        if value.startswith('__PHP_EXPR__'):
            rendered = value.removeprefix('__PHP_EXPR__')
        elif value in {'true', 'false'}:
            rendered = value
        else:
            rendered = "'" + php_escape(value) + "'"
        lines.append(f"            '{php_escape(key)}' => {rendered},")
    lines.append('        ]')
    return '\n'.join(lines)


def generic_user_rules(text: str) -> dict[str, list]:
    available_statuses: set[int] = set()
    taken_statuses: set[int] = set()
    available_needles: list[str] = []
    taken_needles: list[str] = []
    error_rules: list[tuple[int, str]] = []

    lines = text.splitlines()
    for index, line in enumerate(lines):
        stripped = line.strip()
        if not any(token in stripped for token in ('return Result.available', 'return Result.taken', 'return Result.error')):
            continue

        context = '\n'.join(lines[max(0, index - 5): index + 1])
        statuses = {int(value) for value in re.findall(r'response\.status_code\s*==\s*(\d+)', context)}
        for chunk in re.findall(r'response\.status_code\s+in\s+\[([^\]]+)\]', context):
            for part in chunk.split(','):
                part = part.strip()
                if part.isdigit():
                    statuses.add(int(part))

        needles = re.findall(
            r'[\"\']([^\"\']+)[\"\']\s+in\s+(?:html|response\.text|res_text|response_text|text|body|html_text|str\(errors_msg\)\.lower\(\)|message\.lower\(\))',
            context,
        )

        if 'return Result.available' in stripped:
            available_statuses.update(statuses)
            available_needles.extend([needle for needle in needles if '{' not in needle])
            continue

        if 'return Result.taken' in stripped:
            taken_statuses.update(statuses)
            taken_needles.extend([needle for needle in needles if '{' not in needle])
            continue

        if 'return Result.error' in stripped and statuses:
            reason_match = re.search(r'Result\.error\((.+?)\)', stripped)
            reason = reason_match.group(1) if reason_match else ''
            for status in statuses:
                error_rules.append((status, reason))

    dedupe = lambda items: list(dict.fromkeys(items))
    return {
        'available_statuses': sorted(available_statuses),
        'taken_statuses': sorted(taken_statuses),
        'available_needles': dedupe(available_needles),
        'taken_needles': dedupe(taken_needles),
        'error_rules': error_rules,
    }


def render_user_validator(spec, text: str) -> str:
    site_url = extract_literal_url(text)
    request_url = first_match(r"url\s*=\s*f?[\"']([^\"']+)[\"']", text) or site_url
    rules = generic_user_rules(text)

    if spec.key == 'wikipedia':
        parse = dedent(
            """
            protected function parseConnectorResponse(Response $response, string $target): array
            {
                if ($response->status() !== 200) {
                    return ['Error', 'Unexpected status: ' . $response->status()];
                }

                $data = $response->json();
                $users = data_get($data, 'query.users', []);
                if (!is_array($users) || $users === []) {
                    return ['Error', 'Invalid API response format'];
                }

                $userData = $users[0] ?? [];
                if (is_array($userData) && array_key_exists('missing', $userData)) {
                    return ['Available', ''];
                }

                return ['Taken', ''];
            }
            """
        ).strip()
    elif spec.key == 'githubgist':
        parse = dedent(
            """
            protected function parseConnectorResponse(Response $response, string $target): array
            {
                if ($response->status() === 404) {
                    return ['Available', ''];
                }
                if ($response->status() === 403) {
                    return ['Error', 'Rate limited by GitHub API'];
                }
                if ($response->status() === 200) {
                    return ['Taken', ''];
                }

                return ['Error', 'Unexpected response body, report it via GitHub issues.'];
            }
            """
        ).strip()
    elif spec.key == 'warpcast':
        parse = dedent(
            """
            protected function parseConnectorResponse(Response $response, string $target): array
            {
                if (in_array($response->status(), [400, 404], true)) {
                    return ['Available', ''];
                }
                if ($response->status() === 200) {
                    $data = $response->json();
                    if (!empty(data_get($data, 'result.user'))) {
                        return ['Taken', ''];
                    }
                }

                return ['Error', 'Unexpected response body, report it via GitHub issues.'];
            }
            """
        ).strip()
    elif spec.key == 'beatstars':
        parse = dedent(
            """
            protected function requestMethod(): string
            {
                return 'POST';
            }

            protected function requestHeaders(): array
            {
                return [
                    'Accept-Language' => 'en,en-US;q=0.9',
                ];
            }

            protected function requestBodyMode(): string
            {
                return 'json';
            }

            protected function requestBody(string $target): array
            {
                return [
                    'operationName' => 'identifierAvailable',
                    'variables' => [
                        'identifier' => $target,
                    ],
                    'query' => "query identifierAvailable($identifier: String!) {\n  identifierAvailable(identifier: $identifier) {\n    ...AccountBasicInfo\n    __typename\n  }\n}\n\nfragment AccountBasicInfo on IsIdentifierAvailableResponse {\n  available\n  profileDetails {\n    email\n    username\n    artwork {\n      url\n      fitInUrl\n      __typename\n    }\n    __typename\n  }\n  __typename\n}",
                ];
            }

            protected function parseConnectorResponse(Response $response, string $target): array
            {
                $data = $response->json();
                $errors = data_get($data, 'errors', []);
                if (is_array($errors) && $errors !== []) {
                    $message = (string) data_get($errors, '0.message', '');
                    if (str_contains($message, 'ITEM_NOT_FOUND')) {
                        return ['Available', 'Username too short or invalid length'];
                    }
                    if (str_contains(strtolower($message), 'valid email or username')) {
                        return ['Available', 'Invalid username format'];
                    }
                    return ['Error', 'API Error: ' . $message];
                }

                $available = data_get($data, 'data.identifierAvailable.available');
                if ($available === true) {
                    return ['Available', ''];
                }
                if ($available === false) {
                    return ['Taken', ''];
                }

                return ['Error', 'Could not parse identifier data'];
            }
            """
        ).strip()
    elif spec.key == 'pypi':
        parse = dedent(
            """
            protected function requestMethod(): string
            {
                return 'POST';
            }

            protected function requestHeadersForTarget(string $target): array
            {
                return [
                    'Content-Type' => 'text/xml',
                ];
            }

            protected function requestRawBody(string $target): ?string
            {
                return '<?xml version="1.0"?><methodCall><methodName>user_packages</methodName><params><param><value><string>' . $target . '</string></value></param></params></methodCall>';
            }

            public function check(string $target, array $options = []): ScanResult
            {
                if (!preg_match('/^(?!_+$)[A-Za-z0-9._-]+$/', $target)) {
                    return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Username may only contain letters, numbers, periods, underscores, and hyphens, and cannot consist solely of underscores', mode: $this->mode(), key: $this->key());
                }

                return parent::check($target, $options);
            }

            protected function parseConnectorResponse(Response $response, string $target): array
            {
                if ($response->status() !== 200) {
                    return ['Error', 'XML-RPC endpoint returned status code: ' . $response->status()];
                }

                $body = $response->body();
                if (str_contains($body, '<array><data></data></array>') || str_contains($body, '<value><array><data/></array></value>')) {
                    return ['Available', ''];
                }
                if (str_contains($body, '<methodResponse')) {
                    return ['Taken', ''];
                }

                return ['Error', 'System error checking XML-RPC'];
            }
            """
        ).strip()
    else:
        available_checks: list[str] = [f'$status === {status}' for status in rules['available_statuses']]
        available_checks.extend(
            f"str_contains($body, '{php_escape(needle)}')" for needle in rules['available_needles'][:4]
        )
        taken_checks: list[str] = [f'$status === {status}' for status in rules['taken_statuses']]
        taken_checks.extend(
            f"str_contains($body, '{php_escape(needle)}')" for needle in rules['taken_needles'][:4]
        )

        lines = [
            'protected function parseConnectorResponse(Response $response, string $target): array',
            '{',
            '    $status = $response->status();',
            '    $body = $response->body();',
            '',
        ]

        for status, reason in rules['error_rules']:
            if status in {200, 404}:
                continue
            clean_reason = reason.strip().strip('"\'') or f'HTTP {status}'
            if clean_reason.startswith(('f"', "f'")):
                clean_reason = f'HTTP {status}'
            lines.extend([
                f'    if ($status === {status}) {{',
                f"        return ['Error', '{php_escape(clean_reason)}'];",
                '    }',
                '',
            ])

        if available_checks:
            lines.extend([
                f"    if ({' || '.join(available_checks)}) {{",
                "        return ['Available', ''];",
                '    }',
                '',
            ])

        if taken_checks:
            lines.extend([
                f"    if ({' || '.join(taken_checks)}) {{",
                "        return ['Taken', ''];",
                '    }',
                '',
            ])

        if 'Result.taken' in text:
            lines.extend([
                '    if ($status === 200) {',
                "        return ['Taken', ''];",
                '    }',
                '',
            ])

        lines.extend([
            "    return ['Error', 'Unexpected response body'];",
            '}',
        ])
        parse = '\n'.join(lines)

    return f"""<?php

declare(strict_types=1);

namespace App\\Services\\Scanner\\Validators\\Generated\\User;

// parity-source: {spec.python_path.as_posix()}
// parity-class: manual-june

use App\\DTO\\ScanResult;
use App\\Services\\Scanner\\Validators\\Generated\\BaseGeneratedValidator;
use Illuminate\\Http\\Client\\Response;

final class {spec.class_name} extends BaseGeneratedValidator
{{
    public function key(): string
    {{
        return '{spec.key}';
    }}

    public function category(): string
    {{
        return '{spec.category}';
    }}

    public function mode(): string
    {{
        return 'username';
    }}

    public function siteName(): string
    {{
        return '{site_label(spec.key)}';
    }}

    public function siteUrl(): string
    {{
        return '{php_escape(site_url)}';
    }}

    protected function requestUrl(string $target): string
    {{
        return "{php_escape(to_php_interpolated(request_url))}";
    }}

    protected function followRedirects(): bool
    {{
        return true;
    }}

    protected function timeoutSeconds(): int
    {{
        return {spec.timeout};
    }}

    {parse}
}}
"""


def render_one_step_email(
    class_name: str,
    key: str,
    category: str,
    site_name: str,
    site_url: str,
    request_url: str,
    timeout: int,
    *,
    method: str = 'POST',
    headers: dict[str, str] | None = None,
    query: dict[str, str] | None = None,
    body: dict[str, str] | None = None,
    body_mode: str = 'json',
    parse: str,
) -> str:
    headers = headers or {}
    query = query or {}
    body = body or {}
    return f"""<?php

declare(strict_types=1);

namespace App\\Services\\Scanner\\Validators\\Generated\\Email;

// parity-class: manual-june

use App\\Services\\Scanner\\Validators\\Generated\\BaseGeneratedValidator;
use Illuminate\\Http\\Client\\Response;

final class {class_name} extends BaseGeneratedValidator
{{
    public function key(): string
    {{
        return '{key}';
    }}

    public function category(): string
    {{
        return '{category}';
    }}

    public function mode(): string
    {{
        return 'email';
    }}

    public function siteName(): string
    {{
        return '{site_name}';
    }}

    public function siteUrl(): string
    {{
        return '{php_escape(site_url)}';
    }}

    protected function requestMethod(): string
    {{
        return '{method}';
    }}

    protected function requestUrl(string $target): string
    {{
        return '{php_escape(request_url)}';
    }}

    protected function requestHeadersForTarget(string $target): array
    {{
        return {render_assoc_array(headers)};
    }}

    protected function requestQuery(string $target): array
    {{
        return {render_assoc_array(query)};
    }}

    protected function requestBodyMode(): string
    {{
        return '{body_mode}';
    }}

    protected function requestBody(string $target): array
    {{
        return {render_assoc_array(body)};
    }}

    protected function timeoutSeconds(): int
    {{
        return {timeout};
    }}

    {parse}
}}
"""


EMAIL_SPECS = {
    'buymeacoffee': render_one_step_email(
        'BuymeacoffeeValidator',
        'buymeacoffee',
        'creator',
        'Buymeacoffee',
        'https://www.buymeacoffee.com/',
        'https://app.buymeacoffee.com/api/v1/email/login',
        7,
        headers={'Content-Type': 'application/json', 'x-device-fingerprint': '__PHP_EXPR__bin2hex(random_bytes(10))'},
        body={'email': '__PHP_EXPR__$target', 'client_response': '', 'captcha_version': 'v3'},
        parse=dedent(
            """
            protected function parseConnectorResponse(Response $response, string $target): array
            {
                if ($response->status() === 403) {
                    return ['Error', 'Caught by Cloudflare/WAF (403)'];
                }
                if ($response->status() === 429) {
                    return ['Error', 'Rate limited by BuyMeaCoffee (429)'];
                }
                $data = $response->json();
                $message = strtolower((string) ($data['message'] ?? ''));
                if ($response->status() === 200 && ($data['otp_login'] ?? null) === true) {
                    return ['Registered', ''];
                }
                if ($response->status() === 422 || str_contains($message, 'no account with the given')) {
                    return ['Not Registered', ''];
                }
                foreach ((array) data_get($data, 'errors.email', []) as $error) {
                    if (str_contains(strtolower((string) $error), 'no account')) {
                        return ['Not Registered', ''];
                    }
                }
                return ['Error', 'Unexpected API State (HTTP ' . $response->status() . ')'];
            }
            """
        ).strip(),
    ),
    'kick': render_one_step_email(
        'KickValidator',
        'kick',
        'creator',
        'Kick',
        'https://kick.com/',
        'https://kick.com/api/v1/signup/verify/email',
        7,
        headers={'Content-Type': 'application/json', 'x-req-trace': '__PHP_EXPR__bin2hex(random_bytes(16))'},
        body={'email': '__PHP_EXPR__$target'},
        parse=dedent(
            """
            protected function parseConnectorResponse(Response $response, string $target): array
            {
                if ($response->status() === 403) {
                    return ['Error', 'Caught by Cloudflare WAF (403)'];
                }
                if ($response->status() === 429) {
                    return ['Error', 'Rate limited by Kick (429)'];
                }
                if ($response->status() === 204) {
                    return ['Not Registered', ''];
                }
                if ($response->status() === 422) {
                    foreach ((array) data_get($response->json(), 'errors.email', []) as $error) {
                        if (str_contains(strtolower((string) $error), 'already been taken')) {
                            return ['Registered', ''];
                        }
                    }
                    return ['Error', 'Failed to parse 422 validation content'];
                }
                return ['Error', 'Unexpected response state (HTTP ' . $response->status() . ')'];
            }
            """
        ).strip(),
    ),
    'hackerearth': render_one_step_email(
        'HackerearthValidator',
        'hackerearth',
        'dev',
        'Hackerearth',
        'https://www.hackerearth.com',
        'https://www.hackerearth.com/api/v1/sparta/auth/signup/',
        15,
        headers={'Content-Type': 'application/json', 'Origin': 'https://www.hackerearth.com', 'Referer': 'https://www.hackerearth.com'},
        query={'sxhr': 'true', 'next': '/community/dashboard/'},
        body={'first_name': 'Hunan', 'last_name': 'Fish', 'email': '__PHP_EXPR__$target', 'password': '', 'policy_accepted': 'true', 'next': '/community/dashboard/'},
        parse=dedent(
            """
            protected function parseConnectorResponse(Response $response, string $target): array
            {
                if ($response->status() === 403) {
                    return ['Error', 'Caught by WAF (403)'];
                }
                if ($response->status() === 429) {
                    return ['Error', 'Rate limited (429)'];
                }
                $errors = (array) (data_get($response->json(), 'errors', []));
                $emailError = strtolower((string) ($errors['email'] ?? ''));
                if (str_contains($emailError, 'already registered')) {
                    return ['Registered', ''];
                }
                if (array_key_exists('password', $errors) && $emailError === '') {
                    return ['Not Registered', ''];
                }
                return ['Error', 'Unexpected response field layout'];
            }
            """
        ).strip(),
    ),
    'hackerrank': render_one_step_email(
        'HackerrankValidator',
        'hackerrank',
        'dev',
        'Hackerrank',
        'https://www.hackerrank.com',
        'https://www.hackerrank.com/auth/valid_email',
        15,
        headers={'Content-Type': 'application/json', 'Origin': 'https://www.hackerrank.com', 'Referer': 'https://www.hackerrank.com'},
        body={'email': '__PHP_EXPR__$target'},
        parse=dedent(
            """
            protected function parseConnectorResponse(Response $response, string $target): array
            {
                if ($response->status() === 403) {
                    return ['Error', 'Caught by Cloudflare/WAF (403)'];
                }
                if ($response->status() === 429) {
                    return ['Error', 'Rate limited (429)'];
                }
                if ($response->status() !== 200) {
                    return ['Error', 'HTTP Error: ' . $response->status()];
                }
                $data = $response->json();
                $status = $data['status'] ?? null;
                $internal = (string) ($data['internal_status_code'] ?? '');
                $errors = strtolower((string) ($data['errors'] ?? ''));
                if ($status === false || $internal === 'already_registered' || str_contains($errors, 'already registered')) {
                    return ['Registered', ''];
                }
                if ($status === true) {
                    return ['Not Registered', ''];
                }
                return ['Error', 'Unexpected response payload schema'];
            }
            """
        ).strip(),
    ),
    'bunny': render_one_step_email(
        'BunnyValidator',
        'bunny',
        'hosting',
        'Bunny',
        'https://dash.bunny.net/',
        'https://api.bunny.net/auth/register',
        6,
        headers={'Content-Type': 'application/json', 'Origin': 'https://dash.bunny.net', 'Referer': 'https://dash.bunny.net/'},
        body={'AffiliateCode': '9sa3wl8vst', 'PowToken': '39bbb876b0e4f380:e80448fecdf5d1a40fcabf2e20d79c', 'Email': '__PHP_EXPR__$target', 'Password': 'th3_knight_n3v3r_had_th3_st33l_h3rt_it_was_an_arm0r'},
        parse=dedent(
            """
            protected function parseConnectorResponse(Response $response, string $target): array
            {
                if ($response->status() === 403) {
                    return ['Error', 'Caught by WAF/Mitigation (403)'];
                }
                if ($response->status() === 429) {
                    return ['Error', 'Rate limited (429)'];
                }
                $data = $response->json();
                $message = strtolower((string) ($data['Message'] ?? ''));
                $field = strtolower((string) ($data['Field'] ?? ''));
                if (str_contains($message, 'already in use') || $field === 'email') {
                    return ['Registered', ''];
                }
                if (str_contains($message, 'passwords must have')) {
                    return ['Not Registered', ''];
                }
                return ['Error', 'Unexpected response structure: ' . substr((string) ($data['Message'] ?? ''), 0, 50)];
            }
            """
        ).strip(),
    ),
    'ama': render_one_step_email(
        'AmaValidator',
        'ama',
        'other',
        'Ama',
        'https://www.ama-assn.org',
        'https://fsso.ama-assn.org/api/resetPassword',
        10,
        headers={'Content-Type': 'application/json', 'Origin': 'https://fsso.ama-assn.org', 'Referer': 'https://www.ama-assn.org', 'Cookie': 'IV_JCT=%2Flogin;'},
        body={'emailAddressOrPhone': '__PHP_EXPR__$target', 'fedType': 'oAuth', 'returnUrl': '/AMAresources', 'refererUrl': '/AMAresources', 'successUrl': 'https://www.ama-assn.org/', 'appCxUrl': 'https://www.ama-assn.org/'},
        parse=dedent(
            """
            protected function parseConnectorResponse(Response $response, string $target): array
            {
                if ($response->status() === 403) {
                    return ['Error', 'Caught by WAF (403)'];
                }
                if ($response->status() === 429) {
                    return ['Error', 'Rate limited (429)'];
                }
                $data = $response->json();
                $message = strtolower((string) ($data['message'] ?? ''));
                $code = (string) ($data['httpCode'] ?? '');
                if ($code === '200' && str_contains($message, 'sent successfully')) {
                    return ['Registered', ''];
                }
                if ($code === '202' || str_contains($message, 'not found') || str_contains($message, 'please use an existing account')) {
                    return ['Not Registered', ''];
                }
                return ['Error', 'Logic Mismatch - Code: ' . $code . ' | Msg: ' . substr((string) ($data['message'] ?? ''), 0, 50)];
            }
            """
        ).strip(),
    ),
    'etsy': render_one_step_email(
        'EtsyValidator',
        'etsy',
        'shopping',
        'Etsy',
        'https://www.etsy.com',
        'https://www.etsy.com/api/v3/ajax/public/users/by-identity-optional',
        10,
        method='GET',
        headers={'Referer': 'https://www.etsy.com/join/email'},
        query={'identity': '__PHP_EXPR__$target'},
        body={},
        body_mode='form',
        parse=dedent(
            """
            protected function parseConnectorResponse(Response $response, string $target): array
            {
                if ($response->status() === 403) {
                    return ['Error', '403'];
                }
                if (trim($response->body()) === 'null') {
                    return ['Not Registered', ''];
                }
                if (!empty(($response->json())['user_id'] ?? null)) {
                    return ['Registered', ''];
                }
                return ['Error', 'Unexpected response body structure'];
            }
            """
        ).strip(),
    ),
    'nykaaman': render_one_step_email(
        'NykaamanValidator',
        'nykaaman',
        'shopping',
        'Nykaaman',
        'https://www.nykaaman.com',
        'https://www.nykaaman.com/app-api/index.php/customer/check_existence',
        15,
        headers={'Origin': 'https://www.nykaaman.com', 'Referer': 'https://www.nykaaman.com?ptype=auth&root=myAccount_topBar', 'Cookie': 'storeId=men'},
        query={'catalog_tag_filter': 'men'},
        body={'email': '__PHP_EXPR__$target', 'platform': 'web', 'captcha_type': 'v3'},
        body_mode='form',
        parse=dedent(
            """
            protected function parseConnectorResponse(Response $response, string $target): array
            {
                if ($response->status() === 403) {
                    return ['Error', 'Caught by WAF (403)'];
                }
                if ($response->status() === 429) {
                    return ['Error', 'Rate limited (429)'];
                }
                if ($response->status() !== 200) {
                    return ['Error', 'HTTP Error: ' . $response->status()];
                }
                $inner = (array) data_get($response->json(), 'response', []);
                $exists = $inner['is_exists'] ?? null;
                $message = strtolower((string) ($inner['message'] ?? ''));
                if ($exists === true || str_contains($message, 'already registered')) {
                    return ['Registered', ''];
                }
                if ($exists === false || str_contains($message, 'welcome to nykaa')) {
                    return ['Not Registered', ''];
                }
                return ['Error', 'Unexpected JSON response structure'];
            }
            """
        ).strip(),
    ),
}

EMAIL_HANDSHAKE = {
    'rubygems': dedent(
        """
        <?php

        declare(strict_types=1);

        namespace App\\Services\\Scanner\\Validators\\Generated\\Email;

        // parity-class: manual-june

        use App\\DTO\\ScanResult;
        use App\\Services\\Scanner\\Validators\\Generated\\BaseGeneratedValidator;
        use Illuminate\\Support\\Facades\\Http;

        final class RubygemsValidator extends BaseGeneratedValidator
        {
            public function key(): string { return 'rubygems'; }
            public function category(): string { return 'dev'; }
            public function mode(): string { return 'email'; }
            public function siteName(): string { return 'Rubygems'; }
            public function siteUrl(): string { return 'https://rubygems.org'; }

            public function check(string $target, array $options = []): ScanResult
            {
                try {
                    $request = Http::timeout(15)->withOptions(['allow_redirects' => true, 'verify' => (bool) config('scanner.verify_ssl', false)]);
                    $init = $request->get('https://rubygems.org/sign_up');
                    if ($init->status() !== 200) {
                        return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Failed to grab verification context: ' . $init->status(), mode: $this->mode(), key: $this->key());
                    }
                    if (!preg_match('/name="authenticity_token" value="([^"]+)"/', $init->body(), $match)) {
                        return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Could not scrape Rails authenticity token', mode: $this->mode(), key: $this->key());
                    }
                    $response = $request->asForm()->post('https://rubygems.org/users', [
                        'authenticity_token' => $match[1],
                        'user[full_name]' => '',
                        'user[email]' => $target,
                        'user[handle]' => '',
                        'user[password]' => '',
                        'user[public_email]' => '0',
                        'commit' => 'Sign up',
                    ]);
                    $body = $response->body();
                    if (str_contains($body, 'has already been taken')) {
                        return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', mode: $this->mode(), key: $this->key());
                    }
                    if (str_contains($body, 'prohibited this user from being saved') || str_contains($body, "Password can't be blank")) {
                        return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', mode: $this->mode(), key: $this->key());
                    }
                    return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected signature inside response DOM tree', mode: $this->mode(), key: $this->key());
                } catch (\\Throwable $e) {
                    return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $e->getMessage(), mode: $this->mode(), key: $this->key());
                }
            }
        }
        """
    ).strip() + "\n",
    'luarocks': dedent(
        """
        <?php

        declare(strict_types=1);

        namespace App\\Services\\Scanner\\Validators\\Generated\\Email;

        // parity-class: manual-june

        use App\\DTO\\ScanResult;
        use App\\Services\\Scanner\\Validators\\Generated\\BaseGeneratedValidator;
        use Illuminate\\Support\\Facades\\Http;

        final class LuarocksValidator extends BaseGeneratedValidator
        {
            public function key(): string { return 'luarocks'; }
            public function category(): string { return 'dev'; }
            public function mode(): string { return 'email'; }
            public function siteName(): string { return 'Luarocks'; }
            public function siteUrl(): string { return 'https://luarocks.org'; }

            public function check(string $target, array $options = []): ScanResult
            {
                try {
                    $request = Http::timeout(15)->withOptions(['allow_redirects' => true, 'verify' => (bool) config('scanner.verify_ssl', false)]);
                    $init = $request->get('https://luarocks.org/login');
                    if ($init->status() !== 200) {
                        return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Failed to load validation frame: ' . $init->status(), mode: $this->mode(), key: $this->key());
                    }
                    if (!preg_match('/name="csrf_token" value="([^"]+)"/', $init->body(), $match)) {
                        return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Could not parse LuaRocks state CSRF token', mode: $this->mode(), key: $this->key());
                    }
                    $response = $request->asForm()->post('https://luarocks.org/user/forgot_password', [
                        'csrf_token' => $match[1],
                        'email' => $target,
                    ]);
                    $body = strtolower($response->body());
                    if (str_contains($body, 'password reset link has been sent')) {
                        return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', mode: $this->mode(), key: $this->key());
                    }
                    if (str_contains($body, "don't know anyone") || str_contains($body, 'don&#39;t know anyone') || str_contains($body, 'know anyone with that email')) {
                        return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', mode: $this->mode(), key: $this->key());
                    }
                    return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected target response markup signature', mode: $this->mode(), key: $this->key());
                } catch (\\Throwable $e) {
                    return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $e->getMessage(), mode: $this->mode(), key: $this->key());
                }
            }
        }
        """
    ).strip() + "\n",
    'girlslife': dedent(
        """
        <?php

        declare(strict_types=1);

        namespace App\\Services\\Scanner\\Validators\\Generated\\Email;

        // parity-class: manual-june

        use App\\DTO\\ScanResult;
        use App\\Services\\Scanner\\Validators\\Generated\\BaseGeneratedValidator;
        use Illuminate\\Support\\Facades\\Http;

        final class GirlslifeValidator extends BaseGeneratedValidator
        {
            public function key(): string { return 'girlslife'; }
            public function category(): string { return 'entertainment'; }
            public function mode(): string { return 'email'; }
            public function siteName(): string { return 'Girlslife'; }
            public function siteUrl(): string { return 'https://girlslife.com/register/'; }

            public function check(string $target, array $options = []): ScanResult
            {
                try {
                    $request = Http::timeout(10)->withOptions(['allow_redirects' => true, 'verify' => (bool) config('scanner.verify_ssl', false)]);
                    $init = $request->get('https://girlslife.com/register/');
                    if ($init->status() !== 200) {
                        return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Failed to load register page: ' . $init->status(), mode: $this->mode(), key: $this->key());
                    }
                    if (!preg_match('/name="_wpnonce" value="([^"]+)"/', $init->body(), $match)) {
                        return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Failed to parse response', mode: $this->mode(), key: $this->key());
                    }
                    $response = $request->asForm()->post('https://girlslife.com/register/', [
                        'user_email-291233' => $target,
                        'user_password-291233' => '',
                        'confirm_user_password-291233' => '',
                        'birth_date-291233' => '',
                        'streetaddress-291233' => 'Miami-1',
                        'zip_code-291233' => '6281',
                        'form_id' => '291233',
                        'um_request' => '',
                        '_wpnonce' => $match[1],
                        '_wp_http_referer' => '/register/',
                    ]);
                    if ($response->status() === 403) {
                        return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Caught by Cloudflare/WAF (403)', mode: $this->mode(), key: $this->key());
                    }
                    $body = $response->body();
                    if (str_contains($body, 'Password is required') && !str_contains($body, 'The email you entered is incorrect')) {
                        return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', mode: $this->mode(), key: $this->key());
                    }
                    if (str_contains($body, 'The email you entered is incorrect')) {
                        return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', mode: $this->mode(), key: $this->key());
                    }
                    return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected response pattern', mode: $this->mode(), key: $this->key());
                } catch (\\Throwable $e) {
                    return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $e->getMessage(), mode: $this->mode(), key: $this->key());
                }
            }
        }
        """
    ).strip() + "\n",
    'screener': dedent(
        """
        <?php

        declare(strict_types=1);

        namespace App\\Services\\Scanner\\Validators\\Generated\\Email;

        // parity-class: manual-june

        use App\\DTO\\ScanResult;
        use App\\Services\\Scanner\\Validators\\Generated\\BaseGeneratedValidator;
        use Illuminate\\Support\\Facades\\Http;

        final class ScreenerValidator extends BaseGeneratedValidator
        {
            public function key(): string { return 'screener'; }
            public function category(): string { return 'other'; }
            public function mode(): string { return 'email'; }
            public function siteName(): string { return 'Screener'; }
            public function siteUrl(): string { return 'https://www.screener.in'; }

            public function check(string $target, array $options = []): ScanResult
            {
                try {
                    $request = Http::timeout(6)->withOptions(['allow_redirects' => true, 'verify' => (bool) config('scanner.verify_ssl', false)]);
                    $init = $request->get('https://www.screener.in/register/');
                    if ($init->status() === 403) {
                        return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Caught by WAF or IP Block (403)', mode: $this->mode(), key: $this->key());
                    }
                    if ($init->status() === 429) {
                        return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Rate limited by Screener (429)', mode: $this->mode(), key: $this->key());
                    }
                    if ($init->status() !== 200) {
                        return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'HTTP Error: ' . $init->status(), mode: $this->mode(), key: $this->key());
                    }
                    if (!preg_match('/name="csrfmiddlewaretoken" value="([^"]+)"/', $init->body(), $csrf) || !preg_match('/name="token" value="([^"]+)"/', $init->body(), $token)) {
                        return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected response body structure, report it via GitHub issues', mode: $this->mode(), key: $this->key());
                    }
                    $response = $request->asForm()->post('https://www.screener.in/register/', [
                        'csrfmiddlewaretoken' => $csrf[1],
                        'next' => '',
                        'token' => $token[1],
                        'email' => $target,
                        'email2' => $target,
                        'password' => '',
                    ]);
                    if ($response->status() === 403) {
                        return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Caught by WAF or IP Block (403)', mode: $this->mode(), key: $this->key());
                    }
                    if ($response->status() === 429) {
                        return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Rate limited by Screener (429)', mode: $this->mode(), key: $this->key());
                    }
                    if ($response->status() !== 200) {
                        return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'HTTP Error: ' . $response->status(), mode: $this->mode(), key: $this->key());
                    }
                    $body = $response->body();
                    if (str_contains($body, 'User account with this Email already exists')) {
                        return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', mode: $this->mode(), key: $this->key());
                    }
                    if (str_contains($body, '<ul class="errorlist"><li>This field is required.</li>')) {
                        return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', mode: $this->mode(), key: $this->key());
                    }
                    return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected response body structure, report it via GitHub issues', mode: $this->mode(), key: $this->key());
                } catch (\\Throwable $e) {
                    return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $e->getMessage(), mode: $this->mode(), key: $this->key());
                }
            }
        }
        """
    ).strip() + "\n",
}

EMAIL_FIXTURES = {
    'buymeacoffee': [
        ([{'status': 200, 'json': {'otp_login': True}}], 'Registered'),
        ([{'status': 422, 'json': {'message': 'No account with the given email'}}], 'Not Registered'),
    ],
    'kick': [
        ([{'status': 422, 'json': {'errors': {'email': ['has already been taken']}}}], 'Registered'),
        ([{'status': 204, 'body': ''}], 'Not Registered'),
    ],
    'hackerearth': [
        ([{'status': 200, 'json': {'errors': {'email': 'already registered'}}}], 'Registered'),
        ([{'status': 200, 'json': {'errors': {'password': 'required'}}}], 'Not Registered'),
    ],
    'hackerrank': [
        ([{'status': 200, 'json': {'status': False, 'internal_status_code': 'already_registered'}}], 'Registered'),
        ([{'status': 200, 'json': {'status': True}}], 'Not Registered'),
    ],
    'bunny': [
        ([{'status': 200, 'json': {'Message': 'already in use', 'Field': 'Email'}}], 'Registered'),
        ([{'status': 200, 'json': {'Message': 'Passwords must have 1 number'}}], 'Not Registered'),
    ],
    'ama': [
        ([{'status': 200, 'json': {'httpCode': '200', 'message': 'sent successfully'}}], 'Registered'),
        ([{'status': 200, 'json': {'httpCode': '202', 'message': 'not found'}}], 'Not Registered'),
    ],
    'etsy': [
        ([{'status': 200, 'json': {'user_id': 1}}], 'Registered'),
        ([{'status': 200, 'body': 'null'}], 'Not Registered'),
    ],
    'nykaaman': [
        ([{'status': 200, 'json': {'response': {'is_exists': True}}}], 'Registered'),
        ([{'status': 200, 'json': {'response': {'is_exists': False}}}], 'Not Registered'),
    ],
    'rubygems': [
        ([{'status': 200, 'body': '<input name="authenticity_token" value="abc">'}, {'status': 200, 'body': 'has already been taken'}], 'Registered'),
        ([{'status': 200, 'body': '<input name="authenticity_token" value="abc">'}, {'status': 200, 'body': "Password can't be blank"}], 'Not Registered'),
    ],
    'luarocks': [
        ([{'status': 200, 'body': '<input name="csrf_token" value="abc">'}, {'status': 200, 'body': 'password reset link has been sent'}], 'Registered'),
        ([{'status': 200, 'body': '<input name="csrf_token" value="abc">'}, {'status': 200, 'body': "don't know anyone with that email"}], 'Not Registered'),
    ],
    'girlslife': [
        ([{'status': 200, 'body': '<input name="_wpnonce" value="abc">'}, {'status': 200, 'body': 'The email you entered is incorrect'}], 'Registered'),
        ([{'status': 200, 'body': '<input name="_wpnonce" value="abc">'}, {'status': 200, 'body': 'Password is required'}], 'Not Registered'),
    ],
    'screener': [
        ([{'status': 200, 'body': '<input name="csrfmiddlewaretoken" value="abc"><input name="token" value="def">'}, {'status': 200, 'body': 'User account with this Email already exists'}], 'Registered'),
        ([{'status': 200, 'body': '<input name="csrfmiddlewaretoken" value="abc"><input name="token" value="def">'}, {'status': 200, 'body': '<ul class="errorlist"><li>This field is required.</li>'}], 'Not Registered'),
    ],
}


def write_fixture_file(cases: list[dict]) -> None:
    FIXTURE_PATH.parent.mkdir(parents=True, exist_ok=True)
    lines = ['<?php', '', 'return [']
    for case in cases:
        lines.extend([
            '    [',
            f"        'class' => '{php_escape(case['class'])}',",
            f"        'target' => '{php_escape(case['target'])}',",
            "        'responses' => [",
        ])
        for response in case['responses']:
            lines.append('            [')
            lines.append(f"                'status' => {response['status']},")
            if 'json' in response:
                lines.append(f"                'json' => {render_php_value(response['json'])},")
            else:
                lines.append(f"                'body' => '{php_escape(response.get('body', ''))}',")
            lines.append('            ],')
        lines.extend([
            '        ],',
            f"        'expected' => '{php_escape(case['expected'])}',",
            '    ],',
        ])
    lines.append('];')
    FIXTURE_PATH.write_text('\n'.join(lines) + '\n', encoding='utf-8')


def write_june_only_config(user_keys: list[str], email_keys: list[str]) -> None:
    lines = ['<?php', '', 'return [', "    'username' => ["]
    lines.extend(f"        '{php_escape(key)}'," for key in user_keys)
    lines.extend(['    ],', "    'email' => ["])
    lines.extend(f"        '{php_escape(key)}'," for key in email_keys)
    lines.extend(['    ],', '];'])
    JUNE_ONLY_CONFIG.write_text('\n'.join(lines) + '\n', encoding='utf-8')


def main() -> None:
    generator = load_generator_module()
    specs = generator.discover_specs()
    generator.sync_manual_sets(specs)

    april_user_keys = inventory_keys(APRIL, 'user_scan')
    april_email_keys = inventory_keys(APRIL, 'email_scan')
    june_only_user = [spec for spec in specs if spec.mode == 'username' and spec.key not in april_user_keys]
    june_only_email = [spec for spec in specs if spec.mode == 'email' and spec.key not in april_email_keys]

    missing_user = []
    missing_email = []
    for spec in june_only_user + june_only_email:
        mode_dir = 'User' if spec.mode == 'username' else 'Email'
        php_path = PHP_ROOT / mode_dir / f'{spec.class_name}.php'
        if php_path.exists():
            continue
        if spec.mode == 'username':
            missing_user.append(spec)
        else:
            missing_email.append(spec)

    fixture_cases: list[dict] = []
    user_manual_keys: list[str] = []

    for spec in june_only_user:
        if spec.key in SAFE_USER_KEYS:
            continue

        php_path = PHP_ROOT / 'User' / f'{spec.class_name}.php'
        if not php_path.exists():
            content = render_user_validator(spec, source_for(spec))
            php_path.write_text(content, encoding='utf-8')
            user_manual_keys.append(spec.key)

        class_name = f'App\\Services\\Scanner\\Validators\\Generated\\User\\{spec.class_name}'
        if spec.key == 'wikipedia':
            fixture_cases.extend([
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 200, 'json': {'query': {'users': [{'userid': 1}]}}}], 'expected': 'Found'},
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 200, 'json': {'query': {'users': [{'missing': ''}]}}}], 'expected': 'Not Found'},
            ])
            continue
        if spec.key == 'githubgist':
            fixture_cases.extend([
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 200, 'json': {'login': 'alice'}}], 'expected': 'Found'},
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 404, 'body': 'missing'}], 'expected': 'Not Found'},
            ])
            continue
        if spec.key == 'disqus':
            fixture_cases.extend([
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 200, 'json': {'response': {'id': '1'}}}], 'expected': 'Found'},
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 200, 'json': {}}], 'expected': 'Not Found'},
            ])
            continue
        if spec.key == 'fansly':
            fixture_cases.extend([
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 200, 'json': {'success': True, 'response': [{'id': 1}]}}], 'expected': 'Found'},
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 200, 'json': {'success': True, 'response': []}}], 'expected': 'Not Found'},
            ])
            continue
        if spec.key == 'mozilladiscourse':
            fixture_cases.extend([
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 200, 'json': {'user': {'name': 'Alice'}}}], 'expected': 'Found'},
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 404, 'body': 'missing'}], 'expected': 'Not Found'},
            ])
            continue
        if spec.key == 'warpcast':
            fixture_cases.extend([
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 200, 'json': {'result': {'user': {'fid': 1}}}}], 'expected': 'Found'},
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 404, 'body': 'missing'}], 'expected': 'Not Found'},
            ])
            continue
        if spec.key == 'keybase':
            fixture_cases.extend([
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 200, 'json': {'them': [{'profile': {'full_name': 'Alice'}}]}}], 'expected': 'Found'},
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 200, 'json': {'them': []}}], 'expected': 'Not Found'},
            ])
            continue
        if spec.key == 'pypi':
            fixture_cases.extend([
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 200, 'body': '<methodResponse><params><param><value><array><data><value><array><data><value><string>owner</string></value><value><string>pkg</string></value></data></array></value></data></array></value></param></params></methodResponse>'}], 'expected': 'Found'},
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 200, 'body': '<methodResponse><params><param><value><array><data></data></array></value></param></params></methodResponse>'}], 'expected': 'Not Found'},
            ])
            continue
        if spec.key == 'codeforces':
            fixture_cases.extend([
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 200, 'json': {'status': 'OK', 'result': [{'handle': 'alice'}]}}], 'expected': 'Found'},
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 400, 'json': {'status': 'FAILED'}}], 'expected': 'Not Found'},
            ])
            continue
        if spec.key == 'beatstars':
            fixture_cases.extend([
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 200, 'json': {'data': {'identifierAvailable': {'available': False}}}}], 'expected': 'Found'},
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 200, 'json': {'data': {'identifierAvailable': {'available': True}}}}], 'expected': 'Not Found'},
            ])
            continue
        if spec.key == 'niftygateway':
            fixture_cases.extend([
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 200, 'json': {'didSucceed': True, 'userProfileAndNifties': {'id': '1'}}}], 'expected': 'Found'},
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 400, 'json': {'didSucceed': False, 'errorType': 'not_found'}}], 'expected': 'Not Found'},
            ])
            continue
        if spec.key == 'duolingo':
            fixture_cases.extend([
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 200, 'json': {'users': [{'id': 1}]}}], 'expected': 'Found'},
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 200, 'json': {'users': []}}], 'expected': 'Not Found'},
            ])
            continue
        if spec.key == 'freelancer':
            fixture_cases.extend([
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 200, 'json': {'result': {'users': {'1': {'id': 1}}}}}], 'expected': 'Found'},
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 200, 'json': {'result': {'users': {}}}}], 'expected': 'Not Found'},
            ])
            continue
        if spec.key == 'paragraph':
            fixture_cases.extend([
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 200, 'json': {'id': '1', 'name': 'Alice'}}], 'expected': 'Found'},
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 200, 'json': {}}], 'expected': 'Not Found'},
            ])
            continue
        if spec.key == 'imgur':
            fixture_cases.extend([
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 200, 'json': {'id': 1, 'username': 'alice'}}], 'expected': 'Found'},
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 404, 'body': 'missing'}], 'expected': 'Not Found'},
            ])
            continue
        if spec.key == 'vivino':
            fixture_cases.extend([
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 200, 'json': {'id': 1, 'alias': 'alice'}}], 'expected': 'Found'},
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 200, 'json': {}}], 'expected': 'Not Found'},
            ])
            continue
        if spec.key == 'px500':
            fixture_cases.extend([
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 200, 'json': {'data': {'userByUsername': {'legacyId': 1}}}}], 'expected': 'Found'},
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 200, 'json': {'errors': [{'extensions': {'response': {'status': 404}}}], 'data': {'userByUsername': None}}}], 'expected': 'Not Found'},
            ])
            continue
        if spec.key == 'wordpress':
            fixture_cases.extend([
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 200, 'body': '<title>Alice User Profile</title><div class=\"user-name\">Alice</div>'}], 'expected': 'Found'},
                {'class': class_name, 'target': 'alice', 'responses': [{'status': 404, 'body': 'missing'}], 'expected': 'Not Found'},
            ])
            continue

        rules = generic_user_rules(source_for(spec))
        positive_body = rules['taken_needles'][0] if rules['taken_needles'] else 'profile ok'
        negative_body = rules['available_needles'][0] if rules['available_needles'] else 'missing profile'
        negative_status = 404 if 404 in rules['available_statuses'] else (rules['available_statuses'][0] if rules['available_statuses'] else 404)
        fixture_cases.extend([
            {'class': class_name, 'target': 'alice', 'responses': [{'status': 200, 'body': positive_body}], 'expected': 'Found'},
            {'class': class_name, 'target': 'alice', 'responses': [{'status': negative_status, 'body': negative_body}], 'expected': 'Not Found'},
        ])

    email_manual_keys: list[str] = []
    for spec in june_only_email:
        php_path = PHP_ROOT / 'Email' / f'{spec.class_name}.php'
        if not php_path.exists():
            if spec.key in EMAIL_SPECS:
                content = EMAIL_SPECS[spec.key]
            elif spec.key in EMAIL_HANDSHAKE:
                content = EMAIL_HANDSHAKE[spec.key]
            else:
                raise RuntimeError(f'No June email template defined for {spec.key}')
            php_path.write_text(content, encoding='utf-8')
            email_manual_keys.append(spec.key)

        class_name = f'App\\Services\\Scanner\\Validators\\Generated\\Email\\{spec.class_name}'
        for responses, expected in EMAIL_FIXTURES[spec.key]:
            fixture_cases.append({
                'class': class_name,
                'target': 'jane@example.com',
                'responses': responses,
                'expected': expected,
            })

    write_fixture_file(fixture_cases)
    write_june_only_config(
        sorted(spec.key for spec in june_only_user),
        sorted(spec.key for spec in june_only_email),
    )

    print(json.dumps({
        'user_manual_written': len(user_manual_keys),
        'email_manual_written': len(email_manual_keys),
        'fixture_cases': len(fixture_cases),
    }, indent=2))


if __name__ == '__main__':
    main()
