from __future__ import annotations

import argparse
import json
import re
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
SOURCE_ROOT = ROOT / "user-scanner-py-sept-release" / "user_scanner" / "user_scan"
OUTPUT = ROOT / "config" / "scanner_september_username.php"
VALIDATOR_ROOT = ROOT / "app" / "Services" / "Scanner" / "Validators"

FAMILY_MARKERS = {
    "discourse": "Discourse 404 response missing expected error payload",
    "gitea": "Gitea 404 response missing expected error payload",
    "lemmy": "Lemmy 404 response missing expected error payload",
    "generic_profile": "Profile page returned 200 but target username was not found",
}

# These modules build their URLs with quoted expressions, computed subdomains,
# query strings, or API payloads that cannot be recovered safely by the small
# static extractor below.  Keep the transport contract explicit instead of
# guessing from whichever string literal happens to appear first in the file.
MANUAL_MODULES: dict[str, dict[str, object]] = {
    "iha": {"url": "https://www.iha.ee/users/{target}"},
    "youporn": {"url": "https://www.youporn.com/pornstar/{target}/"},
    "pornhub": {
        "family": "html_lookup",
        "url": "https://www.pornhub.com/users/{target}",
    },
    "quora": {"url": "https://www.quora.com/profile/{target}"},
    "lesswrong": {
        "family": "api_lookup",
        "url": "https://www.lesswrong.com/graphql",
        "site_url": "https://www.lesswrong.com/users/{target}",
        "method": "POST",
        "body_mode": "json",
        "body": {"query": "{ user(input: {selector: {slug: \"{target}\"}}) { result { _id displayName slug karma bio createdAt } } }"},
        "headers": {"Accept": "application/json", "Content-Type": "application/json"},
    },
    "fandom": {
        "family": "api_lookup",
        "url": "https://community.fandom.com/api.php",
        "site_url": "https://community.fandom.com/wiki/User:{target}",
        "query": {
            "action": "query",
            "list": "users",
            "ususers": "{target}",
            "usprop": "registration|gender|editcount|groups",
            "format": "json",
        },
        "headers": {"Accept": "application/json"},
    },
    "fredmiranda": {
        "url": "https://www.fredmiranda.com/forum/viewprofile.php",
        "site_url": "https://www.fredmiranda.com/forum/viewprofile.php?Action=viewprofile&username={target}",
        "query": {"Action": "viewprofile", "username": "{target}"},
    },
    "beacons": {"url": "https://beacons.ai/{target}"},
    "directme": {"url": "https://direct.me/{target}"},
    "indiegogo": {"url": "https://www.indiegogo.com/en/profile/{target}"},
    "indiegogo_creator": {"url": "https://www.indiegogo.com/en/creators/{target}"},
    "kickstarter": {
        "url": "https://www.kickstarter.com/profile/{target}/about",
        "site_url": "https://www.kickstarter.com/profile/{target}",
    },
    "leanpub": {"url": "https://leanpub.com/u/{target}"},
    "luma": {"url": "https://luma.com/user/{target}"},
    "odysee": {
        "family": "html_lookup",
        "url": "https://odysee.com/@{target}",
    },
    "payhip": {"url": "https://payhip.com/{target}"},
    "rumble": {"url": "https://rumble.com/user/{target}"},
    "rumble_channel": {"url": "https://rumble.com/c/{target}"},
    "threadless_shop": {"url": "https://{target}.threadless.com/"},
    "amoremi": {"url": "https://www.amoremi.ee/user/{target}"},
    "neanky": {
        "family": "neanky",
        "url": "https://neanky.ee/p/register",
        "site_url": "https://neanky.ee/user/{target}",
        "method": "POST",
        "body": {"username": "{target}", "type": "validateusername"},
    },
    "svenvaher": {"url": "https://svenvaher.ee/{target}"},
    "tutvused": {"url": "https://tutvused.net/vendor/{target}/"},
    "livejasmin": {
        "family": "html_lookup",
        "url": "https://www.livejasmin.com/en/chat/{target}",
    },
    "bugcrowd": {
        "family": "bugcrowd",
        "url": "https://bugcrowd.com/profile-service/v1/profiles/{target}",
        "site_url": "https://bugcrowd.com/h/{target}",
    },
    "codepen": {"url": "https://codepen.io/{target}"},
    "codepenteams": {"url": "https://codepen.io/team/{target}"},
    "devhunt": {
        "family": "devhunt",
        "url": "https://xpdhqqwgprlqmqaqmnyx.supabase.co/rest/v1/profiles",
        "site_url": "https://devhunt.org/@{target}",
        "query": {
            "username": "eq.{target}",
            "select": "id,username,full_name,avatar_url,website_url,headline,about,social_url,updated_at,products(name)",
            "products.deleted": "eq.false",
        },
        "headers": {
            "Accept": "application/json",
            "apikey": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhwZGhxcXdncHJscW1xYXFtbnl4Iiwicm9sZSI6ImFub24iLCJpYXQiOjE2ODQ4NTg0ODcsImV4cCI6MjAwMDQzNDQ4N30.fwN6a_NzygrFxhj0GCxGnJJpHv8q8iNEjY1jvhL8Kv0",
        },
    },
    "civitai": {
        "family": "api_lookup",
        "url": "https://civitai.com/api/v1/creators",
        "site_url": "https://civitai.com/user/{target}",
        "query": {"query": "{target}"},
        "headers": {"Accept": "application/json"},
    },
    "medal": {
        "family": "medal",
        "url": "https://medal.tv/api/users/username",
        "site_url": "https://medal.tv/u/{target}",
        "method": "POST",
        "body_mode": "json",
        "body": {"username": "{target}"},
    },
    "nexusmods": {"url": "https://www.nexusmods.com/profile/{target}"},
    "stackb": {"url": "https://stackb.net/@{target}"},
    "weblancer": {"url": "https://www.weblancer.net/users/{target}/"},
    "dblp": {
        "family": "api_lookup",
        "url": "https://dblp.org/search/author/api",
        "site_url": "https://dblp.org/search/author?q={target}",
        "query": {"q": "{target}", "format": "json"},
        "headers": {"Accept": "application/json"},
    },
    "openalex": {
        "family": "api_lookup",
        "url": "https://api.openalex.org/authors",
        "site_url": "https://openalex.org/authors?search={target}",
        "query": {"filter": "display_name.search:{target}"},
        "headers": {"Accept": "application/json"},
    },
    "orcid": {
        "family": "api_lookup",
        "url": "https://pub.orcid.org/v3.0/expanded-search/",
        "site_url": "https://orcid.org/orcid-search/search?searchQuery={target}",
        "query": {"q": "{target}", "start": "0", "rows": "5"},
        "headers": {"Accept": "application/json"},
    },
    "pedsovet": {
        "family": "html_lookup",
        "url": "https://pedsovet.su/index/8-0-{target}",
    },
    "andelemandele": {"url": "https://www.andelemandele.lv/{target}"},
    "osta": {
        "url": "https://www.osta.ee/en?fuseaction=listing.seller&q%5Bseller%5D={target}",
    },
    "tindie": {"url": "https://www.tindie.com/stores/{target}/"},
    "polarsteps": {
        "family": "api_lookup",
        "url": "https://api.polarsteps.com/users/byusername/{target}",
        "site_url": "https://www.polarsteps.com/{target}",
        "headers": {"Accept": "application/json", "Polarsteps-API-Version": "61"},
    },
    "yaga_co_za": {"url": "https://www.yaga.co.za/{target}"},
    "yaga_ee": {"url": "https://www.yaga.ee/{target}"},
    "characterai": {
        "family": "characterai",
        "url": "https://character.ai/api/trpc/social.publicProfile",
        "site_url": "https://character.ai/profile/{target}",
        "query": {"input": "{\"json\":{\"username\":\"{target}\"}}"},
    },
    "buicha": {
        "family": "api_lookup",
        "url": "https://buicha.social/api/users/show",
        "site_url": "https://buicha.social/@{target}",
        "method": "POST",
        "body_mode": "json",
        "body": {"username": "{target}"},
        "headers": {"Accept": "application/json", "Content-Type": "application/json"},
    },
    "kitsu": {
        "family": "api_lookup",
        "url": "https://kitsu.app/api/edge/users",
        "site_url": "https://kitsu.app/users/{target}",
        "query": {"filter[name]": "{target}"},
        "headers": {"Accept": "application/vnd.api+json"},
    },
    "memory_lol": {
        "family": "api_lookup",
        "url": "https://api.memory.lol/v1/tw/{target}",
        "site_url": "https://memory.lol/tw/{target}",
        "headers": {"Accept": "application/json"},
    },
    "taplink": {
        "family": "html_lookup",
        "url": "https://taplink.cc/{target}",
    },
    "allthelyrics": {
        "family": "html_lookup",
        "url": "https://www.allthelyrics.com/forum/member.php",
        "site_url": "https://www.allthelyrics.com/forum/members/{target}.html",
        "query": {"username": "{target}"},
    },
    "clubhouse": {"url": "https://www.clubhouse.com/@{target}"},
    "linkedin_company": {"url": "https://www.linkedin.com/company/{target}"},
    "matrix": {
        "family": "matrix",
        "url": "https://matrix.org/_matrix/client/v3/profile/@{target}:matrix.org",
        "site_url": "https://matrix.to/#/@{target}:matrix.org",
        "headers": {"Accept": "application/json"},
    },
    "annaabi": {
        "family": "annaabi",
        "url": "https://annaabi.ee/register.php",
        "site_url": "https://annaabi.ee/?username={target}",
        "query": {"go": "1", "usernamecheck": "{target}"},
    },
    "defensivecarry": {
        "family": "xenforo_search",
        "url": "https://www.defensivecarry.com/members/",
        "site_url": "https://www.defensivecarry.com/members/?username={target}",
        "query": {"username": "{target}"},
        "follow_redirects": False,
    },
    "thefirearmsforum": {
        "family": "xenforo_search",
        "url": "https://www.thefirearmsforum.com/members/",
        "site_url": "https://www.thefirearmsforum.com/members/?username={target}",
        "query": {"username": "{target}"},
        "follow_redirects": False,
    },
}


