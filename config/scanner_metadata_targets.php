<?php

$emailModuleTargetOverrides = json_decode((string) env('SCANNER_EMAIL_BASELINE_MODULE_TARGETS', '{}'), true);
if (!is_array($emailModuleTargetOverrides)) {
    $emailModuleTargetOverrides = [];
}

$normalizedEmailModuleTargetOverrides = [];
foreach ($emailModuleTargetOverrides as $module => $targets) {
    if (!is_string($module)) {
        continue;
    }

    $module = trim($module);
    if ($module === '') {
        continue;
    }

    if (is_string($targets)) {
        $targets = [$targets];
    }

    if (!is_array($targets)) {
        continue;
    }

    $normalizedTargets = [];
    foreach ($targets as $target) {
        if (!is_string($target)) {
            continue;
        }

        $target = trim($target);
        if ($target !== '') {
            $normalizedTargets[] = $target;
        }
    }

    if ($normalizedTargets !== []) {
        $normalizedEmailModuleTargetOverrides[strtolower($module)] = array_values(array_unique($normalizedTargets));
    }
}

$registry = [
    'username' => [
        '35photo' => ['35photo'],
        'about_me' => ['aboutme'],
        'arduino' => ['arduino'],
        'asciinema' => ['asciinema'],
        'atcoder' => ['chokudai'],
        'bandlab' => ['bandlab'],
        'beatstars' => ['beatstars'],
        'behance' => ['behanceprofile'],
        'blogger' => ['googleblog'],
        'bluesky' => ['bluesky'],
        'boosty' => ['boosty'],
        'boot_dev' => ['bootdev'],
        'buymeacoffee' => ['pickacountry'],
        'codeforces' => ['codeforces'],
        'codewars' => ['g964'],
        'codeberg' => ['codeberg'],
        'coderwall' => ['coderwall'],
        'cratesio' => ['alexcrichton'],
        'dailymotion' => ['dailymotion'],
        'deviantart' => ['deviantart'],
        'daily_dev' => ['dailydotdev'],
        'devto' => ['thepracticaldev', 'ben'],
        'discogs' => ['discogs'],
        'dockerhub' => ['docker'],
        'archwiki' => ['Alad'],
        'chess_com' => ['hikaru'],
        'calendly' => ['calendly'],
        'codecademy' => ['codecademy'],
        'disqus' => ['disqus'],
        'discourse_meta' => ['sam'],
        'dribbble' => ['simplebits', 'desandro'],
        'donatealerts' => ['donationalerts'],
        'duolingo' => ['duolingo'],
        'etoro' => ['yoniassia'],
        'flickr' => ['flickr'],
        'freelancer' => ['freelancer'],
        'ghost_forum' => ['ghost'],
        'freesound' => ['freesound'],
        'gitea' => ['lunny'],
        'githubgist' => ['sindresorhus'],
        'gitlab' => ['gitlab-bot'],
        'gravatar' => ['gravatar'],
        'gumroad' => ['gumroad'],
        'gpodder_net' => ['gpodder'],
        'hackerrank' => ['hackerrank'],
        'habr' => ['habr'],
        'hashicorp_discuss' => ['apparentlymart'],
        'huggingface' => ['openai'],
        'imgur' => ['sarah'],
        'ifttt' => ['ifttt'],
        'instructables' => ['instructables'],
        'jupyter_forum' => ['bollwyvl'],
        'itch_io' => ['itchio'],
        'kaggle' => ['serigne'],
        'leetcode' => ['leetcode'],
        'lichess' => ['thibault'],
        'mozilladiscourse' => ['pmac'],
        'mixcloud' => ['mixcloud'],
        'minds' => ['minds'],
        'monkeytype' => ['monkeytype'],
        'niftygateway' => ['niftygateway'],
        'naturalnews' => ['naturalnews'],
        'omglol' => ['adam'],
        'operaforums' => ['operaforums'],
        'osu' => ['peppy'],
        'openstreetmap' => ['SomeoneElse'],
        'packagist' => ['fabpot'],
        'paragraph' => ['paragraph'],
        'picsart' => ['picsart'],
        'pinterest' => ['pinterest'],
        'pypi' => ['mitsuhiko'],
        'python_discuss' => ['vstinner'],
        'roblox' => ['builderman'],
        'rust_users' => ['nikomatsakis'],
        'scratch' => ['scratchteam'],
        'sourceforge' => ['csc'],
        'spotify' => ['spotify'],
        'speedrun' => ['darbian'],
        'sportstracker' => ['sportstracker'],
        'statsfm' => ['statsfm'],
        'steam' => ['steam'],
        'anilist' => ['anilist'],
        'stackoverflow' => ['jon-skeet'],
        'warframemarket' => ['warframemarket'],
        'wikipedia' => ['fabpot', 'Jimbo_Wales'],
        'github' => ['torvalds', 'sindresorhus'],
        'hackernews' => ['pg', 'dang'],
        'hashnode' => ['hashnode'],
        'keybase' => ['sindresorhus', 'hynek', 'max'],
        'kotlin_discuss' => ['hhariri'],
        'linktree' => ['mrbeast', 'garyvee'],
        'launchpad' => ['mpt', 'didrocks'],
        'lastfm' => ['wadecastle'],
        'livejournal' => ['livejournal'],
        'minecraft' => ['minecraft'],
        'mastodon' => ['mastodon'],
        'patreon' => ['patreon'],
        'pastebin' => ['ubuntu'],
        'rubygems' => ['rails'],
        'soundcloud' => ['soundcloud'],
        'snapchat' => ['statedept'],
        'substack' => ['lennysnewsletter'],
        'telegram' => ['durov'],
        'trello' => ['trello'],
        'venmo' => ['eodioko'],
        'vivino' => ['vivino'],
        'vimeo' => ['staff'],
        'warpcast' => ['dwr'],
        'weebly' => ['weebly'],
        'wordpress' => ['hynek', 'dd32', 'max'],
        'x' => ['openai'],
        'youtube' => ['openai'],
    ],
    'email' => [
        'adobe' => ['baseline_email_primary', 'baseline_email_secondary', 'baseline_email_tertiary'],
        'allen' => ['baseline_email_primary'],
        'appletv' => ['baseline_email_tertiary'],
        'coursera' => ['baseline_email_primary', 'baseline_email_tertiary'],
        'duolingo' => ['baseline_email_tertiary'],
        'etsy' => ['baseline_email_tertiary', 'baseline_email_primary'],
        'eventbrite' => ['baseline_email_primary', 'baseline_email_tertiary'],
        'github' => ['baseline_email_primary', 'baseline_email_tertiary'],
        'gravatar' => ['baseline_email_tertiary'],
        'indiatimes' => ['baseline_email_primary', 'baseline_email_secondary'],
        'vedantu' => ['baseline_email_primary'],
        'vivino' => ['baseline_email_primary'],
        'walmart' => ['baseline_email_primary', 'baseline_email_secondary', 'baseline_email_tertiary'],
        'wix' => ['baseline_email_primary', 'baseline_email_secondary'],
    ],
];

foreach ($normalizedEmailModuleTargetOverrides as $module => $targets) {
    $existingTargets = $registry['email'][$module] ?? [];
    $registry['email'][$module] = array_values(array_unique(array_merge($existingTargets, $targets)));
}

return $registry;
