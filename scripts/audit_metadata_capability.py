from __future__ import annotations

import json
import re
from collections import Counter
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
JUNE_ROOT = ROOT / "user-scanner-py-june-release" / "user_scanner"
SEPTEMBER_ROOT = ROOT / "user-scanner-py-sept-release" / "user_scanner"
ACTIVE_DIRS = [
    (JUNE_ROOT / "user_scan", JUNE_ROOT, "username"),
    (SEPTEMBER_ROOT / "email_scan", SEPTEMBER_ROOT, "email"),
]

RICH_SIGNAL_KEYS = {
    "name",
    "full_name",
    "real_name",
    "display_name",
    "bio",
    "location",
    "website",
    "email",
    "public_email",
    "followers",
    "following",
    "avatar",
    "avatar_url",
    "links",
    "social_links",
    "created_at",
    "joined",
    "registration",
}


def iter_modules() -> list[tuple[Path, Path, str]]:
    files: list[tuple[Path, Path, str]] = []
    for base, root, mode in ACTIVE_DIRS:
        files.extend(
            (path, root, mode)
            for path in base.rglob("*.py")
            if path.name != "__init__.py" and "abandoned" not in path.parts
        )
    return sorted(files, key=lambda item: str(item[0]))


def infer_level(source: str, mode: str) -> tuple[int, dict[str, bool | int | list[str]]]:
    has_positive_extra = bool(re.search(r"Result\.(?:taken|available)\([^)]*extra\s*=", source, re.S))
    positive_extra_keys = re.findall(r'extra\s*=\s*\{([^}]*)\}', source, re.S)
    discovered_keys: set[str] = set()
    for block in positive_extra_keys:
        for key in re.findall(r'["\']([^"\']+)["\']\s*:', block):
            discovered_keys.add(key.strip().lower().replace(" ", "_"))

    has_profile_template = bool(
        re.search(
            r'show_url\s*=|f["\']https?://.*\{user\}|https?://[^"\']*\{user\}|https?://[^"\']*\{username\}',
            source,
            re.I,
        )
    )
    rich_hits = discovered_keys & RICH_SIGNAL_KEYS

    if mode == "username":
        if has_profile_template:
            level = 4
            strategy = "profile-html-enrichment"
        elif has_positive_extra:
            level = 3
            strategy = "direct-extra-normalization"
        else:
            level = 2
            strategy = "positive-match-only"
    else:
        if has_positive_extra and (len(discovered_keys) >= 2 or len(rich_hits) >= 1):
            level = 4
            strategy = "account-evidence-enrichment"
        elif has_positive_extra:
            level = 3
            strategy = "direct-extra-normalization"
        elif has_profile_template:
            level = 2
            strategy = "account-url-only"
        else:
            level = 1
            strategy = "found-not-found-only"

    return level, {
        "has_positive_extra": has_positive_extra,
        "has_profile_template": has_profile_template,
        "rich_signal_count": len(rich_hits),
        "metadata_keys": sorted(discovered_keys),
        "strategy": strategy,
    }


def module_record(path: Path, root: Path, mode: str) -> dict[str, object]:
    source = path.read_text(encoding="utf-8")
    rel = path.relative_to(root).as_posix()
    category = path.parent.name
    platform = path.stem
    level, details = infer_level(source, mode)

    return {
        "platform": platform,
        "mode": mode,
        "category": category,
        "path": rel,
        "capability_level": level,
        **details,
    }


def main() -> None:
    records = [module_record(path, root, mode) for path, root, mode in iter_modules()]
    level_counts = Counter(record["capability_level"] for record in records)
    summary = {
        "total_modules": len(records),
        "level_counts": {f"level_{level}": level_counts.get(level, 0) for level in range(1, 5)},
        "level_3_plus": sum(1 for record in records if record["capability_level"] >= 3),
        "level_4": sum(1 for record in records if record["capability_level"] >= 4),
    }

    print(json.dumps({"summary": summary, "modules": records}, indent=2))


if __name__ == "__main__":
    main()