def runtime_keys() -> set[str]:
    keys: set[str] = set()
    for directory in (
        VALIDATOR_ROOT / "User",
        VALIDATOR_ROOT / "Generated" / "User",
        VALIDATOR_ROOT / "Generated" / "Manual" / "User",
    ):
        if not directory.is_dir():
            continue
        for path in directory.glob("*Validator.php"):
            match = re.search(
                r"function\s+key\(\):\s*string\s*\{\s*return\s+'([^']+)'",
                path.read_text(encoding="utf-8"),
                re.DOTALL,
            )
            if match:
                keys.add(match.group(1))
    return keys


def php_string(value: str) -> str:
    return "'" + value.replace("\\", "\\\\").replace("'", "\\'") + "'"


def module_key(path: Path) -> str:
    return path.stem.lower().replace(".", "_")


def site_name(key: str) -> str:
    return " ".join(part.capitalize() for part in key.split("_"))


def string_constants(source: str) -> dict[str, str]:
    constants: dict[str, str] = {}
    pattern = re.compile(r"^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*f?([\"'])(.*?)\2\s*$", re.MULTILINE)
    for match in pattern.finditer(source):
        constants[match.group(1)] = match.group(3)
    return constants


def assignment(source: str, name: str, constants: dict[str, str]) -> str:
    match = re.search(
        rf"^\s*{re.escape(name)}\s*=\s*f?([\"'])(.*?)\1\s*$",
        source,
        re.MULTILINE,
    )
    if not match:
        raise ValueError(f"missing {name} assignment")

    value = resolve_constants(match.group(2), constants)
    for placeholder in ("encoded_user", "user", "username"):
        value = value.replace("{" + placeholder + "}", "{target}")
    return value


