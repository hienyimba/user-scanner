from __future__ import annotations

import ast
import json
import re
from dataclasses import dataclass
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
PY_ROOT = ROOT / 'user-scanner-py' / 'user_scanner'
PHP_ROOT = ROOT / 'app' / 'Services' / 'Scanner' / 'Validators' / 'Generated'
CONFIG_FILE = ROOT / 'config' / 'scanner_generated.php'

EXPLICIT_MANUAL_USER_KEYS = {
    '7dach', 'about_me', 'advfn', 'admireme_vip', 'adultforum', 'adultism', 'airliners', 'albicla', 'ameblo',
    'americanthinker', 'anilist', 'anonup', 'apexlegends', 'archwiki', 'babepedia', 'bandcamp', 'battlenet',
    'bdsmlr', 'bdsmsingles', 'bentbox', 'bitchute', 'bluesky', 'chess_com', 'cups7', 'discord', 'donatello',
    'facebook', 'gumroad', 'hackernews', 'hamaha', 'instagram', 'liberapay', 'lichess', 'linkedin', 'linktree',
    'medium', 'minds',
    'minecraft', 'mix', 'monkeytype', 'osu', 'patreon', 'pinterest', 'producthunt', 'reddit',
    'roblox', 'soundcloud', 'stackoverflow', 'substack', 'threads', 'tiktok', 'twitch', 'zmarsa', 'zomato',
    'naturalnews', 'newamerica', 'vinted'
}
EXPLICIT_MANUAL_EMAIL_KEYS = {
    'classmates', 'facebook', 'instagram', 'mewe', 'pinterest', 'plurk', 'x',
    'fapfolder', 'letsporn', 'lovescape', 'made_porn', 'pornhub', 'superporn', 'thegay', 'xnxx', 'xvideos',
    'nextdoor', 'stackoverflow',
    'adobe', 'flickr', 'gumroad', 'patreon',
    'hubspot', 'insightly', 'zoho',
    'boot_dev', 'codecademy', 'codepen', 'codewars', 'devrant', 'envato', 'github', 'hackerone', 'hackthebox', 'howtogeek', 'huggingface', 'qiita', 'replit', 'saashub', 'wix', 'wondershare', 'wordpress', 'xda',
    'anilist', 'justwatch', 'letterboxd', 'myanimelist', 'nebula_tv', 'stremio',
    'fitnessblender', 'myfitnesspal',
    'addictinggames', 'chess_com', 'crazygames',
    'neocities', 'render',
    'freelancer',
    'alison', 'allen', 'coursera', 'duolingo', 'vedantu',
    'gaana', 'mixcloud', 'spotify',
    'aljazeera', 'bbc', 'cnn', 'foxnews', 'globaltimes', 'indiatimes', 'nytimes',
    'anydo', 'deviantart', 'firefox', 'moz',
    'amazon', 'naturabuy', 'vivino', 'walmart',
    'espn', 'nba', 'emirates', 'komoot', 'office365',
  }
MANUAL_USER_KEYS: set[str] = set(EXPLICIT_MANUAL_USER_KEYS)
MANUAL_EMAIL_KEYS: set[str] = set(EXPLICIT_MANUAL_EMAIL_KEYS)

STATUS_VALIDATE = re.compile(r'status_validate\((?P<args>[\s\S]*?)\)', re.M)
GENERIC_VALIDATE = re.compile(r'generic_validate\((?P<args>[\s\S]*?)\)', re.M)
SHOW_URL_RE = re.compile(r'show_url\s*=\s*f?["\']([^"\']+)["\']')
URL_RE = re.compile(r'url\s*=\s*f?["\']([^"\']+)["\']')
HEADERS_RE = re.compile(r'headers\s*=\s*\{(?P<body>[\s\S]*?)\n\}', re.M)
DICT_ITEM_RE = re.compile(r'["\']([^"\']+)["\']\s*:\s*(.+?)(?=,\n|\n\}|$)', re.M)
PARAMS_RE = re.compile(r'params\s*=\s*\{(?P<body>[\s\S]*?)\n\}', re.M)
PAYLOAD_RE = re.compile(r'(payload|data)\s*=\s*(\[[\s\S]*?\]|\{[\s\S]*?\})', re.M)
TIMEOUT_RE = re.compile(r'timeout\s*=\s*([0-9.]+)')
FOLLOW_REDIRECTS_RE = re.compile(r'follow_redirects\s*=\s*(True|False)')
ERROR_RETURN_RE = re.compile(r'return\s+Result\.error\((?P<arg>[^\)]*)\)')
INPUT_VALIDATION_RE = re.compile(r'^\s*(if\s+not\s+|if\s+[\w\.]+\.startswith|if\s+[\w\.]+\.endswith|if\s+[\w\.]+\.isdigit|if\s+!?)', re.M)


