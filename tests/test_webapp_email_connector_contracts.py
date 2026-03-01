import ast
import json
import re
from pathlib import Path

BASE = Path('webapp/app/Services/Scanning/Connectors')
EMAIL_DIR = BASE / 'Generated' / 'Email'
FIXTURE_DIR = Path('tests/fixtures/email_connectors')


def _extract_return_list(content: str, method: str):
    m = re.search(rf"{re.escape(method)}\(\): array\s*\{{\s*return\s*(\[[^;]+\]);", content, re.S)
    if not m:
        return []
    raw = m.group(1)
    return ast.literal_eval(raw)


def _extract_return_int_list(content: str, method: str):
    m = re.search(rf"{re.escape(method)}\(\): array\s*\{{\s*return\s*(\[[^;]+\]);", content, re.S)
    if not m:
        return []
    return ast.literal_eval(m.group(1))


def _json_path_value(payload, path):
    cur = payload
    for segment in path.split('.'):
        if not isinstance(cur, dict) or segment not in cur:
            return None
        cur = cur[segment]
    return cur


def _json_has_any(payload, paths):
    for path in paths:
        value = _json_path_value(payload, path)
        if isinstance(value, bool):
            if value:
                return True
            continue
        if isinstance(value, (int, float)):
            if int(value) > 0:
                return True
            continue
        if isinstance(value, str):
            normalized = value.strip().lower()
            if normalized and normalized not in {'false', '0', 'no', 'null'}:
                return True
    return False


def _simulate(status, body, payload, reg_codes, nreg_codes, reg_ind, nreg_ind, reg_paths, nreg_paths):
    body_lower = body.lower()
    if status == 429 or status >= 500:
        return 'error'
    if _json_has_any(payload, nreg_paths):
        return 'not_registered'
    if _json_has_any(payload, reg_paths):
        return 'registered'
    if status in nreg_codes or any(x.lower() in body_lower for x in nreg_ind):
        return 'not_registered'
    if status in reg_codes or any(x.lower() in body_lower for x in reg_ind):
        return 'registered'
    return 'error'


def test_base_email_connector_contract_and_no_placeholder():
    content = (BASE / 'BaseEmailConnector.php').read_text()
    assert 'Email probe contract not implemented for this connector yet' not in content
    assert 'Unable to determine email registration from connector response signatures' in content
    assert 'Unsupported or malformed probe endpoint for connector' in content

    for method in [
        'probeMethod(): string',
        'emailField(): string',
        'probeEndpointPath(): string',
        'probeUrl(): string',
        'requestHeaders(): array',
        'probeQuery(string $email): array',
        'probeBody(string $email): array',
        'registrationIndicators(): array',
        'nonRegistrationIndicators(): array',
        'registrationJsonPaths(): array',
        'nonRegistrationJsonPaths(): array',
    ]:
        assert method in content


def test_all_generated_email_connectors_define_site_specific_hooks():
    connectors = sorted(EMAIL_DIR.glob('*EmailConnector.php'))
    assert len(connectors) >= 60

    required = [
        'protected function probeMethod(): string',
        'protected function emailField(): string',
        'protected function probeEndpointPath(): string',
        'protected function probeUrl(): string',
        'protected function requestHeaders(): array',
        'protected function registeredStatusCodes(): array',
        'protected function nonRegisteredStatusCodes(): array',
        'protected function probeQuery(string $email): array',
        'protected function probeBody(string $email): array',
        'protected function registrationIndicators(): array',
        'protected function nonRegistrationIndicators(): array',
        'protected function registrationJsonPaths(): array',
        'protected function nonRegistrationJsonPaths(): array',
    ]

    for connector in connectors:
        content = connector.read_text()
        for item in required:
            assert item in content, f"{connector.name} missing {item}"


def test_representative_fixtures_match_connector_signatures():
    fixtures = sorted(FIXTURE_DIR.glob('*.json'))
    assert len(fixtures) >= 5

    for fixture_file in fixtures:
        data = json.loads(fixture_file.read_text())
        connector_path = EMAIL_DIR / data['connector']
        assert connector_path.exists(), f"missing connector {data['connector']}"
        content = connector_path.read_text()

        reg_codes = _extract_return_int_list(content, 'registeredStatusCodes')
        nreg_codes = _extract_return_int_list(content, 'nonRegisteredStatusCodes')
        reg_ind = _extract_return_list(content, 'registrationIndicators')
        nreg_ind = _extract_return_list(content, 'nonRegistrationIndicators')
        reg_paths = _extract_return_list(content, 'registrationJsonPaths')
        nreg_paths = _extract_return_list(content, 'nonRegistrationJsonPaths')

        outcome = _simulate(
            data['status'],
            data['body'],
            data['json'],
            reg_codes,
            nreg_codes,
            reg_ind,
            nreg_ind,
            reg_paths,
            nreg_paths,
        )
        assert outcome == data['expected'], f"{data['connector']} expected {data['expected']} got {outcome}"
