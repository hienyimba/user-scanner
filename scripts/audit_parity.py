from __future__ import annotations

import argparse
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PY_ROOT = ROOT / 'user-scanner-py' / 'user_scanner'
PHP_ROOT = ROOT / 'app' / 'Services' / 'Scanner' / 'Validators' / 'Generated'

from generate_validators import MANUAL_EMAIL_KEYS, MANUAL_USER_KEYS, discover_specs, sync_manual_sets  # type: ignore


def php_path_for(spec) -> Path:
    mode_dir = 'User' if spec.mode == 'username' else 'Email'
    return PHP_ROOT / mode_dir / f'{spec.class_name}.php'


def parity_class(spec, php_exists: bool) -> str:
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
    return 'exact' if php_exists else 'generator-shaped'


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument('--output', type=Path, default=None)
    args = parser.parse_args()

    specs = discover_specs()
    sync_manual_sets(specs)
    report = []
    for spec in specs:
        php_path = php_path_for(spec)
        report.append({
            'mode': spec.mode,
            'category': spec.category,
            'key': spec.key,
            'python_path': str(spec.python_path),
            'php_path': str(php_path),
            'parity_class': parity_class(spec, php_path.exists()),
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
        'report': report,
    }
    rendered = json.dumps(output, indent=2)
    if args.output is not None:
        args.output.parent.mkdir(parents=True, exist_ok=True)
        args.output.write_text(rendered, encoding='utf-8')
    print(rendered)


if __name__ == '__main__':
    main()