def studly(name: str) -> str:
    value = ''.join(part.capitalize() for part in name.replace('.', '_').split('_'))
    return 'Site' + value if value and value[0].isdigit() else value


def site_label(name: str) -> str:
    return ''.join(part.capitalize() for part in name.replace('.', '_').split('_'))


def php_escape(value: str) -> str:
    return value.replace('\\', '\\\\').replace("'", "\\'")


def php_array(data: dict[str, str]) -> str:
    if not data:
        return '[]'
    lines = ["["]
    for k, v in data.items():
        lines.append(f"            '{php_escape(k)}' => {v},")
    lines.append('        ]')
    return '\n'.join(lines)


def transform_value(value: str) -> str:
    value = value.strip().rstrip(',')
    if value.startswith(('"', "'")) and value.endswith(('"', "'")):
        return "'" + php_escape(value[1:-1]) + "'"
    if value in {'True', 'False'}:
        return value.lower()
    if value.replace('.', '', 1).isdigit():
        return value
    if value.startswith('f"') or value.startswith("f'"):
        inner = value[2:-1]
        inner = inner.replace('{user}', '{$target}').replace('{email}', '{$target}')
        return '"' + inner.replace('"', '\\"') + '"'
    return "'" + php_escape(value) + "'"


def parse_inline_dict(text: str, name: str) -> dict[str, str]:
    match = re.search(rf'{name}\s*=\s*\{{(?P<body>[\s\S]*?)\n\}}', text, re.M)
    if not match:
        return {}
    body = match.group('body')
    result: dict[str, str] = {}
    for key, value in DICT_ITEM_RE.findall(body):
        result[key] = transform_value(value)
    return result


def parse_validate_args(source: str, kind: str) -> dict[str, str]:
    regex = STATUS_VALIDATE if kind == 'status' else GENERIC_VALIDATE
    match = regex.search(source)
    if not match:
        return {}
    args_text = match.group('args')
    kwargs = dict(re.findall(r'(\w+)\s*=\s*([^,\n\)]+)', args_text))
    return {k: v.strip() for k, v in kwargs.items()}


def parse_statuses(raw: str | None) -> list[str]:
    if raw is None:
        return []
    raw = raw.strip()
    if raw.startswith('['):
        return [part.strip() for part in raw[1:-1].split(',') if part.strip()]
    return [raw]

@dataclass
class Rule:
    conditions: list[str]
    status: str
    reason: str | None = None

@dataclass
class ModuleSpec:
    mode: str
    category: str
    key: str
    python_path: Path
    class_name: str
    site_name: str
    site_url: str
    request_method: str
    request_url: str
    timeout: int
    follow_redirects: bool
    headers: dict[str, str]
    query: dict[str, str]
    body: str | None
    body_mode: str
    input_validation: list[tuple[str, str]]
    rules: list[Rule]
    error_fallback: str
    complexity: str


def extract_input_validation(source: str) -> list[tuple[str, str]]:
    lines = source.splitlines()
    results: list[tuple[str, str]] = []
    for idx, line in enumerate(lines):
        if 'Result.error(' not in '\n'.join(lines[max(0, idx): idx + 2]):
            continue
        if 'url =' in line:
            break
    pattern = re.compile(r'if\s+(?P<cond>.+?):\s*\n\s*return\s+Result\.error\((?P<msg>[^\)]*)\)', re.M)
    for m in pattern.finditer(source):
        cond = m.group('cond').strip()
        msg = m.group('msg').strip().strip('"\'')
        if 'response' in cond:
            continue
        if 'url' in cond:
            continue
        results.append((cond, msg))
    return results


