from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PY_ROOT = ROOT / "user-scanner-py" / "user_scanner"
PHP_ROOT = ROOT / "app" / "Services" / "Scanner" / "Validators" / "Generated"
CONFIG_FILE = ROOT / "config" / "scanner_generated.php"

STATUS_DIRECT = re.compile(
    r"if\s+[^\n:]*status(?:_code)?\s*(?:==|in)\s*(\[[^\]]+\]|\d+)[\s\S]{0,180}?return\s+Result\.(available|taken)\(",
    re.I,
)
INDIRECT = re.compile(
    r"([\"'].*?[\"'])\s+in\s+\w+[\s\S]{0,180}?return\s+Result\.(available|taken)\(",
    re.I,
)
SHOW_URL = re.compile(r"show_url\s*=\s*f?[\"']([^\"']+)[\"']")
HEADERS = re.compile(r"headers\s*=\s*\{([\s\S]*?)\n\}", re.M)
HEADER_ITEM = re.compile(r"[\"']([^\"']+)[\"']\s*:\s*[\"']([^\"']*)[\"']")
POST_CALL = re.compile(r"client\.post\(\s*[\"']([^\"']+)[\"']")
GET_CALL = re.compile(r"client\.get\(\s*[\"']([^\"']+)[\"']")
METHOD_HINT = re.compile(r"generic_validate\([^\)]*method\s*=\s*[\"']post[\"']", re.I)
STATUS_VALIDATE = re.compile(
    r"status_validate\(\s*url\s*,\s*(?:available\s*=\s*)?(\[[^\]]+\]|\d+)\s*,\s*(?:taken\s*=\s*)?(\[[^\]]+\]|\d+)",
    re.I,
)
FOLLOW_REDIRECTS = re.compile(r"follow_redirects\s*=\s*(True|False)")
TIMEOUT = re.compile(r"timeout\s*=\s*([0-9.]+)")


def studly(name: str) -> str:
    value = "".join(part.capitalize() for part in name.replace(".", "_").split("_"))
    return "Site" + value if value and value[0].isdigit() else value


def site_label(name: str) -> str:
    return "".join(part.capitalize() for part in name.replace(".", "_").split("_"))


def php_escape(value: str) -> str:
    return value.replace("\\", "\\\\").replace("'", "\\'")


def php_list(raw: str) -> str:
    raw = raw.strip()
    if raw.startswith("[") and raw.endswith("]"):
        items = [item.strip().strip("'\"") for item in raw[1:-1].split(",") if item.strip()]
        return "[" + ", ".join(item for item in items) + "]"
    return "[" + raw.strip().strip("'\"") + "]"


def normalize_url(url: str) -> str:
    return (
        url.replace("{user}", "{$target}")
        .replace("{email}", "{$target}")
        .replace("{safe_user}", "{$target}")
        .replace("{target}", "{$target}")
    )


def detect_method(text: str) -> str:
    if POST_CALL.search(text) or METHOD_HINT.search(text):
        return "POST"
    return "GET"


def detect_request_url(text: str) -> str:
    for pattern in (POST_CALL, GET_CALL):
        match = pattern.search(text)
        if match:
            return normalize_url(match.group(1))

    show = SHOW_URL.search(text)
    if show:
        return normalize_url(show.group(1))

    url_match = re.search(r"url\s*=\s*f?[\"']([^\"']+)[\"']", text)
    return normalize_url(url_match.group(1)) if url_match else "https://example.com"


def detect_site_url(text: str, request_url: str) -> str:
    show = SHOW_URL.search(text)
    if show:
        clean = re.sub(r"\{\$target\}|\{[^}]+\}", "", normalize_url(show.group(1))).rstrip("/")
        return clean or request_url
    return re.sub(r"\{\$target\}|\{[^}]+\}", "", request_url).rstrip("/")


def detect_headers(text: str) -> list[tuple[str, str]]:
    block = HEADERS.search(text)
    if not block:
        return []
    return HEADER_ITEM.findall(block.group(1))


def detect_statuses(text: str) -> tuple[str, str]:
    match = STATUS_VALIDATE.search(text)
    if match:
        return php_list(match.group(1)), php_list(match.group(2))

    available = []
    taken = []
    for status, kind in STATUS_DIRECT.findall(text):
        value = php_list(status)
        if kind.lower() == "available":
            available.append(value[1:-1])
        else:
            taken.append(value[1:-1])

    return "[" + ", ".join(dict.fromkeys(filter(None, available))) + "]", "[" + ", ".join(dict.fromkeys(filter(None, taken))) + "]"


def detect_indicators(text: str) -> tuple[list[str], list[str]]:
    available = []
    taken = []
    for literal, kind in INDIRECT.findall(text):
        value = literal.strip("'\"")
        if value == "":
            continue
        if kind.lower() == "available":
            available.append(value.lower())
        else:
            taken.append(value.lower())
    return list(dict.fromkeys(available)), list(dict.fromkeys(taken))


def detect_follow_redirects(text: str) -> bool:
    match = FOLLOW_REDIRECTS.search(text)
    return match.group(1) == "True" if match else True