def resolve_constants(value: str, constants: dict[str, str]) -> str:
    for _ in range(5):
        previous = value
        for constant, replacement in constants.items():
            if replacement != value:
                value = value.replace("{" + constant + "}", replacement)
        if value == previous:
            break
    return value


def best_url(source: str, constants: dict[str, str], public: bool = False) -> str:
    names = (
        ("show_url", "profile_url", "url", "PROFILE_URL", "BASE_URL", "SIGNUP_URL")
        if public
        else ("url", "profile_url", "PROFILE_URL", "API_URL", "ENDPOINT", "SIGNUP_URL", "show_url")
    )
    for name in names:
        try:
            value = assignment(source, name, constants)
        except ValueError:
            value = resolve_constants(constants.get(name, ""), constants)
            for placeholder in ("encoded_user", "user", "username"):
                value = value.replace("{" + placeholder + "}", "{target}")
        if value.startswith(("http://", "https://")):
            return value

    for value in constants.values():
        value = resolve_constants(value, constants)
        if value.startswith(("http://", "https://")) and any(
            "{" + placeholder + "}" in value for placeholder in ("encoded_user", "user", "username")
        ):
            for placeholder in ("encoded_user", "user", "username"):
                value = value.replace("{" + placeholder + "}", "{target}")
            return value

    raise ValueError("missing URL assignment")


def family_for(source: str) -> str | None:
    for family, marker in FAMILY_MARKERS.items():
        if marker in source:
            return family
    return "custom_profile"