def classify_manual(source: str, mode: str, key: str) -> bool:
    explicit = MANUAL_USER_KEYS if mode == 'username' else MANUAL_EMAIL_KEYS
    if key in explicit:
        return True
    manual_markers = [
        'AsyncClient', 'authenticity_token', 'client.get(', 'client.post(', 'response.json()',
        'json.loads', 'json.dumps', 'content=', 'http2=True', 'csrf', 'persistedQuery',
        'Result.error(exc', 'except json.JSONDecodeError', 'except (json.JSONDecodeError',
    ]
    if any(marker in source for marker in manual_markers):
        return True
    if 're.match' in source or 're.fullmatch' in source or 'len(user)' in source or 'len(email)' in source:
        return True
    if re.search(r'\buser\s*=\s*user\.', source) or re.search(r'\bemail\s*=\s*email\.', source):
        return True
    if 'response.text.lower()' in source or '.lower()' in source and 'profile:' in source:
        return True
    if 'response.json()' in source:
        return True
    return False


def extract_rules(source: str, mode: str) -> tuple[list[Rule], str] | None:
    process_match = re.search(r'def\s+process\(response\).*?:\n(?P<body>[\s\S]*?)\n\s*return\s+(?:generic_validate|status_validate)', source)
    if not process_match:
        return None
    body = process_match.group('body')
    rules: list[Rule] = []
    fallback = 'Unexpected response body'
    pattern = re.compile(r'if\s+(?P<cond>[^:]+):\s*\n\s*return\s+Result\.(?P<kind>taken|available|error)\((?P<arg>[^\)]*)\)', re.M)
    for m in pattern.finditer(body):
        cond = m.group('cond').strip()
        kind = m.group('kind')
        arg = m.group('arg').strip().strip('"\'')
        if kind == 'error':
            fallback = arg or fallback
            continue
        status = 'Taken' if mode == 'username' else 'Registered'
        if kind == 'available':
            status = 'Available' if mode == 'username' else 'Not Registered'
        conditions = [piece.strip() for piece in cond.split(' or ')]
        rules.append(Rule(conditions=conditions, status=status))
    return rules, fallback


def build_spec(py_file: Path, mode: str, category: str) -> ModuleSpec:
    key = py_file.stem.replace('.', '_').lower()
    class_name = studly(key) + 'Validator'
    source = py_file.read_text(encoding='utf-8')
    show_url = SHOW_URL_RE.search(source)
    url = URL_RE.search(source)
    validate_kind = 'status' if 'status_validate(' in source else 'generic'
    args = parse_validate_args(source, validate_kind)
    request_method = 'POST' if 'method="POST"' in source or "method='POST'" in source else 'GET'
    timeout = int(float(args.get('timeout', TIMEOUT_RE.search(source).group(1) if TIMEOUT_RE.search(source) else '10')))
    follow_redirects = args.get('follow_redirects', FOLLOW_REDIRECTS_RE.search(source).group(1) if FOLLOW_REDIRECTS_RE.search(source) else 'True') == 'True'
    headers = parse_inline_dict(source, 'headers')
    query = parse_inline_dict(source, 'params')
    body = None
    body_mode = 'form'
    payload_match = PAYLOAD_RE.search(source)
    if payload_match:
        body = payload_match.group(2).strip()
        if 'json=' in source:
            body_mode = 'json'
        elif 'content=' in source:
            body_mode = 'raw'
        else:
            body_mode = 'form'
    parsed = extract_rules(source, mode)
    rules, fallback = parsed if parsed else ([], 'Unexpected response body')
    complexity = 'manual' if classify_manual(source, mode, key) else 'generator-safe'
    return ModuleSpec(
        mode=mode,
        category=category,
        key=key,
        python_path=py_file,
        class_name=class_name,
        site_name=site_label(key),
        site_url=(show_url.group(1) if show_url else (url.group(1) if url else '')),
        request_method=request_method,
        request_url=(url.group(1) if url else ''),
        timeout=timeout,
        follow_redirects=follow_redirects,
        headers=headers,
        query=query,
        body=body,
        body_mode=body_mode,
        input_validation=extract_input_validation(source),
        rules=rules,
        error_fallback=fallback,
        complexity=complexity,
    )