def detect_timeout(text: str) -> int:
    match = TIMEOUT.search(text)
    if not match:
        return 10
    return max(5, int(float(match.group(1))))


def build_validator(py_file: Path) -> tuple[str, str]:
    parts = py_file.relative_to(PY_ROOT).parts
    mode = "email" if parts[0] == "email_scan" else "username"
    category = parts[1].lower()
    key = py_file.stem.replace(".", "_").lower()
    class_name = studly(key) + "Validator"
    namespace_mode = "Email" if mode == "email" else "User"
    text = py_file.read_text(encoding="utf-8")
    request_url = detect_request_url(text)
    site_url = detect_site_url(text, request_url)
    method = detect_method(text)
    available_statuses, taken_statuses = detect_statuses(text)
    available_indicators, taken_indicators = detect_indicators(text)
    headers = detect_headers(text)
    follow_redirects = "true" if detect_follow_redirects(text) else "false"
    timeout = detect_timeout(text)

    headers_php = "\n".join(
        "            '{}' => '{}',".format(php_escape(name), php_escape(value)) for name, value in headers
    )
    if headers_php == "":
        headers_php = "            // No connector-specific headers inferred."

    indicator_list = lambda values: "[" + ", ".join("'{}'".format(php_escape(value)) for value in values) + "]"

    body = f"""<?php

declare(strict_types=1);

namespace App\\Services\\Scanner\\Validators\\Generated\\{namespace_mode};

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
        return '{mode}';
    }}

    public function siteName(): string
    {{
        return '{site_label(key)}';
    }}

    public function siteUrl(): string
    {{
        return '{site_url}';
    }}

    protected function requestMethod(): string
    {{
        return '{method}';
    }}

    protected function requestUrl(string $target): string
    {{
        return "{request_url}";
    }}

    protected function followRedirects(): bool
    {{
        return {follow_redirects};
    }}

    protected function timeoutSeconds(): int
    {{
        return {timeout};
    }}

    protected function requestHeaders(): array
    {{
        return [
{headers_php}
        ];
    }}

    /** @return array{{0:string,1:string}} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {{
        $status = $response->status();
        $body = strtolower($response->body());

        if (in_array($status, [401, 403, 429], true)) {{
            return ['Error', $this->key() . ': blocked/rate-limited (HTTP ' . $status . ')'];
        }}

        foreach (['captcha', 'challenge', 'verify you are human', 'cloudflare', 'bot check'] as $needle) {{
            if ($needle !== '' && str_contains($body, $needle)) {{
                return ['Error', $this->key() . ': anti-bot challenge detected'];
            }}
        }}

        $availableStatuses = {available_statuses};
        $takenStatuses = {taken_statuses};
        $availableIndicators = {indicator_list(available_indicators)};
        $takenIndicators = {indicator_list(taken_indicators)};

        if ($this->mode() === 'username') {{
            if (in_array($status, $availableStatuses, true)) {{
                return ['Available', ''];
            }}
            if (in_array($status, $takenStatuses, true)) {{
                return ['Taken', ''];
            }}
            foreach ($takenIndicators as $needle) {{
                if ($needle !== '' && str_contains($body, $needle)) {{
                    return ['Taken', ''];
                }}
            }}
            foreach ($availableIndicators as $needle) {{
                if ($needle !== '' && str_contains($body, $needle)) {{
                    return ['Available', ''];
                }}
            }}

            return ['Error', $this->key() . ': indeterminate username response (HTTP ' . $status . ')'];
        }}

        if (in_array($status, $takenStatuses, true)) {{
            return ['Registered', ''];
        }}
        if (in_array($status, $availableStatuses, true)) {{
            return ['Not Registered', ''];
        }}
        foreach ($takenIndicators as $needle) {{
            if ($needle !== '' && str_contains($body, $needle)) {{
                return ['Registered', ''];
            }}
        }}
        foreach ($availableIndicators as $needle) {{
            if ($needle !== '' && str_contains($body, $needle)) {{
                return ['Not Registered', ''];
            }}
        }}

        return ['Error', $this->key() . ': indeterminate email response (HTTP ' . $status . ')'];
    }}
}}
"""
    return class_name, body


def main() -> None:
    generated = []
    for old in PHP_ROOT.rglob("*Validator.php"):
        if old.name == "BaseGeneratedValidator.php":
            continue
        old.unlink()

    for base, mode_dir in (("user_scan", "User"), ("email_scan", "Email")):
        for py_file in sorted((PY_ROOT / base).rglob("*.py")):
            if py_file.name == "__init__.py":
                continue
            class_name, body = build_validator(py_file)
            target = PHP_ROOT / mode_dir / f"{class_name}.php"
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_text(body, encoding="utf-8")
            generated.append(f"        App\\Services\\Scanner\\Validators\\Generated\\{mode_dir}\\{class_name}::class,")

    generated = sorted(dict.fromkeys(generated))
    config = "<?php\n\nreturn [\n    'validators' => [\n" + "\n".join(generated) + "\n    ],\n];\n"
    CONFIG_FILE.write_text(config, encoding="utf-8")
    print(f"Generated {len(generated)} validator registrations.")


if __name__ == "__main__":
    main()