def definition(path: Path, family: str) -> dict[str, object]:
    source = path.read_text(encoding="utf-8")
    constants = string_constants(source)
    key = module_key(path)
    manual = MANUAL_MODULES.get(key, {})
    request_url = str(manual.get("url") or best_url(source, constants))
    public_url = str(manual.get("site_url") or manual.get("url") or best_url(source, constants, public=True))
    if "{" in request_url.replace("{target}", "") or "{" in public_url.replace("{target}", ""):
        raise ValueError("unresolved URL placeholder")

    family = str(manual.get("family") or family)
    query: dict[str, str] = dict(manual.get("query") or ({"username": "{target}"} if family == "lemmy" else {}))
    accept = "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8" if family in {"generic_profile", "custom_profile", "xenforo_search"} else "application/json"
    headers = {"Accept": accept, **dict(manual.get("headers") or {})}
    return {
        "key": key,
        "category": path.parent.name.lower(),
        "site_name": site_name(key),
        "site_url": public_url,
        "source": path.relative_to(SOURCE_ROOT.parent).as_posix(),
        "family": family,
        "request": {
            "method": str(manual.get("method") or "GET"),
            "url": request_url,
            "headers": headers,
            "query": query,
            "body": dict(manual.get("body") or {}),
            "body_mode": str(manual.get("body_mode") or "form"),
            "follow_redirects": bool(manual.get("follow_redirects", True)),
            "timeout": 10,
        },
    }


def discover() -> tuple[list[dict[str, object]], list[dict[str, str]]]:
    existing = runtime_keys()
    definitions: list[dict[str, object]] = []
    errors: list[dict[str, str]] = []
    for path in sorted(SOURCE_ROOT.glob("*/*.py")):
        if path.name == "__init__.py" or module_key(path) in existing:
            continue
        source = path.read_text(encoding="utf-8")
        family = family_for(source)
        if family is None:
            continue
        try:
            definitions.append(definition(path, family))
        except ValueError as error:
            errors.append({"source": str(path), "error": str(error)})
    return definitions, errors


def render_php(definitions: list[dict[str, object]]) -> str:
    lines = ["<?php", "", "declare(strict_types=1);", "", "return [", "    'modules' => ["]
    for item in definitions:
        request = item["request"]
        assert isinstance(request, dict)
        query = request["query"]
        assert isinstance(query, dict)
        query_php = "[" + ", ".join(f"{php_string(k)} => {php_string(v)}" for k, v in query.items()) + "]"
        body = request["body"]
        assert isinstance(body, dict)
        body_php = "[" + ", ".join(f"{php_string(k)} => {php_string(v)}" for k, v in body.items()) + "]"
        headers = request["headers"]
        assert isinstance(headers, dict)
        headers_php = "[" + ", ".join(f"{php_string(k)} => {php_string(v)}" for k, v in headers.items()) + "]"
        lines.extend(
            [
                "        [",
                f"            'key' => {php_string(str(item['key']))},",
                f"            'category' => {php_string(str(item['category']))},",
                f"            'site_name' => {php_string(str(item['site_name']))},",
                f"            'site_url' => {php_string(str(item['site_url']))},",
                f"            'source' => {php_string(str(item['source']))},",
                f"            'family' => {php_string(str(item['family']))},",
                "            'request' => [",
                f"                'method' => {php_string(str(request['method']))},",
                f"                'url' => {php_string(str(request['url']))},",
                f"                'headers' => {headers_php},",
                f"                'query' => {query_php},",
                f"                'body' => {body_php},",
                f"                'body_mode' => {php_string(str(request['body_mode']))},",
                f"                'follow_redirects' => {'true' if request['follow_redirects'] else 'false'},",
                "                'timeout' => 10,",
                "            ],",
                "        ],",
            ]
        )
    lines.extend(["    ],", "];", ""])
    return "\n".join(lines)


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--check", action="store_true")
    parser.add_argument("--allow-partial", action="store_true")
    args = parser.parse_args()

    definitions, errors = discover()
    family_counts: dict[str, int] = {}
    for item in definitions:
        family = str(item["family"])
        family_counts[family] = family_counts.get(family, 0) + 1

    report = {
        "definitions": len(definitions),
        "families": family_counts,
        "errors": errors,
    }
    if not args.check and (not errors or args.allow_partial):
        OUTPUT.write_text(render_php(definitions), encoding="utf-8")
    print(json.dumps(report, indent=2))
    if errors and not args.allow_partial:
        raise SystemExit(1)


if __name__ == "__main__":
    main()
