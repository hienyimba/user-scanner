from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PY_ROOT = ROOT / "user-scanner-py" / "user_scanner"
PHP_CONFIG = ROOT / "config" / "scanner_generated.php"


def load_python_inventory() -> set[tuple[str, str, str]]:
    inventory: set[tuple[str, str, str]] = set()
    for mode, folder in (("username", "user_scan"), ("email", "email_scan")):
        for category_dir in (PY_ROOT / folder).iterdir():
            if not category_dir.is_dir() or category_dir.name.startswith("__"):
                continue
            for py_file in category_dir.glob("*.py"):
                if py_file.name == "__init__.py":
                    continue
                inventory.add((mode, category_dir.name.lower(), py_file.stem.replace(".", "_").lower()))
    return inventory


def load_php_inventory() -> set[str]:
    content = PHP_CONFIG.read_text(encoding="utf-8")
    return set(re.findall(r"Generated\\[A-Za-z]+\\([A-Za-z0-9]+)::class", content))


def main() -> None:
    python_inventory = load_python_inventory()
    expected_total = len(python_inventory)
    content = PHP_CONFIG.read_text(encoding="utf-8")
    actual_total = len(re.findall(r"Generated\\(?:Email|User)\\[A-Za-z0-9]+::class", content))

    print(f"python inventory: {expected_total}")
    print(f"php generated registrations: {actual_total}")

    if actual_total < expected_total:
        raise SystemExit("Parity audit failed: generated validator registrations are below Python module count.")

    print("Parity audit passed.")


if __name__ == "__main__":
    main()
