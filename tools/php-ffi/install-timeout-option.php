<?php

declare(strict_types=1);

// Reproducible, fail-closed compatibility patch for the audited 2.0.0 release.
// This adds curl's existing seconds-valued connect-timeout option to the shared
// registry; it works on both the FFI and executable engines. No native rebuild.
$path = dirname(__DIR__, 2).'/vendor/hamaadraza/php-impersonate/src/Support/CurlOptions.php';
if (! is_file($path)) {
    throw new RuntimeException('Pinned PHP-Impersonate dependency is missing');
}
$source = file_get_contents($path);
$needle = "        'proxy' => [self::TYPE_STRING, self::CURLOPT_PROXY],";
$addition = "        'connect-timeout' => [self::TYPE_LONG, 78], // scanner compatibility: CURLOPT_CONNECTTIMEOUT\n";
$expected = '34fde0c2f176b7be38eccb077d0d27062b902c3d82518961a381f37693b60fb6';
$originalEmpty = "private const EMPTY_IS_MEANINGFUL = ['proxy'];";
$patchedEmpty = "private const EMPTY_IS_MEANINGFUL = ['proxy', 'noproxy'];";
$canonical = str_replace([$addition, $patchedEmpty], ['', $originalEmpty], $source);
if (substr_count($source, $addition) === 1 && str_contains($source, $patchedEmpty)
    && hash('sha256', $canonical) === $expected) {
    echo "PHP-Impersonate scanner timeout option already installed.\n";
} elseif (hash('sha256', $canonical) !== $expected || substr_count($canonical, $needle) !== 1) {
    throw new RuntimeException('PHP-Impersonate source differs from the audited 2.0.0 file; refusing to patch');
} else {
    file_put_contents($path, str_replace([$needle, $originalEmpty], [$addition.$needle, $patchedEmpty], $canonical));
    echo "Installed audited PHP-Impersonate connect-timeout compatibility option.\n";
}
require __DIR__.'/install-native-diagnostics.php';