def php_condition(cond: str) -> str:
    cond = cond.strip()
    cond = cond.replace('response.status_code', '$status')
    cond = cond.replace('response.text.lower()', 'strtolower($body)')
    cond = cond.replace('response.text', '$body')
    cond = re.sub(r'\b(?:data|html_text|html)\b', '$body', cond)
    cond = cond.replace('response.status_code ==', '$status ===')
    cond = cond.replace(' and ', ' && ').replace(' or ', ' || ')
    cond = re.sub(r'(["\'])(.+?)\1\s+in\s+(?:\$body|strtolower\(\$body\))', lambda m: f"str_contains($body, '{php_escape(m.group(2))}')", cond)
    cond = re.sub(r'f"([^"]*?)\{user\}([^"]*?)"\s+in\s+strtolower\(\$body\)', lambda m: f"str_contains(strtolower($body), '{php_escape(m.group(1).lower())}' . strtolower($target) . '{php_escape(m.group(2).lower())}')", cond)
    cond = re.sub(r'f"([^"]*?)\{user\}([^"]*?)"\s+in\s+\$body', lambda m: f"str_contains($body, '{php_escape(m.group(1))}' . $target . '{php_escape(m.group(2))}')", cond)
    return cond


def render_validation(spec: ModuleSpec) -> str:
    if not spec.input_validation:
        return ''
    lines = ["    public function check(string $target, array $options = []): ScanResult", '    {']
    for cond, msg in spec.input_validation:
        # lightweight translation for common validations
        php_cond = cond.replace('len(user)', 'strlen($target)').replace('len(email)', 'strlen($target)')
        php_cond = php_cond.replace('user', '$target').replace('email', '$target')
        php_cond = php_cond.replace(' and ', ' && ').replace(' or ', ' || ')
        php_cond = php_cond.replace('not re.match', '!preg_match').replace('not re.fullmatch', '!preg_match')
        php_cond = php_cond.replace('user.isdigit()', 'ctype_digit($target)').replace('email.isdigit()', 'ctype_digit($target)')
        if 'preg_match' in php_cond and ', $target)' not in php_cond:
            php_cond = re.sub(r'!preg_match\(([^\)]+)\)', r'!preg_match(\1, $target)', php_cond)
        lines.append(f"        if ({php_cond}) {{")
        lines.append(f"            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', '{php_escape(msg)}', mode: $this->mode(), key: $this->key());")
        lines.append('        }')
    lines.append('')
    lines.append('        return parent::check($target, $options);')
    lines.append('    }')
    return '\n'.join(lines)


def render_body(spec: ModuleSpec) -> str:
    if spec.body is None:
        return ''
    if spec.body_mode == 'raw':
        raw = spec.body.replace('{user}', '{$target}').replace('{email}', '{$target}')
        return f"\n    protected function requestRawBody(string $target): ?string\n    {{\n        return <<<'JSON'\n{raw}\nJSON;\n    }}"
    if spec.body_mode == 'json' and spec.body.startswith('['):
        return ''
    return ''


def render_rules(spec: ModuleSpec) -> str:
    lines = ["    protected function parseConnectorResponse(Response $response, string $target): array", '    {', '        $status = $response->status();', '        $body = $response->body();', '']
    for rule in spec.rules:
        joined = ' || '.join(php_condition(c) for c in rule.conditions)
        lines.append(f"        if ({joined}) {{")
        lines.append(f"            return ['{rule.status}', ''];")
        lines.append('        }')
        lines.append('')
    lines.append(f"        return ['Error', '{php_escape(spec.error_fallback)}'];")
    lines.append('    }')
    return '\n'.join(lines)


