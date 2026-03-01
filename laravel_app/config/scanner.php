<?php

return [
    'user_agent' => env('SCANNER_USER_AGENT', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36'),

    // Register pure Laravel validators here.
    'validators' => [
        App\Services\Scanner\Validators\User\GithubValidator::class,
        App\Services\Scanner\Validators\User\XValidator::class,
        App\Services\Scanner\Validators\Email\GithubEmailValidator::class,
    ],
];
