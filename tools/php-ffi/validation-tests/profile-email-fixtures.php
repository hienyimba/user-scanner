<?php

// Synthetic positive/negative response fixtures retained from the existing parser suite.
return [
    'canva registered' => [
        0 => 'canva',
        1 => [
            0 => [
                0 => 200,
                1 => ')]}\'\\n{"B":{"A?":"A"}}',
            ],
        ],
        2 => 'Registered',
    ],
    'canva available' => [
        0 => 'canva',
        1 => [
            0 => [
                0 => 200,
                1 => ')]}\'\\n{"B":{"A?":"B"}}',
            ],
        ],
        2 => 'Not Registered',
    ],
    'figma registered' => [
        0 => 'figma',
        1 => [
            0 => [
                0 => 200,
                1 => [
                    'error' => false,
                    'meta' => [
                        'available_methods' => [
                            0 => 'sign_in',
                        ],
                    ],
                ],
            ],
        ],
        2 => 'Registered',
    ],
    'figma available' => [
        0 => 'figma',
        1 => [
            0 => [
                0 => 200,
                1 => [
                    'error' => false,
                    'meta' => [
                        'available_methods' => [
                            0 => 'sign_up',
                        ],
                    ],
                ],
            ],
        ],
        2 => 'Not Registered',
    ],
    'atlassian registered' => [
        0 => 'atlassian',
        1 => [
            0 => [
                0 => 200,
                1 => [
                    'action' => 'no_action',
                ],
            ],
        ],
        2 => 'Registered',
    ],
    'atlassian social login registered' => [
        0 => 'atlassian',
        1 => [
            0 => [
                0 => 200,
                1 => [
                    'action' => 'social_login',
                    'identities' => [
                        'google' => true,
                    ],
                ],
            ],
        ],
        2 => 'Registered',
    ],
    'atlassian available' => [
        0 => 'atlassian',
        1 => [
            0 => [
                0 => 200,
                1 => [
                    'action' => 'signup',
                ],
            ],
        ],
        2 => 'Not Registered',
    ],
    'annaabi registered' => [
        0 => 'annaabi',
        1 => [
            0 => [
                0 => 200,
                1 => '<input value="2" id="psemail">Sellise emailiga kasutaja on juba registreerinud',
            ],
        ],
        2 => 'Registered',
    ],
    'annaabi available' => [
        0 => 'annaabi',
        1 => [
            0 => [
                0 => 200,
                1 => '<input value="1" id="psemail">See email on vaba',
            ],
        ],
        2 => 'Not Registered',
    ],
    'start me registered' => [
        0 => 'start_me',
        1 => [
            0 => [
                0 => 200,
                1 => [
                    'exists' => true,
                    'locked' => true,
                ],
            ],
        ],
        2 => 'Registered',
    ],
    'start me available' => [
        0 => 'start_me',
        1 => [
            0 => [
                0 => 200,
                1 => [
                    'exists' => false,
                ],
            ],
        ],
        2 => 'Not Registered',
    ],
    'cv ee registered' => [
        0 => 'cv_ee',
        1 => [
            0 => [
                0 => 200,
                1 => '',
            ],
        ],
        2 => 'Registered',
    ],
    'cv ee available' => [
        0 => 'cv_ee',
        1 => [
            0 => [
                0 => 404,
                1 => [
                    'errorType' => 'RESOURCE_NOT_FOUND',
                    'message' => 'Resource not found.',
                ],
            ],
        ],
        2 => 'Not Registered',
    ],
    'cv lv registered' => [
        0 => 'cv_lv',
        1 => [
            0 => [
                0 => 200,
                1 => '',
            ],
        ],
        2 => 'Registered',
    ],
    'cv lv available' => [
        0 => 'cv_lv',
        1 => [
            0 => [
                0 => 404,
                1 => [
                    'errorType' => 'RESOURCE_NOT_FOUND',
                    'message' => 'Resource not found.',
                ],
            ],
        ],
        2 => 'Not Registered',
    ],
    'cvonline lt registered' => [
        0 => 'cvonline_lt',
        1 => [
            0 => [
                0 => 200,
                1 => '',
            ],
        ],
        2 => 'Registered',
    ],
    'cvonline lt available' => [
        0 => 'cvonline_lt',
        1 => [
            0 => [
                0 => 404,
                1 => [
                    'errorType' => 'RESOURCE_NOT_FOUND',
                    'message' => 'Resource not found.',
                ],
            ],
        ],
        2 => 'Not Registered',
    ],
    'dirbam registered' => [
        0 => 'dirbam',
        1 => [
            0 => [
                0 => 200,
                1 => '"Paskyra su tokių el.paštu jau yra"',
            ],
        ],
        2 => 'Registered',
    ],
    'dirbam available' => [
        0 => 'dirbam',
        1 => [
            0 => [
                0 => 200,
                1 => '"true"',
            ],
        ],
        2 => 'Not Registered',
    ],
    'otsintood registered' => [
        0 => 'otsintood',
        1 => [
            0 => [
                0 => 200,
                1 => '"Selle e-postiga on juba kasutaja registreeritud"',
            ],
        ],
        2 => 'Registered',
    ],
    'otsintood available' => [
        0 => 'otsintood',
        1 => [
            0 => [
                0 => 200,
                1 => '"true"',
            ],
        ],
        2 => 'Not Registered',
    ],
    'pulser registered' => [
        0 => 'pulser',
        1 => [
            0 => [
                0 => 200,
                1 => [
                    'data' => [
                        'status' => true,
                    ],
                ],
            ],
        ],
        2 => 'Registered',
    ],
    'pulser available' => [
        0 => 'pulser',
        1 => [
            0 => [
                0 => 422,
                1 => [
                    'message' => 'The selected email is invalid.',
                ],
            ],
        ],
        2 => 'Not Registered',
    ],
    'visidarbi registered' => [
        0 => 'visidarbi',
        1 => [
            0 => [
                0 => 200,
                1 => '"Profils ar šādu e-pasta adresi jau ir reģistrēts"',
            ],
        ],
        2 => 'Registered',
    ],
    'visidarbi available' => [
        0 => 'visidarbi',
        1 => [
            0 => [
                0 => 200,
                1 => '"true"',
            ],
        ],
        2 => 'Not Registered',
    ],
    'quora registered' => [
        0 => 'quora',
        1 => [
            0 => [
                0 => 200,
                1 => '{"formkey": "abc123"}',
            ],
            1 => [
                0 => 200,
                1 => [
                    'data' => [
                        'validateEmail' => 'IN_USE',
                    ],
                ],
            ],
        ],
        2 => 'Registered',
    ],
    'quora available' => [
        0 => 'quora',
        1 => [
            0 => [
                0 => 200,
                1 => '{"formkey": "abc123"}',
            ],
            1 => [
                0 => 200,
                1 => [
                    'data' => [
                        'validateEmail' => 'OK',
                    ],
                ],
            ],
        ],
        2 => 'Not Registered',
    ],
    'threadless registered' => [
        0 => 'threadless',
        1 => [
            0 => [
                0 => 200,
                1 => '<input name="csrfmiddlewaretoken" value="csrf-1">',
            ],
            1 => [
                0 => 200,
                1 => [
                    'is_valid' => false,
                ],
            ],
        ],
        2 => 'Registered',
    ],
    'threadless available' => [
        0 => 'threadless',
        1 => [
            0 => [
                0 => 200,
                1 => '<input name="csrfmiddlewaretoken" value="csrf-1">',
            ],
            1 => [
                0 => 200,
                1 => [
                    'is_valid' => true,
                ],
            ],
        ],
        2 => 'Not Registered',
    ],
    'codepen registered' => [
        0 => 'codepen',
        1 => [
            0 => [
                0 => 200,
                1 => '<meta name="csrf-token" content="csrf-1">',
            ],
            1 => [
                0 => 200,
                1 => [
                    'success' => true,
                    'payload' => [
                        'errors' => [
                            'email' => [
                                0 => 'That Email is already taken. Please choose another!',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        2 => 'Registered',
    ],
    'codepen available' => [
        0 => 'codepen',
        1 => [
            0 => [
                0 => 200,
                1 => '<meta name="csrf-token" content="csrf-1">',
            ],
            1 => [
                0 => 200,
                1 => [
                    'success' => true,
                    'payload' => [
                        'errors' => [
                            'email' => [
                            ],
                        ],
                    ],
                ],
            ],
        ],
        2 => 'Not Registered',
    ],
    'cvkeskus registered' => [
        0 => 'cvkeskus',
        1 => [
            0 => [
                0 => 200,
                1 => '<form id="reminderForm"><input name="csrf_token" value="csrf-1"></form>',
            ],
            1 => [
                0 => 200,
                1 => 'Saatsime Sinu kasutajanime meeldetuletuse e-postile:',
            ],
        ],
        2 => 'Registered',
    ],
    'cvkeskus available' => [
        0 => 'cvkeskus',
        1 => [
            0 => [
                0 => 200,
                1 => '<form id="reminderForm"><input name="csrf_token" value="csrf-1"></form>',
            ],
            1 => [
                0 => 200,
                1 => 'Sisestatud e-posti aadress puudub meie süsteemis.',
            ],
        ],
        2 => 'Not Registered',
    ],
    'cvmarket lt registered' => [
        0 => 'cvmarket_lt',
        1 => [
            0 => [
                0 => 200,
                1 => '<form id="reminderForm"><input name="csrf_token" value="csrf-1"></form>',
            ],
            1 => [
                0 => 200,
                1 => 'Jeigu su šiuo el. paštu yra paskyrą, jūs turėjote gauti',
            ],
        ],
        2 => 'Registered',
    ],
    'cvmarket lt available' => [
        0 => 'cvmarket_lt',
        1 => [
            0 => [
                0 => 200,
                1 => '<form id="reminderForm"><input name="csrf_token" value="csrf-1"></form>',
            ],
            1 => [
                0 => 200,
                1 => 'Su tokiu el. paštu registracijos nėra.',
            ],
        ],
        2 => 'Not Registered',
    ],
    'cvmarket lv registered' => [
        0 => 'cvmarket_lv',
        1 => [
            0 => [
                0 => 200,
                1 => '<form id="reminderForm"><input name="csrf_token" value="csrf-1"></form>',
            ],
            1 => [
                0 => 200,
                1 => 'Ja esat reģistrējies portālā ar norādīto e-pasta adresi',
            ],
        ],
        2 => 'Registered',
    ],
    'cvmarket lv available' => [
        0 => 'cvmarket_lv',
        1 => [
            0 => [
                0 => 200,
                1 => '<form id="reminderForm"><input name="csrf_token" value="csrf-1"></form>',
            ],
            1 => [
                0 => 200,
                1 => 'Trūkst e-pasta adreses!',
            ],
        ],
        2 => 'Not Registered',
    ],
    'hercul registered' => [
        0 => 'hercul',
        1 => [
            0 => [
                0 => 200,
                1 => [
                    'isAvailable' => false,
                ],
            ],
            1 => [
                0 => 200,
                1 => '',
            ],
        ],
        2 => 'Registered',
    ],
    'hercul available' => [
        0 => 'hercul',
        1 => [
            0 => [
                0 => 200,
                1 => [
                    'isAvailable' => true,
                ],
            ],
        ],
        2 => 'Not Registered',
    ],
    'jobs cz registered' => [
        0 => 'jobs_cz',
        1 => [
            0 => [
                0 => 200,
                1 => '<input name="user_email[_token]" value="csrf-1">',
            ],
            1 => [
                0 => 200,
                1 => 'Posíláme odkaz pro obnovení hesla',
            ],
        ],
        2 => 'Registered',
    ],
    'jobs cz available' => [
        0 => 'jobs_cz',
        1 => [
            0 => [
                0 => 200,
                1 => '<input name="user_email[_token]" value="csrf-1">',
            ],
            1 => [
                0 => 200,
                1 => 'Zadaný e-mail není registrovaný.',
            ],
        ],
        2 => 'Not Registered',
    ],
    'kandideeri registered' => [
        0 => 'kandideeri',
        1 => [
            0 => [
                0 => 200,
                1 => '<form id="registr-form"><meta name="csrf-token" content="csrf-1">',
            ],
            1 => [
                0 => 200,
                1 => 'NOT_UNIQUE_VALUE',
            ],
        ],
        2 => 'Registered',
    ],
    'kandideeri available' => [
        0 => 'kandideeri',
        1 => [
            0 => [
                0 => 200,
                1 => '<form id="registr-form"><meta name="csrf-token" content="csrf-1">',
            ],
            1 => [
                0 => 200,
                1 => '',
            ],
        ],
        2 => 'Not Registered',
    ],
    'tindie registered' => [
        0 => 'tindie',
        1 => [
            0 => [
                0 => 200,
                1 => '<html>signup</html>',
                2 => [
                    'Set-Cookie' => 'csrftoken=csrf-1; Path=/',
                ],
            ],
            1 => [
                0 => 200,
                1 => [
                    'valid' => false,
                    'errors' => [
                        0 => 'Email already registered!',
                    ],
                ],
            ],
        ],
        2 => 'Registered',
    ],
    'tindie available' => [
        0 => 'tindie',
        1 => [
            0 => [
                0 => 200,
                1 => '<html>signup</html>',
                2 => [
                    'Set-Cookie' => 'csrftoken=csrf-1; Path=/',
                ],
            ],
            1 => [
                0 => 200,
                1 => [
                    'valid' => true,
                    'errors' => [
                    ],
                    'message' => 'Email available!',
                ],
            ],
        ],
        2 => 'Not Registered',
    ],
    'tumblr registered' => [
        0 => 'tumblr',
        1 => [
            0 => [
                0 => 200,
                1 => '{"API_TOKEN":"api-1"}',
            ],
            1 => [
                0 => 200,
                1 => [
                ],
                2 => [
                    'X-Csrf' => 'csrf-1',
                ],
            ],
            2 => [
                0 => 400,
                1 => [
                    'response' => [
                        'code' => 2,
                        'error' => 'User already exists',
                    ],
                ],
            ],
        ],
        2 => 'Registered',
    ],
    'tumblr available' => [
        0 => 'tumblr',
        1 => [
            0 => [
                0 => 200,
                1 => '{"API_TOKEN":"api-1"}',
            ],
            1 => [
                0 => 200,
                1 => [
                ],
                2 => [
                    'X-Csrf' => 'csrf-1',
                ],
            ],
            2 => [
                0 => 200,
                1 => [
                    'meta' => [
                        'status' => 200,
                        'msg' => 'OK',
                    ],
                    'response' => [
                    ],
                ],
            ],
        ],
        2 => 'Not Registered',
    ],
    'redtube explicit registered' => [
        0 => 'redtube',
        1 => [
            0 => [
                0 => 200,
                1 => '<script>page_params.token = "token-1."</script>',
            ],
            1 => [
                0 => 200,
                1 => [
                    'email' => 'create_account_failed',
                    'error_message' => 'Email already registered',
                ],
            ],
        ],
        2 => 'Registered',
    ],
    'redtube available' => [
        0 => 'redtube',
        1 => [
            0 => [
                0 => 200,
                1 => '<script>page_params.token = "token-1."</script>',
            ],
            1 => [
                0 => 200,
                1 => [
                    'email' => 'create_account_passed',
                    'error_message' => '',
                ],
            ],
        ],
        2 => 'Not Registered',
    ],
];
