#!/usr/bin/env python3
from __future__ import annotations

import re
import subprocess
from dataclasses import dataclass
from pathlib import Path
from urllib.parse import quote_plus

ROOT = Path(__file__).resolve().parents[1]
GEN_DIR = ROOT / "app/Services/Scanner/Validators/Generated"
MATRIX = ROOT / "docs/connector_validation_matrix.md"


@dataclass
class Connector:
    key: str
    category: str
    mode: str
    method: str
    url_tpl: str
    available_statuses: list[int]
    taken_statuses: list[int]
    available_indicators: list[str]
    taken_indicators: list[str]


def capture(pattern: str, text: str, default: str = "") -> str:
    m = re.search(pattern, text, re.S)
    return m.group(1) if m else default


def parse_array(name: str, text: str) -> list[str]:
    raw = capture(rf"\${name}\s*=\s*\[(.*?)\];", text)
    if not raw:
        return []
    vals: list[str] = []
    for part in raw.split(","):
        part = part.strip()
        if not part:
            continue
        if part.startswith("'") and part.endswith("'"):
            vals.append(part[1:-1])
        elif part.startswith('"') and part.endswith('"'):
            vals.append(part[1:-1])
        elif part.isdigit():
            vals.append(part)
    return vals


def load_connectors() -> list[Connector]:
    connectors: list[Connector] = []
    for file in sorted(GEN_DIR.rglob("*Validator.php")):
        if file.name == "BaseGeneratedValidator.php":
            continue
        s = file.read_text()
        key = capture(r"public function key\(\): string\s*\{\s*return '([^']+)'", s)
        cat = capture(r"public function category\(\): string\s*\{\s*return '([^']+)'", s)
        mode = capture(r"public function mode\(\): string\s*\{\s*return '([^']+)'", s)
        method = capture(r"protected function requestMethod\(\): string\s*\{\s*return '([^']+)'", s, "GET").upper()
        url_tpl = capture(r"protected function requestUrl\(string \$target\): string\s*\{\s*return \"([\s\S]*?)\";", s)
        if not url_tpl:
            url_tpl = capture(r"protected function requestUrl\(string \$target\): string\s*\{\s*return '([\s\S]*?)';", s)

        available_statuses = [int(v) for v in parse_array("availableStatuses", s) if v.isdigit()]
        taken_statuses = [int(v) for v in parse_array("takenStatuses", s) if v.isdigit()]
        available_indicators = [v.lower() for v in parse_array("availableIndicators", s)]
        taken_indicators = [v.lower() for v in parse_array("takenIndicators", s)]

        connectors.append(
            Connector(
                key=key,
                category=cat,
                mode=mode,
                method=method,
                url_tpl=url_tpl,
                available_statuses=available_statuses,
                taken_statuses=taken_statuses,
                available_indicators=available_indicators,
                taken_indicators=taken_indicators,
            )
        )
    return connectors


def map_result(c: Connector, http_code: int, body: str, curl_err: bool) -> tuple[str, str, str]:
    b = body.lower()
    if curl_err:
        return ("network_error", "Error", "curl transport failure")
    if http_code in (401, 403, 429):
        return ("provider_blocked", "Error", f"blocked/rate-limited (HTTP {http_code})")
    if any(k in b for k in ["captcha", "challenge", "verify you are human", "cloudflare", "bot check"]):
        return ("provider_blocked", "Error", "anti-bot challenge detected")
    if any(k in b for k in ["csrf", "authenticity_token", "x-csrf-token", "token required", "invalid token"]) and http_code >= 400:
        return ("provider_blocked", "Error", "token/bootstrap extraction failure")

    if c.mode == "username":
        if http_code in c.available_statuses or any(k and k in b for k in c.available_indicators):
            return ("pass", "Available", "mapped from provider response")
        if http_code in c.taken_statuses or any(k and k in b for k in c.taken_indicators):
            return ("pass", "Taken", "mapped from provider response")
    else:
        if http_code in c.taken_statuses or any(k and k in b for k in c.taken_indicators):
            return ("pass", "Registered", "mapped from provider response")
        if http_code in c.available_statuses or any(k and k in b for k in c.available_indicators):
            return ("pass", "Not Registered", "mapped from provider response")

    return ("non_success", "Error", f"indeterminate response (HTTP {http_code})")


def run_probe(c: Connector) -> tuple[int, str, str, str]:
    target = "codex_probe_user_12345" if c.mode == "username" else "probe_codex_12345@example.com"
    enc = quote_plus(target)
    url = c.url_tpl.replace("{$target}", enc).replace("$target", enc)

    cmd = ["curl", "-sS", "-o", "/tmp/connector_smoke_body.txt", "-w", "%{http_code}", "--max-time", "10", "-X", c.method, url]
    if c.method != "GET":
        field = "username" if c.mode == "username" else "email"
        cmd += ["-H", "Content-Type: application/x-www-form-urlencoded", "--data", f"{field}={enc}"]

    try:
        out = subprocess.check_output(cmd, stderr=subprocess.STDOUT, text=True).strip()
        code = int(out[-3:]) if out[-3:].isdigit() else 0
        body = Path("/tmp/connector_smoke_body.txt").read_text(errors="ignore")
        smoke_status, parsed_status, reason = map_result(c, code, body, curl_err=False)
        return code, smoke_status, parsed_status, reason
    except subprocess.CalledProcessError:
        smoke_status, parsed_status, reason = map_result(c, 0, "", curl_err=True)
        return 0, smoke_status, parsed_status, reason


def main() -> None:
    rows = []
    for c in load_connectors():
        code, smoke_status, parsed_status, reason = run_probe(c)
        target = "codex_probe_user_12345" if c.mode == "username" else "probe_codex_12345@example.com"
        endpoint = c.url_tpl.replace("{$target}", target).replace("$target", target)
        rows.append((c.key, c.category, c.mode, c.method, endpoint, "provider_specific", smoke_status, parsed_status, reason, str(code).zfill(3)))

    lines = [
        "# Connector Validation Matrix",
        "",
        "Generated from `python laravel_app/scripts/run_connector_smoke.py`. Outcomes are transport-level smoke checks and parser-mapping checks for synthetic targets.",
        "",
        "| key | category | mode | method | endpoint | parity_status | smoke_status | parsed_status | reason | http_code |",
        "|---|---|---|---|---|---|---|---|---|---|",
    ]

    for r in rows:
        endpoint = r[4].replace("|", "%7C")
        reason = r[8].replace("|", "%7C")
        lines.append(f"| {r[0]} | {r[1]} | {r[2]} | {r[3]} | {endpoint} | {r[5]} | {r[6]} | {r[7]} | {reason} | {r[9]} |")

    MATRIX.write_text("\n".join(lines) + "\n")
    print(f"wrote {len(rows)} connector rows to {MATRIX}")


if __name__ == "__main__":
    main()
