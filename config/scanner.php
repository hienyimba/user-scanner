<?php

$structuredProxyPool = [
    [
        'label' => 'us-8008',
        'entry_point' => 'disp.oxylabs.io',
        'ip' => '92.71.122.4',
        'port' => 8008,
        'country_code' => 'US',
        'asn_name' => 'Cox',
        'asn_number' => 22773,
        'tier' => 'primary',
        'enabled' => true,
    ],
    [
        'label' => 'us-8005',
        'entry_point' => 'disp.oxylabs.io',
        'ip' => '92.249.29.44',
        'port' => 8005,
        'country_code' => 'US',
        'asn_name' => 'Astound',
        'asn_number' => 6079,
        'tier' => 'primary',
        'enabled' => true,
    ],
    [
        'label' => 'us-8004',
        'entry_point' => 'disp.oxylabs.io',
        'ip' => '104.243.201.4',
        'port' => 8004,
        'country_code' => 'US',
        'asn_name' => 'Cox',
        'asn_number' => 22773,
        'tier' => 'primary',
        'enabled' => true,
    ],
    [
        'label' => 'us-8003',
        'entry_point' => 'disp.oxylabs.io',
        'ip' => '104.243.200.57',
        'port' => 8003,
        'country_code' => 'US',
        'asn_name' => 'Cox',
        'asn_number' => 22773,
        'tier' => 'primary',
        'enabled' => true,
    ],
    [
        'label' => 'us-8002',
        'entry_point' => 'disp.oxylabs.io',
        'ip' => '104.243.199.16',
        'port' => 8002,
        'country_code' => 'US',
        'asn_name' => 'Cox',
        'asn_number' => 22773,
        'tier' => 'primary',
        'enabled' => true,
    ],
    [
        'label' => 'us-8001',
        'entry_point' => 'disp.oxylabs.io',
        'ip' => '92.71.123.21',
        'port' => 8001,
        'country_code' => 'US',
        'asn_name' => 'Cox',
        'asn_number' => 22773,
        'tier' => 'primary',
        'enabled' => true,
    ],
    [
        'label' => 'gb-8010',
        'entry_point' => 'disp.oxylabs.io',
        'ip' => '45.41.149.119',
        'port' => 8010,
        'country_code' => 'GB',
        'asn_name' => 'BRSK',
        'asn_number' => 51809,
        'tier' => 'primary',
        'enabled' => true,
    ],
    [
        'label' => 'gb-8009',
        'entry_point' => 'disp.oxylabs.io',
        'ip' => '45.41.148.181',
        'port' => 8009,
        'country_code' => 'GB',
        'asn_name' => 'BRSK',
        'asn_number' => 51809,
        'tier' => 'primary',
        'enabled' => true,
    ],
    [
        'label' => 'ca-8007',
        'entry_point' => 'disp.oxylabs.io',
        'ip' => '82.23.174.64',
        'port' => 8007,
        'country_code' => 'CA',
        'asn_name' => 'Rogers',
        'asn_number' => 812,
        'tier' => 'fallback',
        'enabled' => true,
    ],
    [
        'label' => 'de-8006',
        'entry_point' => 'disp.oxylabs.io',
        'ip' => '64.105.212.162',
        'port' => 8006,
        'country_code' => 'DE',
        'asn_name' => 'Deutsche Telekom',
        'asn_number' => 3320,
        'tier' => 'fallback',
        'enabled' => true,
    ],
];

$explicitProxyList = array_values(array_filter(array_map(
    static fn (string $line): string => trim($line),
    preg_split('/\R/', (string) env('SCANNER_PROXY_LIST', '')) ?: []
), static fn (string $line): bool => $line !== ''));

$configuredPoolProxyList = array_values(array_map(
    static fn (array $proxy): string => sprintf('%s:%d', $proxy['entry_point'], $proxy['port']),
    array_values(array_filter(
        $structuredProxyPool,
        static fn (array $proxy): bool => ($proxy['enabled'] ?? true) === true
    ))
));

return [
    'user_agent' => env('SCANNER_USER_AGENT', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36'),
    'verify_ssl' => env('SCANNER_VERIFY_SSL', false),
    'proxy_list' => $explicitProxyList !== [] ? $explicitProxyList : $configuredPoolProxyList,
    'proxies' => [
        'provider' => env('SCANNER_PROXY_PROVIDER', 'oxylabs_isp'),
        'credentials' => [
            'username' => env('SCANNER_PROXY_USERNAME'),
            'password' => env('SCANNER_PROXY_PASSWORD'),
        ],
        'pool' => $structuredProxyPool,
        'behavior' => [
            'max_concurrent_per_proxy' => (int) env('SCANNER_PROXY_MAX_CONCURRENT_PER_IP', 2),
            'max_retry_per_module' => (int) env('SCANNER_PROXY_MAX_RETRY_PER_MODULE', 1),
            'failure_threshold' => (int) env('SCANNER_PROXY_FAILURE_THRESHOLD', 2),
            'cooldown_min_seconds' => (int) env('SCANNER_PROXY_COOLDOWN_MIN_SECONDS', 30),
            'cooldown_max_seconds' => (int) env('SCANNER_PROXY_COOLDOWN_MAX_SECONDS', 90),
            'wait_timeout_seconds' => (int) env('SCANNER_PROXY_WAIT_TIMEOUT_SECONDS', 15),
            'wait_retry_seconds' => (int) env('SCANNER_PROXY_WAIT_RETRY_SECONDS', 2),
        ],
    ],

    'async' => [
        'queue' => env('SCANNER_ASYNC_QUEUE', 'scanner'),
        'job_timeout' => (int) env('SCANNER_ASYNC_JOB_TIMEOUT', 45),
        'job_tries' => (int) env('SCANNER_ASYNC_JOB_TRIES', 1),
        'sync_result_threshold' => (int) env('SCANNER_SYNC_RESULT_THRESHOLD', 12),
        'max_expected_results' => (int) env('SCANNER_MAX_EXPECTED_RESULTS', 5000),
        'poll_interval_ms' => (int) env('SCANNER_POLL_INTERVAL_MS', 2000),
    ],

    'metadata' => [
        'fetch_profile_html' => env('SCANNER_METADATA_FETCH_PROFILE_HTML', true),
        'request_timeout_seconds' => (int) env('SCANNER_METADATA_TIMEOUT_SECONDS', 8),
        'max_html_bytes' => (int) env('SCANNER_METADATA_MAX_HTML_BYTES', 262144),
        'max_external_links' => (int) env('SCANNER_METADATA_MAX_EXTERNAL_LINKS', 10),
    ],

    'loud_modules' => [
        'username' => [],
        'email' => [
            'leetcode',
            'netflix',
            'sexvid',
            'made.porn',
            'flirtbate',
            'babestation',
            'flipkart',
            'ama',
            'buymeacoffee',
            'luarocks',
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
            'foxnews' => 'Fox legacy status endpoint returned found=false for a real account; the old non-interactive signal is no longer reliable',
            'nytimes' => 'NYT took too long to answer',
            'anydo' => 'Connection timed out! maybe region blocks',
            'deviantart' => 'Connection timed out! maybe region blocks',
            'amazon' => 'CAPTCHA triggered (IP may be flagged)',
            'classmates' => 'Caught by WAF (403) during Handshake',
            'marca' => 'Request timeout',
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
