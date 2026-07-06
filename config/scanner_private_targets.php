<?php

$jsonAliases = json_decode((string) env('SCANNER_EMAIL_BASELINE_TARGET_ALIASES', '{}'), true);
if (!is_array($jsonAliases)) {
    $jsonAliases = [];
}

$envAliases = [];
foreach ($jsonAliases as $alias => $value) {
    if (!is_string($alias) || !is_string($value)) {
        continue;
    }

    $alias = trim($alias);
    $value = trim($value);
    if ($alias === '' || $value === '') {
        continue;
    }

    $envAliases[$alias] = $value;
}

$namedAliases = array_filter([
    'baseline_email_primary' => env('SCANNER_EMAIL_BASELINE_PRIMARY'),
    'baseline_email_secondary' => env('SCANNER_EMAIL_BASELINE_SECONDARY'),
    'baseline_email_tertiary' => env('SCANNER_EMAIL_BASELINE_TERTIARY'),
], static fn (mixed $value): bool => is_string($value) && trim($value) !== '');

return [
    'email' => array_merge($namedAliases, $envAliases),
];
