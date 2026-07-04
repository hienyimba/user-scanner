<?php

return [
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\DiscourseMetaValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\DiscourseMetaValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\DisqusValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'response' => [
                    'id' => '1',
                ],
                ],
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\DisqusValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'json' => [],
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\GhostForumValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\GhostForumValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\InstructablesValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\InstructablesValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\JupyterForumValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\JupyterForumValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\MozilladiscourseValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'user' => [
                    'name' => 'Alice',
                ],
                ],
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\MozilladiscourseValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\OperaforumsValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\OperaforumsValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\UbuntuMateValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\UbuntuMateValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\WikipediaValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'query' => [
                    'users' => [
                    [
                    'userid' => 1,
                ],
                ],
                ],
                ],
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\WikipediaValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'query' => [
                    'users' => [
                    [
                    'missing' => '',
                ],
                ],
                ],
                ],
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\BehanceValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\BehanceValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\BoostyValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\BoostyValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\FanslyValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'success' => true,
                    'response' => [
                    [
                    'id' => 1,
                ],
                ],
                ],
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\FanslyValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'success' => true,
                    'response' => [],
                ],
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\VimeoValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\VimeoValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\CodecademyValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\CodecademyValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\CodeforcesValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'status' => 'OK',
                    'result' => [
                    [
                    'handle' => 'alice',
                ],
                ],
                ],
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\CodeforcesValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 400,
                'json' => [
                    'status' => 'FAILED',
                ],
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\CoderwallValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\CoderwallValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\CodewarsValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\CodewarsValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\ElixirForumValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\ElixirForumValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\FDroidValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\FDroidValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\GiteaValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\GiteaValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\GiteeValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\GiteeValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\GithubgistValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'login' => 'alice',
                ],
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\GithubgistValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\HackerrankValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\HackerrankValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\HashicorpDiscussValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\HashicorpDiscussValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\KotlinDiscussValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\KotlinDiscussValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\LuarocksValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\LuarocksValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\MicrosoftlearnValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\MicrosoftlearnValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\PypiValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => '<methodResponse><params><param><value><array><data><value><array><data><value><string>owner</string></value><value><string>pkg</string></value></data></array></value></data></array></value></param></params></methodResponse>',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\PypiValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => '<methodResponse><params><param><value><array><data></data></array></value></param></params></methodResponse>',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\PythonDiscussValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\PythonDiscussValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\RustUsersValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\RustUsersValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\ScratchValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\ScratchValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\WordpressValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => '<title>Alice User Profile</title><div class="user-name">Alice</div>',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\WordpressValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\NiftygatewayValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'didSucceed' => true,
                    'userProfileAndNifties' => [
                    'id' => '1',
                ],
                ],
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\NiftygatewayValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 400,
                'json' => [
                    'didSucceed' => false,
                    'errorType' => 'not_found',
                ],
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\KickValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'id' => 123,
                    'slug' => 'alice',
                    'is_banned' => false,
                ],
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\KickValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\SpeedrunValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\SpeedrunValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\WarframemarketValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\WarframemarketValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\BeatstarsValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'data' => [
                    'identifierAvailable' => [
                    'available' => false,
                ],
                ],
                ],
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\BeatstarsValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'data' => [
                    'identifierAvailable' => [
                    'available' => true,
                ],
                ],
                ],
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\MixcloudValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\MixcloudValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\SpotifyValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\SpotifyValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\YandexmusicValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\YandexmusicValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\CalendlyValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\CalendlyValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\DuolingoValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'users' => [
                    [
                    'id' => 1,
                ],
                ],
                ],
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\DuolingoValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'users' => [],
                ],
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\FreelancerValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'result' => [
                    'users' => [
                    '1' => [
                    'id' => 1,
                ],
                ],
                ],
                ],
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\FreelancerValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'result' => [
                    'users' => [],
                ],
                ],
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\IssuuValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\IssuuValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'No such user',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\OmglolValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\OmglolValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\ParagraphValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'id' => '1',
                    'name' => 'Alice',
                ],
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\ParagraphValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'json' => [],
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\StatsfmValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\StatsfmValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\TrelloValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\TrelloValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\VivinoValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'id' => 1,
                    'alias' => 'alice',
                ],
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\VivinoValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'json' => [],
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\FiverrValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\FiverrValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\BloggerValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\BloggerValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\DailymotionValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\DailymotionValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\DeviantartValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\DeviantartValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\FlickrValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\FlickrValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\FotkaValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\FotkaValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\FoursquareValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\FoursquareValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\GoodreadsValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\GoodreadsValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\GravatarValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\GravatarValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'User not found',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\HabrValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\HabrValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\ImgurValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'id' => 1,
                    'username' => 'alice',
                ],
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\ImgurValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\KeybaseValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'them' => [
                    [
                    'profile' => [
                    'full_name' => 'Alice',
                ],
                ],
                ],
                ],
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\KeybaseValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'them' => [],
                ],
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\LivejournalValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\LivejournalValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\PicsartValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\PicsartValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\Pr0grammValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\Pr0grammValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\Px500Validator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'data' => [
                    'userByUsername' => [
                    'legacyId' => 1,
                ],
                ],
                ],
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\Px500Validator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'errors' => [
                    [
                    'extensions' => [
                    'response' => [
                    'status' => 404,
                ],
                ],
                ],
                ],
                    'data' => [
                    'userByUsername' => null,
                ],
                ],
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\SportstrackerValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\SportstrackerValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'Not found',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\TumblrValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\TumblrValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\VirgoolValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\VirgoolValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\WarpcastValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'result' => [
                    'user' => [
                    'fid' => 1,
                ],
                ],
                ],
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\WarpcastValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\WeeblyValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 200,
                'body' => 'profile ok',
            ],
        ],
        'expected' => 'Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\User\\WeeblyValidator',
        'target' => 'alice',
        'responses' => [
            [
                'status' => 404,
                'body' => 'missing profile',
            ],
        ],
        'expected' => 'Not Found',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\Email\\BuymeacoffeeValidator',
        'target' => 'jane@example.com',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'otp_login' => true,
                ],
            ],
        ],
        'expected' => 'Registered',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\Email\\BuymeacoffeeValidator',
        'target' => 'jane@example.com',
        'responses' => [
            [
                'status' => 422,
                'json' => [
                    'message' => 'No account with the given email',
                ],
            ],
        ],
        'expected' => 'Not Registered',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\Email\\KickValidator',
        'target' => 'jane@example.com',
        'responses' => [
            [
                'status' => 422,
                'json' => [
                    'errors' => [
                    'email' => [
                    'has already been taken',
                ],
                ],
                ],
            ],
        ],
        'expected' => 'Registered',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\Email\\KickValidator',
        'target' => 'jane@example.com',
        'responses' => [
            [
                'status' => 204,
                'body' => '',
            ],
        ],
        'expected' => 'Not Registered',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\Email\\HackerearthValidator',
        'target' => 'jane@example.com',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'errors' => [
                    'email' => 'already registered',
                ],
                ],
            ],
        ],
        'expected' => 'Registered',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\Email\\HackerearthValidator',
        'target' => 'jane@example.com',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'errors' => [
                    'password' => 'required',
                ],
                ],
            ],
        ],
        'expected' => 'Not Registered',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\Email\\HackerrankValidator',
        'target' => 'jane@example.com',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'status' => false,
                    'internal_status_code' => 'already_registered',
                ],
            ],
        ],
        'expected' => 'Registered',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\Email\\HackerrankValidator',
        'target' => 'jane@example.com',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'status' => true,
                ],
            ],
        ],
        'expected' => 'Not Registered',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\Email\\LuarocksValidator',
        'target' => 'jane@example.com',
        'responses' => [
            [
                'status' => 200,
                'body' => '<input name="csrf_token" value="abc">',
            ],
            [
                'status' => 200,
                'body' => 'password reset link has been sent',
            ],
        ],
        'expected' => 'Registered',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\Email\\LuarocksValidator',
        'target' => 'jane@example.com',
        'responses' => [
            [
                'status' => 200,
                'body' => '<input name="csrf_token" value="abc">',
            ],
            [
                'status' => 200,
                'body' => 'don\'t know anyone with that email',
            ],
        ],
        'expected' => 'Not Registered',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\Email\\RubygemsValidator',
        'target' => 'jane@example.com',
        'responses' => [
            [
                'status' => 200,
                'body' => '<input name="authenticity_token" value="abc">',
            ],
            [
                'status' => 200,
                'body' => 'has already been taken',
            ],
        ],
        'expected' => 'Registered',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\Email\\RubygemsValidator',
        'target' => 'jane@example.com',
        'responses' => [
            [
                'status' => 200,
                'body' => '<input name="authenticity_token" value="abc">',
            ],
            [
                'status' => 200,
                'body' => 'Password can\'t be blank',
            ],
        ],
        'expected' => 'Not Registered',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\Email\\GirlslifeValidator',
        'target' => 'jane@example.com',
        'responses' => [
            [
                'status' => 200,
                'body' => '<input name="_wpnonce" value="abc">',
            ],
            [
                'status' => 200,
                'body' => 'The email you entered is incorrect',
            ],
        ],
        'expected' => 'Registered',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\Email\\GirlslifeValidator',
        'target' => 'jane@example.com',
        'responses' => [
            [
                'status' => 200,
                'body' => '<input name="_wpnonce" value="abc">',
            ],
            [
                'status' => 200,
                'body' => 'Password is required',
            ],
        ],
        'expected' => 'Not Registered',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\Email\\BunnyValidator',
        'target' => 'jane@example.com',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'Message' => 'already in use',
                    'Field' => 'Email',
                ],
            ],
        ],
        'expected' => 'Registered',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\Email\\BunnyValidator',
        'target' => 'jane@example.com',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'Message' => 'Passwords must have 1 number',
                ],
            ],
        ],
        'expected' => 'Not Registered',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\Email\\AmaValidator',
        'target' => 'jane@example.com',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'httpCode' => '200',
                    'message' => 'sent successfully',
                ],
            ],
        ],
        'expected' => 'Registered',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\Email\\AmaValidator',
        'target' => 'jane@example.com',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'httpCode' => '202',
                    'message' => 'not found',
                ],
            ],
        ],
        'expected' => 'Not Registered',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\Email\\ScreenerValidator',
        'target' => 'jane@example.com',
        'responses' => [
            [
                'status' => 200,
                'body' => '<input name="csrfmiddlewaretoken" value="abc"><input name="token" value="def">',
            ],
            [
                'status' => 200,
                'body' => 'User account with this Email already exists',
            ],
        ],
        'expected' => 'Registered',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\Email\\ScreenerValidator',
        'target' => 'jane@example.com',
        'responses' => [
            [
                'status' => 200,
                'body' => '<input name="csrfmiddlewaretoken" value="abc"><input name="token" value="def">',
            ],
            [
                'status' => 200,
                'body' => '<ul class="errorlist"><li>This field is required.</li>',
            ],
        ],
        'expected' => 'Not Registered',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\Email\\EtsyValidator',
        'target' => 'jane@example.com',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'user_id' => 1,
                ],
            ],
        ],
        'expected' => 'Registered',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\Email\\EtsyValidator',
        'target' => 'jane@example.com',
        'responses' => [
            [
                'status' => 200,
                'body' => 'null',
            ],
        ],
        'expected' => 'Not Registered',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\Email\\NykaamanValidator',
        'target' => 'jane@example.com',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'response' => [
                    'is_exists' => true,
                ],
                ],
            ],
        ],
        'expected' => 'Registered',
    ],
    [
        'class' => 'App\\Services\\Scanner\\Validators\\Generated\\Email\\NykaamanValidator',
        'target' => 'jane@example.com',
        'responses' => [
            [
                'status' => 200,
                'json' => [
                    'response' => [
                    'is_exists' => false,
                ],
                ],
            ],
        ],
        'expected' => 'Not Registered',
    ],
];
