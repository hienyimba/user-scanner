from __future__ import annotations

import argparse
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
JUNE_ROOT = ROOT / 'user-scanner-py-june-release' / 'user_scanner'
SEPTEMBER_ROOT = ROOT / 'user-scanner-py-sept-release' / 'user_scanner'
PHP_ROOT = ROOT / 'app' / 'Services' / 'Scanner' / 'Validators' / 'Generated'
VALIDATOR_ROOT = ROOT / 'app' / 'Services' / 'Scanner' / 'Validators'
SEPTEMBER_CONFIG = ROOT / 'config' / 'scanner_september_email.php'
SEPTEMBER_USERNAME_CONFIG = ROOT / 'config' / 'scanner_september_username.php'

from generate_validators import MANUAL_EMAIL_KEYS, MANUAL_USER_KEYS, build_spec, sync_manual_sets  # type: ignore


def discover_specs():
    specs = []
    sources = (
        (SEPTEMBER_ROOT / 'user_scan', 'username'),
        (SEPTEMBER_ROOT / 'email_scan', 'email'),
    )
    for base, mode in sources:
        for category_dir in sorted(base.iterdir()):
            if not category_dir.is_dir() or category_dir.name.startswith('__') or category_dir.name.lower() == 'abandoned':
                continue
            for py_file in sorted(category_dir.glob('*.py')):
                if py_file.name != '__init__.py':
                    specs.append(build_spec(py_file, mode, category_dir.name.lower()))
    return specs


def registered_runtime_keys(mode: str) -> dict[str, Path]:
    mode_dir = 'User' if mode == 'username' else 'Email'
    candidates = [
        VALIDATOR_ROOT / mode_dir,
        VALIDATOR_ROOT / 'Generated' / mode_dir,
        VALIDATOR_ROOT / 'Generated' / 'Manual' / mode_dir,
    ]
    found: dict[str, Path] = {}
    for base in candidates:
        if not base.is_dir():
            continue
        for path in base.glob('*Validator.php'):
            match = re.search(r"function\s+key\(\):\s*string\s*\{\s*return\s+'([^']+)'", path.read_text(encoding='utf-8'), re.S)
            if match:
                found[match.group(1)] = path

    if mode == 'email' and SEPTEMBER_CONFIG.is_file():
        for key in re.findall(r"'key'\s*=>\s*'([^']+)'", SEPTEMBER_CONFIG.read_text(encoding='utf-8')):
            found.setdefault(key, SEPTEMBER_CONFIG)
    if mode == 'username' and SEPTEMBER_USERNAME_CONFIG.is_file():
        for key in re.findall(r"'key'\s*=>\s*'([^']+)'", SEPTEMBER_USERNAME_CONFIG.read_text(encoding='utf-8')):
            found.setdefault(key, SEPTEMBER_USERNAME_CONFIG)

    return found


def php_path_for(spec) -> Path:
    mode_dir = 'User' if spec.mode == 'username' else 'Email'
    return PHP_ROOT / mode_dir / f'{spec.class_name}.php'


def parity_class(spec, php_path: Path | None) -> str:
    if php_path is None:
        return 'missing'
    if php_path == SEPTEMBER_CONFIG:
        return 'september-declarative'
    if php_path == SEPTEMBER_USERNAME_CONFIG:
        return 'september-declarative'
    manual = MANUAL_USER_KEYS if spec.mode == 'username' else MANUAL_EMAIL_KEYS
    if spec.key in manual:
        return 'manual-user' if spec.mode == 'username' else 'manual-email'
    if spec.complexity == 'manual' and spec.mode == 'email':
        return 'missing-manual-email'
    if spec.input_validation:
        return 'needs-input-validation'
    if spec.body_mode in {'json', 'raw'} or spec.request_method == 'POST':
        return 'needs-multi-step' if spec.complexity == 'manual' else 'generator-shaped'
    if spec.rules and any(any('status' in cond and '$body' in cond for cond in rule.conditions) for rule in spec.rules):
        return 'needs-body-order-fix'
    return 'exact'


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument('--output', type=Path, default=None)
    parser.add_argument('--quiet', action='store_true')
    args = parser.parse_args()

    specs = discover_specs()
    sync_manual_sets(specs)
    runtime_keys = {
        'username': registered_runtime_keys('username'),
        'email': registered_runtime_keys('email'),
    }
    report = []
    for spec in specs:
        php_path = runtime_keys[spec.mode].get(spec.key)
        report.append({
            'mode': spec.mode,
            'category': spec.category,
            'key': spec.key,
            'python_path': str(spec.python_path),
            'php_path': str(php_path) if php_path is not None else str(php_path_for(spec)),
            'parity_class': parity_class(spec, php_path),
            'complexity': spec.complexity,
            'traits': {
                'request_method': spec.request_method,
                'url_pattern': spec.request_url,
                'headers': sorted(spec.headers.keys()),
                'query_params': sorted(spec.query.keys()),
                'body_mode': spec.body_mode,
                'timeout': spec.timeout,
                'follow_redirects': spec.follow_redirects,
                'input_validation': bool(spec.input_validation),
                'body_rules': len(spec.rules),
                'multi_request_bootstrap': spec.complexity == 'manual' and spec.body_mode in {'json', 'raw'},
            },
        })

    output = {
        'total': len(report),
        'exact': sum(1 for row in report if row['parity_class'] == 'exact'),
        'manual_user': sum(1 for row in report if row['parity_class'] == 'manual-user'),
        'manual_email': sum(1 for row in report if row['parity_class'] == 'manual-email'),
        'september_declarative': sum(1 for row in report if row['parity_class'] == 'september-declarative'),
        'missing': sum(1 for row in report if row['parity_class'] == 'missing'),
        'report': report,
    }
    rendered = json.dumps(output, indent=2)
    if args.output is not None:
        args.output.parent.mkdir(parents=True, exist_ok=True)
        args.output.write_text(rendered, encoding='utf-8')
    if not args.quiet:
        print(rendered)


if __name__ == '__main__':
    main()
