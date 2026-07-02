<?php

return [
    'user_agent' => env('SCANNER_USER_AGENT', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36'),
    'verify_ssl' => env('SCANNER_VERIFY_SSL', false),
    'proxy_list' => array_values(array_filter(array_map(
        static fn (string $line): string => trim($line),
        preg_split('/\R/', (string) env('SCANNER_PROXY_LIST', '')) ?: []
    ), static fn (string $line): bool => $line !== '')),

    'async' => [
        'queue' => env('SCANNER_ASYNC_QUEUE', 'scanner'),
        'job_timeout' => (int) env('SCANNER_ASYNC_JOB_TIMEOUT', 45),
        'job_tries' => (int) env('SCANNER_ASYNC_JOB_TRIES', 1),
        'sync_result_threshold' => (int) env('SCANNER_SYNC_RESULT_THRESHOLD', 12),
        'max_expected_results' => (int) env('SCANNER_MAX_EXPECTED_RESULTS', 5000),
        'poll_interval_ms' => (int) env('SCANNER_POLL_INTERVAL_MS', 2000),
    ],

    'loud_modules' => [
        'username' => [],
        'email' => [
            'leetcode',
            'instagram',
            'netflix',
            'sexvid',
            'made.porn',
            'flirtbate',
            'polarsteps',
            'babestation',
            'flipkart',
        ],
    ],

    'auto_skip' => [
        'username' => [],
        'email' => [
            'fapfolder' => '403',
            'lovescape' => 'Unexpected: Username is already used',
            'pornhub' => 'cURL error 28: Operation timed out after 5012 milliseconds with 108237 bytes received (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for https://www.pornhub.com',
            'patreon' => 'Status 403',
            'axonaut' => 'Request timeout',
            'boot_dev' => 'HTTP 404',
            'codepen' => 'Unexpected response: 403',
            'replit' => '403 Forbidden',
            'appletv' => 'Request timeout',
            'letterboxd' => 'Unexpected response structure',
            'alison' => 'Alison signup now appears to be JS-shell / reCAPTCHA gated, so the old non-interactive email check flow is no longer parsable',
            'allen' => 'Blocked by Allen WAF (403)',
            'foxnews' => 'Fox legacy status endpoint returned found=false for a real account; the old non-interactive signal is no longer reliable',
            'indiatimes' => 'Connection timed out! maybe region blocks',
            'nytimes' => 'NYT took too long to answer',
            'anydo' => 'Connection timed out! maybe region blocks',
            'deviantart' => 'Connection timed out! maybe region blocks',
            'amazon' => 'CAPTCHA triggered (IP may be flagged)',
            'classmates' => 'Caught by WAF (403) during Handshake',
            'marca' => 'Request timeout',
            'vivino' => 'Registered - Auto Registers people',
            'flirtbate' => 'Notifies the target by forgot password email or similar',
            'sexvid' => 'Notifies the target by forgot password email or similar',
            'leetcode' => 'Notifies the target by forgot password email or similar',
            'netflix' => 'Notifies the target by forgot password email or similar',
            'flipkart' => 'Notifies the target by forgot password email or similar',
            'instagram' => 'Notifies the target by forgot password email or similar',
            'polarsteps' => 'Notifies the target by forgot password email or similar',
        ],
    ],

    'nsfw_categories' => ['adult'],

    // Register pure Laravel validators here.
    'validators' => [
        App\Services\Scanner\Validators\User\GithubValidator::class,
        App\Services\Scanner\Validators\User\XValidator::class,
    ],
];