def render_generated_php(spec: ModuleSpec) -> str:
    namespace_mode = 'User' if spec.mode == 'username' else 'Email'
    imports = ['use App\\Services\\Scanner\\Validators\\Generated\\BaseGeneratedValidator;', 'use Illuminate\\Http\\Client\\Response;']
    if spec.input_validation:
        imports.insert(0, 'use App\\DTO\\ScanResult;')
    header_php = php_array(spec.headers)
    query_php = php_array(spec.query)
    validation_php = render_validation(spec)
    body_php = render_body(spec)
    return f"""<?php

declare(strict_types=1);

namespace App\\Services\\Scanner\\Validators\\Generated\\{namespace_mode};

// parity-source: {spec.python_path.as_posix()}
// parity-class: generated

{chr(10).join(imports)}

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
        return '{spec.mode}';
    }}

    public function siteName(): string
    {{
        return '{spec.site_name}';
    }}

    public function siteUrl(): string
    {{
        return '{php_escape(spec.site_url)}';
    }}

    protected function requestMethod(): string
    {{
        return '{spec.request_method}';
    }}

    protected function requestUrl(string $target): string
    {{
        return \"{spec.request_url.replace('{user}', '{$target}').replace('{email}', '{$target}').replace('"', '\\"')}\";
    }}

    protected function followRedirects(): bool
    {{
        return {'true' if spec.follow_redirects else 'false'};
    }}

    protected function timeoutSeconds(): int
    {{
        return {spec.timeout};
    }}

    protected function requestHeaders(): array
    {{
        return {header_php};
    }}

    protected function requestQuery(string $target): array
    {{
        return {query_php};
    }}{body_php}

{validation_php if validation_php else ''}
{render_rules(spec)}
}}
"""


def discover_specs() -> list[ModuleSpec]:
    specs: list[ModuleSpec] = []
    for mode, folder in (('username', 'user_scan'), ('email', 'email_scan')):
        for category_dir in sorted((PY_ROOT / folder).iterdir()):
            if not category_dir.is_dir() or category_dir.name.startswith('__'):
                continue
            for py_file in sorted(category_dir.glob('*.py')):
                if py_file.name == '__init__.py':
                    continue
                specs.append(build_spec(py_file, mode, category_dir.name.lower()))
    return specs


def sync_manual_sets(specs: list[ModuleSpec]) -> None:
    global MANUAL_USER_KEYS, MANUAL_EMAIL_KEYS
    for spec in specs:
        if spec.complexity == 'manual':
            if spec.mode == 'username':
                MANUAL_USER_KEYS.add(spec.key)
            else:
                MANUAL_EMAIL_KEYS.add(spec.key)


def write_validator(spec: ModuleSpec) -> None:
    namespace_mode = 'User' if spec.mode == 'username' else 'Email'
    out_dir = PHP_ROOT / namespace_mode
    out_dir.mkdir(parents=True, exist_ok=True)
    path = out_dir / f'{spec.class_name}.php'
    explicit_manual = MANUAL_USER_KEYS if spec.mode == 'username' else MANUAL_EMAIL_KEYS
    if spec.key in explicit_manual and path.exists():
        return
    path.write_text(render_generated_php(spec), encoding='utf-8')


def write_config() -> None:
    classes: list[str] = []
    for mode in ('Email', 'User'):
        for file in sorted((PHP_ROOT / mode).glob('*Validator.php')):
            classes.append(f'        App\\Services\\Scanner\\Validators\\Generated\\{mode}\\{file.stem}::class,')
    content = "<?php\n\nreturn [\n    'validators' => [\n" + '\n'.join(classes) + "\n    ],\n];\n"
    CONFIG_FILE.write_text(content, encoding='utf-8')


def main() -> None:
    specs = discover_specs()
    sync_manual_sets(specs)
    for spec in specs:
        write_validator(spec)
    write_config()
    report = {
        'manual_user_keys': sorted(MANUAL_USER_KEYS),
        'manual_email_keys': sorted(MANUAL_EMAIL_KEYS),
        'generated_count': len([s for s in specs if s.key not in (MANUAL_USER_KEYS if s.mode == 'username' else MANUAL_EMAIL_KEYS)]),
        'total': len(specs),
    }
    print(json.dumps(report, indent=2))


if __name__ == '__main__':
    main()
