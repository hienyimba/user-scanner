<?php

/**
 * Data-only ports of one-request email modules added in user-scanner v1.5.2.
 *
 * Keep each definition aligned with its `source` file. Rules must positively
 * identify both registered and unregistered states; ambiguous responses fall
 * through to Error.
 */

return [
    'modules' => [
        [
            'key' => 'mondly',
            'category' => 'learning',
            'site_name' => 'Mondly',
            'site_url' => 'https://app.mondly.com',
            'source' => 'email_scan/learning/mondly.py',
            'request' => [
                'method' => 'POST',
                'url' => 'https://api.mondly.com/v1/user/login',
                'body_mode' => 'form',
                'body' => ['email' => '{target}', 'password' => 'scanner-invalid-password'],
            ],
            'rules' => [
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 200],
                    ['field' => 'json', 'path' => 'errors.password', 'value' => ['incorrect']],
                ]], 'status' => 'Registered'],
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 200],
                    ['field' => 'json', 'path' => 'errors.password', 'value' => ['not-found']],
                ]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'speakly',
            'category' => 'learning',
            'site_name' => 'Speakly',
            'site_url' => 'https://speakly.me',
            'source' => 'email_scan/learning/speakly.py',
            'request' => [
                'method' => 'POST',
                'url' => 'https://api.v4.speakly.me/api/v4/users/password/reset/',
                'body' => ['email' => '{target}'],
            ],
            'rules' => [
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 400],
                    ['field' => 'json', 'path' => '0', 'value' => 'Email not found.'],
                ]], 'status' => 'Not Registered'],
                ['when' => ['field' => 'status', 'value' => 400], 'status' => 'Error', 'reason' => 'Email rejected by Speakly'],
                ['when' => ['field' => 'status', 'value' => 200], 'status' => 'Registered'],
            ],
        ],
        [
            'key' => 'payhip',
            'category' => 'creator',
            'site_name' => 'Payhip',
            'site_url' => 'https://payhip.com',
            'source' => 'email_scan/creator/payhip.py',
            'request' => [
                'method' => 'POST',
                'url' => 'https://payhip.com/auth/forgot_password_buyer_ajax',
                'body_mode' => 'form',
                'headers' => [
                    'Origin' => 'https://payhip.com',
                    'Referer' => 'https://payhip.com/auth/forgot_password',
                    'X-Requested-With' => 'XMLHttpRequest',
                ],
                'body' => ['login' => '{target}'],
            ],
            'rules' => [
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 200],
                    ['field' => 'json', 'path' => 'success', 'operator' => 'truthy'],
                ]], 'status' => 'Registered'],
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 200],
                    ['field' => 'json', 'path' => 'success', 'operator' => 'falsy'],
                    ['field' => 'json', 'path' => 'error_message_public', 'value' => "Login or email doesn't exist"],
                ]], 'status' => 'Not Registered'],
            ],
            'unexpected_reason' => 'Unexpected Payhip password-reset response (HTTP {http_status})',
        ],
        [
            'key' => 'sweat',
            'category' => 'fitness',
            'site_name' => 'Sweat',
            'site_url' => 'https://sweat.com',
            'source' => 'email_scan/fitness/sweat.py',
            'semantic_statuses' => [403],
            'request' => [
                'method' => 'POST',
                'url' => 'https://login.sweat.com/oauth/token',
                'timeout' => 6,
                'headers' => [
                    'User-Agent' => 'okhttp/5.3.2',
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json; charset=utf-8',
                ],
                'body' => [
                    'client_id' => 'ekN6uujzIgNGOxe8gu6XkmhSyhlnmt31',
                    'scope' => 'openid email offline_access profile',
                    'username' => '{target}',
                    'password' => 'dummy_password_xyz123',
                    'grant_type' => 'http://auth0.com/oauth/grant-type/password-realm',
                    'realm' => 'Username-Password-Authentication',
                ],
            ],
            'rules' => [
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 403],
                    ['field' => 'json', 'path' => 'error_description', 'operator' => 'contains_ci', 'value' => 'password is incorrect'],
                ]], 'status' => 'Registered'],
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 403],
                    ['any' => [
                        ['field' => 'json', 'path' => 'error_description', 'operator' => 'contains_ci', 'value' => "couldn't find an account"],
                        ['field' => 'json', 'path' => 'error_description', 'operator' => 'contains_ci', 'value' => 'couldn’t find an account'],
                    ]],
                ]], 'status' => 'Not Registered', 'reason' => 'Target may have an OAuth-only registered account (Apple, Google, or Facebook)'],
            ],
        ],
        [
            'key' => 'whering',
            'category' => 'social',
            'site_name' => 'Whering',
            'site_url' => 'https://play.google.com/store/apps/details?id=com.whering.app',
            'source' => 'email_scan/social/whering.py',
            'request' => [
                'method' => 'POST',
                'url' => 'https://www.googleapis.com/identitytoolkit/v3/relyingparty/verifyPassword',
                'timeout' => 20,
                'query' => ['key' => 'AIzaSyBuo23oVhkHuZ_P7rKU1jUrMD8gJ95yVwk'],
                'headers' => [
                    'User-Agent' => 'Dalvik/2.1.0 (Linux; U; Android 13; Pixel 6 Build/TP1A.220624.021)',
                    'Content-Type' => 'application/json',
                    'X-Android-Package' => 'com.whering.app',
                    'X-Client-Version' => 'Android/Fallback/X24000000/FirebaseCore-Android',
                    'X-Firebase-GMPID' => '1:182334734580:android:b55d5ff0601be7971e5ea1',
                ],
                'body' => [
                    'email' => '{target}',
                    'password' => 'igoh8h8g89y',
                    'returnSecureToken' => true,
                    'clientType' => 'CLIENT_TYPE_ANDROID',
                ],
            ],
            'rules' => [
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 400],
                    ['field' => 'json', 'path' => 'error.message', 'value' => 'EMAIL_NOT_FOUND'],
                ]], 'status' => 'Not Registered'],
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 400],
                    ['field' => 'json', 'path' => 'error.message', 'value' => 'INVALID_PASSWORD'],
                ]], 'status' => 'Registered'],
            ],
        ],
        [
            'key' => 'fixderma',
            'category' => 'shopping',
            'site_name' => 'Fixderma',
            'site_url' => 'https://www.fixderma.com',
            'source' => 'email_scan/shopping/fixderma.py',
            'semantic_statuses' => [401],
            'request' => [
                'method' => 'POST',
                'url' => 'https://fixderma-skincare.myshopify.com/api/2025-04/graphql',
                'timeout' => 20,
                'body_mode' => 'raw',
                'headers' => [
                    'User-Agent' => 'Mobile Buy SDK Android/2025.4.1/com.fixderma',
                    'Content-Type' => 'application/graphql; charset=utf-8',
                    'x-buy3-sdk-cache-fetch-strategy' => 'CACHE_FIRST',
                    'x-sdk-version' => '2025.4.1',
                    'x-sdk-variant' => 'android',
                    'x-shopify-storefront-access-token' => '7b9cc3ec1eb82866fb49d1c6d30c4981',
                ],
                'body' => 'mutation {customerCreate(input:{email:"{target_json}",password:"no",firstName:"nopi",lastName:"fish"}){customer{firstName,lastName,email,id,tags},customerUserErrors{field,message}}}',
            ],
            'rules' => [
                ['when' => ['field' => 'status', 'value' => 401], 'status' => 'Error', 'reason' => 'Fixderma Storefront API rejected the configured access token (HTTP 401); account status unknown'],
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 200],
                    ['field' => 'json', 'path' => 'data.customerCreate.customerUserErrors', 'operator' => 'array_any', 'where' => ['all' => [
                        ['field' => 'item', 'path' => 'field', 'operator' => 'contains', 'value' => 'email'],
                        ['field' => 'item', 'path' => 'message', 'operator' => 'contains_ci', 'value' => 'already been taken'],
                    ]]],
                ]], 'status' => 'Registered'],
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 200],
                    ['field' => 'json', 'path' => 'data.customerCreate.customerUserErrors', 'operator' => 'array_any', 'where' => [
                        'field' => 'item', 'path' => 'message', 'operator' => 'contains_ci', 'value' => 'verify your email address',
                    ]],
                ]], 'status' => 'Registered', 'metadata' => ['account_status' => 'unverified']],
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 200],
                    ['field' => 'json', 'path' => 'data.customerCreate.customerUserErrors', 'operator' => 'array_any', 'where' => ['all' => [
                        ['field' => 'item', 'path' => 'field', 'operator' => 'contains', 'value' => 'password'],
                        ['field' => 'item', 'path' => 'message', 'operator' => 'contains', 'value' => 'Password is too short'],
                    ]]],
                ]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'medal',
            'category' => 'gaming',
            'site_name' => 'Medal',
            'site_url' => 'https://medal.tv/login',
            'source' => 'email_scan/gaming/medal.py',
            'request' => [
                'method' => 'POST',
                'url' => 'https://medal.tv/api/users/email',
                'body' => ['email' => '{target}'],
            ],
            'rules' => [
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 200],
                    ['field' => 'json', 'path' => 'valid', 'operator' => 'truthy'],
                    ['field' => 'json', 'path' => 'exists', 'operator' => 'truthy'],
                ]], 'status' => 'Registered'],
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 200],
                    ['field' => 'json', 'path' => 'valid', 'operator' => 'truthy'],
                    ['field' => 'json', 'path' => 'exists', 'operator' => 'falsy'],
                ]], 'status' => 'Not Registered'],
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 200],
                    ['field' => 'json', 'path' => 'valid', 'operator' => 'falsy'],
                ]], 'status' => 'Error', 'reason' => 'Medal rejected the email'],
            ],
        ],
        [
            'key' => 'wisio',
            'category' => 'creator',
            'site_name' => 'Wisio',
            'site_url' => 'https://www.wisio.com',
            'source' => 'email_scan/creator/wisio.py',
            'request' => [
                'method' => 'GET',
                'url' => 'https://be-lb-app.scaleabout.com/api/v1/auth/email/is_registered',
                'query' => ['email' => '{target}'],
            ],
            'rules' => [
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 200],
                    ['field' => 'body', 'transform' => 'trim', 'value' => 'true'],
                ]], 'status' => 'Registered'],
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 200],
                    ['field' => 'body', 'transform' => 'trim', 'value' => 'false'],
                ]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'aslbloom',
            'category' => 'learning',
            'site_name' => 'Aslbloom',
            'site_url' => 'https://www.aslbloom.com',
            'source' => 'email_scan/learning/aslbloom.py',
            'request' => [
                'method' => 'POST',
                'url' => 'https://app.aslbloom.com/api/searchForUser',
                'timeout' => 10,
                'headers' => ['User-Agent' => 'okhttp/4.12.0', 'Content-Type' => 'application/json'],
                'body' => ['email' => '{target}'],
            ],
            'rules' => [
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 200],
                    ['field' => 'json', 'path' => 'foundUser', 'value' => 'YES'],
                ]], 'status' => 'Registered'],
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 200],
                    ['field' => 'json', 'path' => 'foundUser', 'value' => 'NO'],
                ]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'duocards',
            'category' => 'learning',
            'site_name' => 'Duocards',
            'site_url' => 'https://app.duocards.com',
            'source' => 'email_scan/learning/duocards.py',
            'request' => [
                'method' => 'POST',
                'url' => 'https://api.duocards.com/graphql?EmailAuthGetEmailInfoMutation',
                'body' => [
                    'query' => 'mutation EmailAuthGetEmailInfoMutation($email: String!) { getEmailInfo(email: $email) { exists password google apple loginLinkSent } }',
                    'variables' => ['email' => '{target}'],
                ],
            ],
            'rules' => [
                ['when' => ['field' => 'json', 'path' => 'data.getEmailInfo.exists', 'operator' => 'truthy'], 'status' => 'Registered', 'metadata_from_json' => [
                    'password_login' => 'data.getEmailInfo.password',
                    'google_login' => 'data.getEmailInfo.google',
                    'apple_login' => 'data.getEmailInfo.apple',
                    'login_link_sent' => 'data.getEmailInfo.loginLinkSent',
                ]],
                ['when' => ['field' => 'json', 'path' => 'data.getEmailInfo.exists', 'operator' => 'falsy'], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'finch',
            'category' => 'fitness',
            'site_name' => 'Finch',
            'site_url' => 'https://www.finchcare.com/',
            'source' => 'email_scan/fitness/finch.py',
            'request' => [
                'method' => 'POST',
                'url' => 'https://www.googleapis.com/identitytoolkit/v3/relyingparty/getOobConfirmationCode',
                'query' => ['key' => 'AIzaSyAtxGf97SHuNfYC0pjpTM4FSwPf08n9VF0'],
                'headers' => [
                    'User-Agent' => 'Dalvik/2.1.0 (Linux; U; Android 11; RMX2193 Build/RP1A.200720.011)',
                    'Content-Type' => 'application/json',
                    'X-Android-Package' => 'com.finch.finch',
                    'X-Client-Version' => 'Android/Fallback/X23002001/FirebaseCore-Android',
                ],
                'body' => [
                    'requestType' => 1,
                    'email' => '{target}',
                    'androidInstallApp' => false,
                    'canHandleCodeInApp' => false,
                    'clientType' => 'CLIENT_TYPE_ANDROID',
                ],
            ],
            'rules' => [
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 200],
                    ['field' => 'json', 'path' => 'email', 'operator' => 'exists'],
                ]], 'status' => 'Registered'],
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 400],
                    ['field' => 'json', 'path' => 'error.message', 'operator' => 'contains_ci', 'value' => 'email_not_found'],
                ]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'evolveyou',
            'category' => 'fitness',
            'site_name' => 'Evolveyou',
            'site_url' => 'https://evolveyou.app',
            'source' => 'email_scan/fitness/evolveyou.py',
            'semantic_statuses' => [401],
            'request' => [
                'method' => 'POST',
                'url' => 'https://fitness.evolveyouapi.com/api/v1/auth',
                'timeout' => 6,
                'headers' => [
                    'User-Agent' => 'okhttp/4.12.0',
                    'Content-Type' => 'application/json',
                    'x-device-type' => 'unknown',
                    'x-consumer' => 'Tablet',
                    'x-app-version' => '9.7.7',
                    'x-timezone-offset' => '-480',
                    'os' => 'android',
                ],
                'body' => ['username' => '{target}', 'password' => 'dummy_password_xyz123'],
            ],
            'rules' => [
                ['when' => ['all' => [
                    ['field' => 'status', 'operator' => 'in', 'value' => [400, 401]],
                    ['field' => 'json', 'path' => 'message', 'value' => 'Please check your email and password and try again.'],
                ]], 'status' => 'Error', 'reason' => 'EvolveYou returned a generic credential error; account existence cannot be determined'],
                ['when' => ['all' => [
                    ['field' => 'status', 'operator' => 'in', 'value' => [400, 401]],
                    ['field' => 'json', 'path' => 'message', 'operator' => 'contains_ci', 'value' => 'incorrect username or password'],
                ]], 'status' => 'Error', 'reason' => 'EvolveYou returned a unified credential rejection; account existence cannot be determined'],
                ['when' => ['all' => [
                    ['field' => 'status', 'operator' => 'in', 'value' => [400, 401]],
                    ['field' => 'json', 'path' => 'message', 'operator' => 'contains_ci', 'value' => 'user not found'],
                ]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'couplejoy',
            'category' => 'social',
            'site_name' => 'Couplejoy',
            'site_url' => 'https://couplejoyapp.com',
            'source' => 'email_scan/social/couplejoy.py',
            'request' => [
                'method' => 'POST',
                'url' => 'https://api.couplejoyapp.com/v1/reset-password',
                'timeout' => 6,
                'headers' => [
                    'User-Agent' => 'okhttp/4.12.0',
                    'Content-Type' => 'application/json',
                    'app-language' => 'en',
                    'app-timezone' => 'UTC',
                    'app-platform' => 'android',
                    'app-version' => '4.28.0',
                    'authorization' => 'Bearer null',
                ],
                'body' => ['email' => '{target}'],
            ],
            'rules' => [
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 200],
                    ['field' => 'json', 'path' => 'success', 'operator' => 'truthy'],
                ]], 'status' => 'Registered'],
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 400],
                    ['field' => 'json', 'path' => 'code', 'value' => 'EMAIL_NOT_FOUND'],
                ]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'numsify',
            'category' => 'other',
            'site_name' => 'Numsify',
            'site_url' => 'https://numsify.com',
            'source' => 'email_scan/other/numsify.py',
            'request' => [
                'method' => 'POST',
                'url' => 'https://api.numiva.me/api/auth/register',
                'headers' => [
                    'User-Agent' => 'Dart/3.11 (dart:io)',
                    'Content-Type' => 'application/json',
                    'x-device-name' => 'Android:google-Pixel 6-13',
                    'lang' => 'en',
                    'authorization' => 'Bearer null',
                    'x-app-version' => '1.4.53+73',
                ],
                'body' => [
                    'email' => '{target}',
                    'password' => 'everyone_thought_i_am_heartless',
                    'first_name' => 'theknight',
                    'last_name' => 'nevergivesup',
                ],
            ],
            'rules' => [
                ['when' => ['field' => 'json', 'path' => 'error.message', 'operator' => 'contains_ci', 'value' => 'password must contain at least one number'], 'status' => 'Not Registered'],
                ['when' => ['field' => 'json', 'path' => 'error.message', 'operator' => 'contains_ci', 'value' => 'email already registered'], 'status' => 'Registered'],
            ],
        ],
        [
            'key' => 'dollarfix',
            'category' => 'other',
            'site_name' => 'Dollarfix',
            'site_url' => 'https://dollarfix.com',
            'source' => 'email_scan/other/dollarfix.py',
            'request' => [
                'method' => 'POST',
                'url' => 'https://api.dollarfix.com/core-userinfo/user_center/apkLogin',
                'headers' => [
                    'User-Agent' => 'okhttp/4.10.0',
                    'client' => '1',
                    'appversion' => '1.8.9.1',
                    'appid' => '9205',
                    'appname' => 'DollarFix',
                    'uuid' => '{firebase_uuid}',
                    'firebasetoken' => '{firebase_uuid}:APA91bFmZorXu14j6IauzmcISJNDuPds2wd5VAO35UFBLNAnzE7NA242XqLF46kZqZ-k9Q5Io7COOA5WbWScOwOFYqCc_UxqIqfxH486OYDwh3iQslz6Xuk',
                    'Content-Type' => 'application/json; charset=utf-8',
                ],
                'body' => ['password' => 'generic_password_123', 'verifiable_type' => 1, 'user_name' => '{target}'],
            ],
            'rules' => [
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 200],
                    ['field' => 'json', 'path' => 'msg', 'operator' => 'in', 'value' => ['Incorrect password', 'not set password']],
                ]], 'status' => 'Registered'],
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 200],
                    ['field' => 'json', 'path' => 'msg', 'operator' => 'in', 'value' => ['The user does not exist.', '用户不存在。']],
                ]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'secondline',
            'category' => 'other',
            'site_name' => 'Secondline',
            'site_url' => 'https://autobizline.com',
            'source' => 'email_scan/other/secondline.py',
            'request' => [
                'method' => 'POST',
                'url' => 'https://autobizline.com/secondline/user/has_user/',
                'timeout' => 6,
                'body_mode' => 'form',
                'headers' => ['User-Agent' => 'okhttp/4.10.0'],
                'body' => [
                    'api_token' => 'S(FNMSLDFKSD)FLKSlsdflkladf09asdfsafdasdf90123iksf=!@*#',
                    'from_channel' => 'android',
                    'device_id' => '{uuid}',
                    'user_number' => '{target}',
                ],
            ],
            'rules' => [
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 200],
                    ['field' => 'json', 'path' => 'result', 'value' => 'success'],
                    ['any' => [
                        ['field' => 'json', 'path' => 'has_user', 'value' => 'yes'],
                        ['field' => 'json', 'path' => 'has_account', 'value' => 'yes'],
                    ]],
                ]], 'status' => 'Registered'],
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 200],
                    ['field' => 'json', 'path' => 'result', 'value' => 'success'],
                    ['field' => 'json', 'path' => 'has_user', 'value' => 'no'],
                    ['field' => 'json', 'path' => 'has_account', 'value' => 'no'],
                ]], 'status' => 'Not Registered'],
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 200],
                    ['field' => 'json', 'path' => 'result', 'value' => 'failed'],
                ]], 'status' => 'Error', 'reason' => 'API error / request failed'],
            ],
        ],
        [
            'key' => 'aiscore',
            'category' => 'sports',
            'site_name' => 'Aiscore',
            'site_url' => 'https://www.aiscore.com/',
            'source' => 'email_scan/sports/aiscore.py',
            'request' => [
                'method' => 'POST',
                'url' => 'https://api.interntp.com/v1/app/user/register',
                'body_mode' => 'form',
                'headers' => [
                    'User-Agent' => 'AiScore/4.2.4 (realme;RMX2193;Android11)',
                    'platform' => '1',
                    'channel' => 'play Google play',
                    'ver' => '4.2.4',
                    'timezone' => '05:30',
                    'time' => '1783232760',
                ],
                'body' => ['email' => '{target}', 'password' => 'Nopi0fish', 'lang' => '2'],
            ],
            'rules' => [
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 200],
                    ['field' => 'body', 'operator' => 'contains', 'value' => 'The email is existed'],
                ]], 'status' => 'Registered'],
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 200],
                    ['field' => 'body', 'operator' => 'nonempty'],
                ]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'uniscore',
            'category' => 'sports',
            'site_name' => 'Uniscore',
            'site_url' => 'https://uniscore.com/',
            'source' => 'email_scan/sports/uniscore.py',
            'request' => [
                'method' => 'POST',
                'url' => 'https://api.unik8s.com/api/v2/account/auth/forgot-password?language=en',
                'headers' => [
                    'User-Agent' => 'okhttp/4.12.0',
                    'Content-Type' => 'application/json',
                    'app-version' => '1.8.5',
                    'platform' => 'android',
                    'region' => 'IN',
                    'phone-model' => 'REDMAGIC-X3',
                    'os-version' => '11',
                ],
                'body' => ['provider' => 'email', 'locale' => 'en', 'platform' => 'android', 'clientId' => '', 'email' => '{target}'],
            ],
            'rules' => [
                ['when' => ['field' => 'status', 'value' => 200], 'status' => 'Registered'],
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 404],
                    ['field' => 'json', 'path' => 'message', 'operator' => 'contains_ci', 'value' => "this email hasn't been registered to an account"],
                ]], 'status' => 'Not Registered'],
                ['when' => ['field' => 'status', 'value' => 404], 'status' => 'Error', 'reason' => '404 status received but payload body failed verification signature'],
            ],
        ],
        [
            'key' => 'skyscanner',
            'category' => 'travel',
            'site_name' => 'Skyscanner',
            'site_url' => 'https://www.skyscanner.net/',
            'source' => 'email_scan/travel/skyscanner.py',
            'request' => [
                'method' => 'POST',
                'url' => 'https://mobile.ds.skyscanner.net/g/traveller-auth-service/accounts/v2/search',
                'headers' => [
                    'User-Agent' => 'Skyscanner/7.198 (Android 11; Mobile)',
                    'Origin' => 'https://www.skyscanner.net',
                    'x-skyscanner-client' => 'skyscanner_android_app',
                    'x-skyscanner-client-version' => '7.198',
                    'x-skyscanner-client-type' => 'net.skyscanner.android.main',
                    'x-skyscanner-authenticated' => 'false',
                    'x-skyscanner-device-os-type' => 'Android',
                    'x-skyscanner-device-os-version' => '11',
                    'x-skyscanner-device' => 'Android-phone',
                    'x-skyscanner-device-class' => 'phone',
                    'x-skyscanner-client-network-type' => 'WIFI',
                    'x-skyscanner-currency' => 'USD',
                    'x-skyscanner-locale' => 'en-US',
                    'x-skyscanner-market' => 'US',
                    'Content-Type' => 'application/json; charset=UTF-8',
                ],
                'body' => ['email' => '{target}'],
            ],
            'rules' => [
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 200],
                    ['field' => 'json', 'path' => 'providers', 'operator' => 'count_gt', 'value' => 0],
                ]], 'status' => 'Registered', 'metadata_from_json' => ['authentication_providers' => 'providers']],
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 404],
                    ['any' => [
                        ['field' => 'json', 'path' => 'code', 'value' => 404],
                        ['field' => 'json', 'path' => 'message', 'operator' => 'contains', 'value' => 'Not Found'],
                    ]],
                ]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'jiosaavn',
            'category' => 'music',
            'site_name' => 'Jiosaavn',
            'site_url' => 'https://www.jiosaavn.com',
            'source' => 'email_scan/music/jiosaavn.py',
            'request' => [
                'method' => 'POST',
                'url' => 'https://www.saavn.com/api.php',
                'timeout' => 20,
                'body_mode' => 'form',
                'headers' => ['User-Agent' => 'Dalvik/2.1.0 (Linux; U; Android 13; Pixel 6 Build/TP1A.220624.021)'],
                'body' => [
                    'cc' => '', 'dolby_support' => 'false', 'r_device' => 'true',
                    'r_device_flow' => 'ir:,su,idr,', 'app_version' => '10.7.1',
                    '_marker' => '0', 'ctx' => 'android', 'tz' => 'UTC', 'api_version' => '4',
                    'manufacturer' => 'Google', 'provider_state' => 'checking',
                    'readable_version' => '10.7.1', 'build' => 'TP1A.220624.021',
                    'v' => '548', '_format' => 'json', 'is_jio_user' => 'false',
                    'model' => 'Pixel 6', '__call' => 'user.isRegisteredEmail',
                    'network_subtype' => '', 'state' => 'logout', 'network_type' => 'WIFI',
                    'email' => '{target}',
                ],
            ],
            'rules' => [
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 200],
                    ['field' => 'json', 'path' => 'status', 'operator' => 'truthy'],
                ]], 'status' => 'Registered'],
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 200],
                    ['field' => 'json', 'path' => 'status', 'operator' => 'falsy'],
                ]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'asafeer', 'category' => 'learning', 'site_name' => 'Asafeer',
            'site_url' => 'https://3asafeer.com', 'source' => 'email_scan/learning/asafeer.py',
            'request' => [
                'method' => 'GET', 'url' => 'https://3asafeer.com/caller.php', 'timeout' => 6,
                'headers' => ['User-Agent' => 'okhttp/5.3.2'],
                'query' => ['page' => 'user', 'task' => 'forgotPass', 'eml' => '{target}', 'format' => 'json'],
            ],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'result', 'value' => 'success']]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'result', 'value' => 'failed']]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'babbel', 'category' => 'learning', 'site_name' => 'Babbel',
            'site_url' => 'https://play.google.com/store/apps/details?id=com.babbel.mobile.android.en',
            'source' => 'email_scan/learning/babbel.py',
            'unexpected_reason' => 'Babbel registration validation endpoint returned HTTP {http_status}; no account verdict',
            'request' => [
                'method' => 'POST', 'url' => 'https://api.babbel.io/gamma/v2/en/users/',
                'headers' => [
                    'User-Agent' => 'Babbel/22.4.0 (Android 30.0; Google Pixel 6; Google; Google; en_US)',
                    'x-device-udid' => '{hex32_upper}', 'x-amz-date' => '{amz_date}',
                    'Content-Type' => 'application/json; charset=UTF-8',
                ],
                'body' => ['user' => [
                    'email' => '{target}', 'locale' => '', 'learn_language_alpha3' => 'QMS',
                    'registration_date' => '', 'uuid' => '', 'authentication_token' => '',
                    'country_alpha3' => '', 'email_validated' => false, 'privacy_policy' => false,
                    'terms_and_conditions' => false, 'password' => '', 'firstname' => 'nopi',
                    'signin_token' => '', 'captcha' => [],
                ]],
            ],
            'rules' => [
                ['when' => ['all' => [
                    ['field' => 'status', 'value' => 422],
                    ['field' => 'json', 'path' => 'error.messages', 'operator' => 'array_any', 'where' => ['all' => [
                        ['field' => 'item', 'path' => 'attribute', 'value' => 'email'],
                        ['field' => 'item', 'path' => 'details', 'operator' => 'contains', 'value' => 'taken'],
                    ]]],
                ]], 'status' => 'Registered'],
                ['when' => ['field' => 'status', 'value' => 422], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'bnrlanguages', 'category' => 'learning', 'site_name' => 'Bnrlanguages',
            'site_url' => 'https://play.google.com/store/apps/details?id=com.breboucas.japonesparaviajar',
            'source' => 'email_scan/learning/bnrlanguages.py',
            'request' => [
                'method' => 'POST', 'url' => 'https://www.googleapis.com/identitytoolkit/v3/relyingparty/getOobConfirmationCode',
                'query' => ['key' => 'AIzaSyCLqZQ3hh3mAGxFRGgdBz33kV6mTGadwuU'],
                'headers' => ['User-Agent' => 'Dalvik/2.1.0 (Linux; U; Android 13; Pixel 6 Build/TP1A.220624.021)', 'Content-Type' => 'application/json', 'X-Android-Package' => 'com.breboucas.japonesparaviajar', 'X-Client-Version' => 'Android/Fallback/X23001000/FirebaseCore-Android', 'X-Firebase-Locale' => 'en_US'],
                'body' => ['requestType' => 1, 'email' => '{target}', 'androidInstallApp' => false, 'canHandleCodeInApp' => false, 'clientType' => 'CLIENT_TYPE_ANDROID'],
            ],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 400], ['field' => 'json', 'path' => 'error.message', 'value' => 'EMAIL_NOT_FOUND']]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'kind', 'value' => 'identitytoolkit#GetOobConfirmationCodeResponse']]], 'status' => 'Registered'],
            ],
        ],
        [
            'key' => 'bunpo', 'category' => 'learning', 'site_name' => 'Bunpo',
            'site_url' => 'https://play.google.com/store/apps/details?id=com.bunpoapp',
            'source' => 'email_scan/learning/bunpo.py',
            'request' => [
                'method' => 'POST', 'url' => 'https://www.googleapis.com/identitytoolkit/v3/relyingparty/getOobConfirmationCode',
                'query' => ['key' => 'AIzaSyAxg8aXqILmTAN_OOAGIRt0IL6nkQyj3p8'],
                'headers' => ['User-Agent' => 'Dalvik/2.1.0 (Linux; U; Android 13; Pixel 6 Build/TP1A.220624.021)', 'Content-Type' => 'application/json', 'X-Android-Package' => 'com.bunpoapp', 'X-Client-Version' => 'Android/Fallback/X23000000/FirebaseCore-Android'],
                'body' => ['requestType' => 1, 'email' => '{target}', 'androidInstallApp' => false, 'canHandleCodeInApp' => false, 'clientType' => 'CLIENT_TYPE_ANDROID'],
            ],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 400], ['field' => 'json', 'path' => 'error.message', 'value' => 'EMAIL_NOT_FOUND']]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'kind', 'value' => 'identitytoolkit#GetOobConfirmationCodeResponse']]], 'status' => 'Registered'],
            ],
        ],
        [
            'key' => 'cakeapp', 'category' => 'learning', 'site_name' => 'Cakeapp',
            'site_url' => 'https://play.google.com/store/apps/details?id=me.mycake',
            'source' => 'email_scan/learning/cakeapp.py',
            'request' => [
                'method' => 'GET', 'url' => 'https://api.cakeapp.me/gw/auth/email/validate',
                'headers' => ['User-Agent' => 'okhttp/4.12.0'],
                'query' => ['timezone' => '330', 'lang' => 'en', 'languageToLearn' => 'en', 'appVersion' => '6.8.0', 'os' => 'android', 'osVersion' => '11', 'email' => '{target}'],
            ],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'result', 'value' => 'SUCCESS'], ['field' => 'json', 'path' => 'data.hasError', 'operator' => 'falsy']]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'result', 'value' => 'SUCCESS'], ['field' => 'json', 'path' => 'data.hasError', 'operator' => 'truthy'], ['field' => 'json', 'path' => 'data.errorType', 'value' => 'DUPLICATED']]], 'status' => 'Registered'],
            ],
        ],
        [
            'key' => 'cambly', 'category' => 'learning', 'site_name' => 'Cambly',
            'site_url' => 'https://www.cambly.com', 'source' => 'email_scan/learning/cambly.py',
            'request' => [
                'method' => 'POST', 'url' => 'https://www.cambly.com/forgotPassword', 'timeout' => 6,
                'headers' => ['User-Agent' => 'Cambly/7.30.1 (com.cambly.cambly; Android 13)', 'Content-Type' => 'application/json; charset=UTF-8', 'x-cambly-interface-locale' => 'en_US', 'x-cambly-entity' => 'googlePlay'],
                'body' => ['email' => '{target}'],
            ],
            'rules' => [
                ['when' => ['field' => 'status', 'value' => 200], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 422], ['any' => [['field' => 'json', 'path' => 'error', 'operator' => 'contains_ci', 'value' => 'nocaptcha'], ['field' => 'json', 'path' => 'error_text', 'operator' => 'contains_ci', 'value' => 'captcha']]]]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 400], ['any' => [['field' => 'json', 'path' => 'error', 'operator' => 'contains_ci', 'value' => 'notfound'], ['field' => 'json', 'path' => 'error_text', 'operator' => 'contains_ci', 'value' => 'could not be found']]]]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'classdojo', 'category' => 'learning', 'site_name' => 'Classdojo',
            'site_url' => 'https://www.classdojo.com', 'source' => 'email_scan/learning/classdojo.py',
            'request' => [
                'method' => 'GET', 'url' => 'https://api.classdojo.com/api/user/emailValidation/{target_url}', 'timeout' => 6,
                'query' => ['skipDomainValidation' => 'false'],
                'headers' => ['User-Agent' => 'Mozilla/5.0 (Linux; Android 13; wv) AppleWebKit/537.36 Chrome/111.0.5563.116 Mobile Safari/537.36', 'x-client-identifier' => 'Android', 'x-client-version' => '8.80.0', 'x-sign-attachment-urls' => 'true', 'x-client-os-version' => '33', 'x-device-id' => 'fb125fbaf088ff2d', 'x-client-language' => 'en-US', 'x-rn-request' => 'true'],
            ],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'isAvailableForSignUp', 'operator' => 'falsy']]], 'status' => 'Registered', 'metadata_from_json' => ['entity_type' => 'entityType', 'is_passwordless' => 'isPasswordless', 'has_mandatory_sso' => 'hasMandatorySSO', 'oidc_providers' => 'oidcProviders']],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'isAvailableForSignUp', 'operator' => 'truthy']]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'hanzii', 'category' => 'learning', 'site_name' => 'Hanzii',
            'site_url' => 'https://play.google.com/store/apps/details?id=com.eup.hanzii',
            'source' => 'email_scan/learning/hanzii.py', 'semantic_statuses' => [401],
            'request' => [
                'method' => 'POST', 'url' => 'https://api.hanzii.net/api/password/reset',
                'headers' => ['User-Agent' => 'com.eup.hanzii/768 (Linux; U; Android 13; en_US; Pixel 6; Build/TP1A.220624.021; Cronet/149.0.7827.102)', 'Content-Type' => 'application/json; charset=UTF-8'],
                'body' => ['X-localization' => 'en', 'email' => '{target}'],
            ],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 401], ['field' => 'json', 'path' => 'error', 'value' => 'Email is not exist'], ['field' => 'json', 'path' => 'code', 'value' => 401]]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'message', 'value' => 'Success']]], 'status' => 'Registered'],
            ],
        ],
        [
            'key' => 'hellochinese', 'category' => 'learning', 'site_name' => 'Hellochinese',
            'site_url' => 'https://play.google.com/store/apps/details?id=com.hellochinese',
            'source' => 'email_scan/learning/hellochinese.py',
            'request' => [
                'method' => 'POST', 'url' => 'http://api3.hellochinese.cc/v1/passport/forget_password',
                'body_mode' => 'form',
                'headers' => ['User-Agent' => 'Dalvik/2.1.0 (Linux; U; Android 13; Pixel 6 Build/TP1A.220624.021)', 'Env' => '{"appVer":"7.10.35","cid":null,"device":"Pixel 6","deviceId":"{hex32}","lang":"en","locale":"en-US","network":"wifi","osVer":"13","platform":"android","time":"{time_plus0530}"}'],
                'body' => ['email' => '{target}'],
            ],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'code', 'value' => 0]]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'code', 'value' => 103]]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'heyjapan', 'category' => 'learning', 'site_name' => 'Heyjapan',
            'site_url' => 'https://heyjapan.net', 'source' => 'email_scan/learning/heyjapan.py',
            'request' => ['method' => 'POST', 'url' => 'https://v2.heyjapan.net/password/passwordCode', 'headers' => ['User-Agent' => 'okhttp/5.3.2', 'Content-Type' => 'application/json'], 'body' => ['email' => '{target}']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'message', 'value' => 'Gửi mã khôi phục qua email thành công']]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 404], ['field' => 'json', 'path' => 'message', 'value' => 'Email does not exist!']]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'lingq', 'category' => 'learning', 'site_name' => 'Lingq',
            'site_url' => 'https://www.lingq.com', 'source' => 'email_scan/learning/lingq.py',
            'request' => ['method' => 'GET', 'url' => 'https://www.lingq.com/en/accounts/validate-field/', 'query' => ['email' => '{target}']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json_root', 'operator' => 'exists'], ['field' => 'json_root', 'operator' => 'empty']]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'email.is_valid', 'operator' => 'falsy'], ['field' => 'json', 'path' => 'email.message', 'operator' => 'array_contains_ci', 'value' => 'already have an account']]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'email.is_valid', 'operator' => 'falsy'], ['field' => 'json', 'path' => 'email.message', 'operator' => 'array_contains_ci', 'value' => 'Enter a valid email address.']]], 'status' => 'Error', 'reason' => 'LingQ rejected the address as invalid; account existence cannot be determined'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'email.is_valid', 'operator' => 'falsy']]], 'status' => 'Error', 'reason' => 'Email rejected by LingQ'],
            ],
        ],
        [
            'key' => 'programminghub', 'category' => 'learning', 'site_name' => 'Programminghub',
            'site_url' => 'https://play.google.com/store/apps/details?id=com.prghub.pro',
            'source' => 'email_scan/learning/programminghub.py',
            'request' => ['method' => 'POST', 'url' => 'https://api-prod.programminghub.io/v5/api/auth/recover/initiate', 'headers' => ['User-Agent' => 'okhttp/5.3.2', 'client' => 'android', 'locale' => 'en', 'Content-Type' => 'application/json; charset=UTF-8'], 'body' => ['client' => 'android', 'email' => '{target}']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 400], ['field' => 'json', 'path' => 'status', 'operator' => 'falsy'], ['field' => 'json', 'path' => 'message', 'value' => 'You are not registered with us']]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'status', 'operator' => 'truthy'], ['field' => 'json', 'path' => 'data.user_id', 'operator' => 'exists']]], 'status' => 'Registered'],
            ],
        ],
        [
            'key' => 'quizlet', 'category' => 'learning', 'site_name' => 'Quizlet',
            'site_url' => 'https://quizlet.com', 'source' => 'email_scan/learning/quizlet.py',
            'request' => ['method' => 'POST', 'url' => 'https://api.quizlet.com/3.11/validate-email', 'timeout' => 6, 'query' => ['client_id' => 'XbSGGchEnA'], 'headers' => ['User-Agent' => 'QuizletAndroid/10.47 Dalvik/2.1.0 (Linux; U; Android 13; Pixel 6 Build/TQ3A.230901.001)', 'Content-Type' => 'application/json; charset=UTF-8'], 'body' => ['email' => '{target}']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'responses.0.data.validateEmail.isValid', 'operator' => 'falsy']]], 'status' => 'Registered', 'metadata_from_json' => ['existing_account' => 'responses.0.data.validateEmail.existingAccount']],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'responses.0.data.validateEmail.isValid', 'operator' => 'truthy']]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'speak', 'category' => 'learning', 'site_name' => 'Speak',
            'site_url' => 'https://app.speak.com', 'source' => 'email_scan/learning/speak.py',
            'request' => ['method' => 'POST', 'url' => 'https://identitytoolkit.googleapis.com/v1/accounts:createAuthUri?key=AIzaSyAbB_C9TGxqWryYcmx74MeTTeUXdUB4HU4', 'headers' => ['Origin' => 'https://app.speak.com'], 'body' => ['identifier' => '{target}', 'continueUri' => 'https://app.speak.com/us-en/register/email']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'registered', 'operator' => 'truthy']]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'registered', 'operator' => 'falsy']]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'talkme', 'category' => 'learning', 'site_name' => 'Talkme',
            'site_url' => 'https://www.talkme.ai', 'source' => 'email_scan/learning/talkme.py',
            'request' => ['method' => 'POST', 'url' => 'https://api.talkme.ai/api/user/login', 'body' => ['email' => '{target}', 'password' => '2021beab77f462ec7cf0ae4d85c5c8a2']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'errCode', 'value' => 80820053]]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'errCode', 'value' => 80820021]]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'errCode', 'value' => 80820058]]], 'status' => 'Error',
                    'reason' => 'Talkme explicit application throttle (errCode 80820058): Please wait a moment before trying again',
                    'metadata_from_json' => ['upstream_error_code' => 'errCode'],
                ],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'errCode', 'value' => 80820122]]], 'status' => 'Error',
                    'reason' => 'Talkme email delivery unavailable or service quota reached (errCode 80820122); account status unknown',
                    'metadata_from_json' => ['upstream_error_code' => 'errCode'],
                ],
            ],
            // Explicit repeated-target/service throttle or email-quota error,
            // not an IP/account verdict. Back off briefly per module and per
            // target; both windows are bounded and never retried blindly.
            'backoff' => [
                'match' => ['all' => [['field' => 'status', 'value' => 200], ['any' => [
                    ['field' => 'json', 'path' => 'errCode', 'value' => 80820058],
                    ['field' => 'json', 'path' => 'errCode', 'value' => 80820122],
                ]]]],
                'service_cooldown_seconds' => 60,
                'target_cooldown_seconds' => 300,
            ],
        ],
        [
            'key' => 'talkpal', 'category' => 'learning', 'site_name' => 'Talkpal',
            'site_url' => 'https://talkpal.ai', 'source' => 'email_scan/learning/talkpal.py',
            'request' => ['method' => 'POST', 'url' => 'https://api.talkpal.ai/api/v1/auth/recovery-request', 'headers' => ['User-Agent' => 'NitroFetch/1.0', 'Content-Type' => 'application/json', 'source' => 'app', 'source-device' => 'ANDROID'], 'body' => ['email' => '{target}', 'source' => 'app']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 404], ['field' => 'json', 'path' => 'message', 'value' => 'User not found'], ['field' => 'json', 'path' => 'statusCode', 'value' => 404]]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 201], ['field' => 'json', 'path' => 'status', 'operator' => 'truthy'], ['field' => 'json', 'path' => 'message', 'operator' => 'contains', 'value' => 'Password reset email has been sent']]], 'status' => 'Registered'],
            ],
        ],
        [
            'key' => 'dragongroot', 'category' => 'other', 'site_name' => 'Dragongroot',
            'site_url' => 'https://dragongroot.com', 'source' => 'email_scan/other/dragongroot.py',
            'request' => ['method' => 'POST', 'url' => 'https://hayat.dragongroot.com/api/auth/signup/send-otp', 'headers' => ['User-Agent' => 'okhttp/4.12.0', 'Content-Type' => 'application/json'], 'body' => ['email' => '{target}']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 400], ['field' => 'json', 'path' => 'success', 'operator' => 'falsy'], ['field' => 'json', 'path' => 'message', 'value' => 'An account with this email already exists']]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'success', 'operator' => 'truthy'], ['field' => 'json', 'path' => 'message', 'value' => 'OTP sent to your email']]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'flipboard', 'category' => 'news', 'site_name' => 'Flipboard',
            'site_url' => 'https://flipboard.com', 'source' => 'email_scan/news/flipboard.py',
            'request' => [
                'method' => 'GET', 'url' => 'https://fbprod.flipboard.com/v1/flipboard/checkEmail/0',
                'headers' => ['User-Agent' => 'Mozilla/5.0 (Linux; Android 13; Pixel 6) Flipboard/4.3.56/5508,4.3.56.5508'],
                'query' => ['email' => '{target}', 'ver' => '4.3.56.5508', 'device' => 'aphone-google-pixel-30', 'locale' => 'en_US', 'lang' => 'en', 'locale_cg' => 'en_US', 'screensize' => '6.0', 'app' => 'flipboard', 'udid' => '8610cbecde6c89b6b0cb5449f2cd9f13d1cba8f4', 'tuuid' => '52987290-d242-48a8-b10c-7b87fd8c7424'],
            ],
            'rules' => [
                ['when' => ['all' => [['field' => 'json', 'path' => 'success', 'operator' => 'truthy'], ['field' => 'json', 'path' => 'errorcode', 'value' => 3109]]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'json', 'path' => 'success', 'operator' => 'truthy'], ['field' => 'json', 'path' => 'errorcode', 'value' => 3108]]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'json', 'path' => 'success', 'operator' => 'truthy'], ['field' => 'json', 'path' => 'valid', 'operator' => 'truthy'], ['field' => 'json', 'path' => 'errorcode', 'operator' => 'empty'], ['field' => 'json', 'path' => 'code', 'value' => 200]]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'fantasia', 'category' => 'adult', 'site_name' => 'Fantasia',
            'site_url' => 'https://play.google.com/store/apps/details?id=com.api.fantachat',
            'source' => 'email_scan/adult/fantasia.py',
            'request' => [
                'method' => 'POST', 'url' => 'https://api.fantachat.ai/user/send/auth/code', 'timeout' => 20,
                'headers' => [
                    'User-Agent' => 'okhttp/4.12.0', 'x-system-region' => 'US', 'sim-state' => 'EMPTY',
                    'vpn-on' => 'true', 'ssid' => '{digits19}', 'device-model' => 'Pixel 6',
                    'accept-language' => 'en', 'x-app-name' => 'Fantasia', 'timezone' => 'GMT+0',
                    'source-platform' => 'Android', 'app-version' => '255', 'app-version-code' => '255',
                    'device-no' => '{hex33}', 'fingerprint' => '{hex33}', 'authtoken' => '',
                    'Content-Type' => 'application/json; charset=UTF-8',
                ],
                'body' => ['email' => '{target}', 'methodType' => 2],
            ],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'code', 'value' => '304'], ['field' => 'json', 'path' => 'message', 'value' => 'User does not exist']]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'code', 'value' => '0'], ['field' => 'json', 'path' => 'message', 'value' => 'SUCCESS']]], 'status' => 'Registered'],
            ],
        ],
        [
            'key' => 'faproulette', 'category' => 'adult', 'site_name' => 'Faproulette',
            'site_url' => 'https://faproulette.co', 'source' => 'email_scan/adult/faproulette.py',
            'request' => [
                'method' => 'POST', 'url' => 'https://faproulette.co/api/userCheckEmail', 'body_mode' => 'form',
                'headers' => ['User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 'X-Requested-With' => 'XMLHttpRequest', 'Origin' => 'https://faproulette.co', 'Referer' => 'https://faproulette.co/'],
                'body' => ['email' => '{target}', 'timeoutID' => 1],
            ],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'success', 'value' => 1]]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'error', 'operator' => 'contains_ci', 'value' => 'already in use']]], 'status' => 'Registered'],
            ],
        ],
        [
            'key' => 'lespark', 'category' => 'dating', 'site_name' => 'Lespark',
            'site_url' => 'https://www.lespark.cn', 'source' => 'email_scan/dating/lespark.py',
            'request' => [
                'method' => 'POST', 'url' => 'https://api3.lespark.cn/login', 'timeout' => 6, 'body_mode' => 'form',
                'headers' => [
                    'User-Agent' => 'okhttp-okgo/jeasonlzy', 'accept-language' => 'en-US,en;q=0.8',
                    'lang' => 'en', 'lang-app' => 'en', 'device-os' => '13', 'device_model' => 'Pixel 6',
                    'bundle-id' => 'com.redwolfama.peonylespark.gp', 'version' => '9.7.38.1',
                    'version-code' => '786', 'pkg' => 'com.redwolfama.peonylespark.gp', 'device-id' => '{hex16}',
                    'is-ipad' => '0', 'locale' => 'US', 'is-cn' => '0', 'channel' => 'google',
                    'request-id' => '{uuid}', 'gaid' => '{uuid2}',
                ],
                'body' => ['password' => 'd9bd8afc54ee5dc6c1ee6096e297ec31', 'verion' => '1', 'email' => '{target}', 'luid' => '', 'request-id' => '{uuid}'],
            ],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'msg', 'operator' => 'contains_ci', 'value' => 'password is wrong']]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'msg', 'operator' => 'contains_ci', 'value' => 'user does not exist']]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'locanto', 'category' => 'dating', 'site_name' => 'Locanto',
            'site_url' => 'https://www.locanto.org', 'source' => 'email_scan/dating/locanto.py',
            'request' => [
                'method' => 'POST', 'url' => 'https://www.locanto.org/api/ajax/general', 'timeout' => 6, 'body_mode' => 'form',
                'headers' => ['User-Agent' => 'Mozilla/5.0 (Linux; Android 13; Pixel 6 Build/TQ3A.230901.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/111.0.5563.116 Safari/537.36 YalwApp/eL.4.2.83', 'X-Requested-With' => 'XMLHttpRequest', 'Origin' => 'https://www.locanto.org', 'Referer' => 'https://www.locanto.org/g/dol/signup/?continue=https%3A%2F%2Fwww.locanto.org%2Fg%2Fdol%2Fdiscover%2F'],
                'body' => ['action' => 'register_attempt', 'email' => '{target}', 'is_dol' => 'true'],
            ],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'success', 'operator' => 'truthy'], ['field' => 'json', 'path' => 'email_exists', 'operator' => 'truthy']]], 'status' => 'Error', 'reason' => 'Locanto returned contradictory account flags; account status unknown'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'success', 'operator' => 'truthy']]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['any' => [['field' => 'json', 'path' => 'email_exists', 'operator' => 'truthy'], ['field' => 'json', 'path' => 'text', 'operator' => 'contains_ci', 'value' => 'already exists']]]]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'text', 'operator' => 'contains_ci', 'value' => 'something went wrong']]], 'status' => 'Error', 'reason' => 'Locanto returned a generic registration error; account status unknown'],
            ],
        ],
        [
            'key' => 'skout', 'category' => 'dating', 'site_name' => 'Skout',
            'site_url' => 'https://www.skout.com', 'source' => 'email_scan/dating/skout.py',
            'request' => [
                'method' => 'GET', 'url' => 'https://api.skout.com/validations/Users_NewUserCheck', 'timeout' => 6,
                'query' => ['email' => '{target}', 'name' => 'notSet', 'brand' => 'skout'],
                'headers' => [
                    'User-Agent' => 'Skout/23201 Dalvik/2.1.0 (Linux; U; Android 13; Pixel 6 Build/TQ3A.230901.001) 4.12.0',
                    'kissapi-ad-id' => 'c66aefe62fa926b6e76dc1ebef756baf', 'kissapi-ad-name' => 'Organic',
                    'kissapi-app-version' => '23201', 'kissapi-version' => '1.36.0', 'kissapi-device-os' => '33',
                    'kissapi-device-model' => 'Pixel 6', 'tz' => 'UTC', 'kissapi-device' => 'android',
                    'kissapi-app-package-id' => 'com.skout.android', 'kissapi-brand' => 'skout',
                    'kissapi-apptype' => 'google', 'kissapi-android-id' => '{hex16}',
                    'kissapi-app-id' => '{uuid}?identifier=skout.prod', 'kissapi-gpsa-id' => '{uuid2}?identifier=skout.prod',
                    'kissapi-gpsa-on' => 'true', 'kissapi-appset-id' => '{uuid3}?identifier=skout.prod',
                    'firebase-analytics-app-instance-id' => '{hex32}', 'accept-language' => 'en-US', 'wifi' => '0',
                ],
            ],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 409], ['field' => 'json', 'path' => 'statusMessage', 'operator' => 'contains_ci', 'value' => 'email already exists']]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'statusCode', 'value' => 200]]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'medium', 'category' => 'dev', 'site_name' => 'Medium',
            'site_url' => 'https://medium.com', 'source' => 'email_scan/dev/medium.py',
            'request' => [
                'method' => 'POST', 'url' => 'https://medium.com/_/graphql', 'timeout' => 20,
                'headers' => ['User-Agent' => 'donkey/4.5.1302444 (Phone; Google Pixel 6; channel:playstore; os/33)', 'Content-Type' => 'application/json', 'x-apollo-can-be-batched' => 'false', 'accept' => 'multipart/mixed;deferSpec=20220824, application/graphql-response+json, application/json', 'x-obvious-cid' => 'android', 'x-client-date' => '{epoch_ms}', 'accept-language' => 'en', 'cache-control' => 'public, max-age=60'],
                'body' => [
                    'operationName' => 'SendAccountAuthEmailMutation',
                    'variables' => ['email' => '{target}', 'captchaValue' => '', 'operation' => 'login', 'type' => 'LOGIN_CODE', 'rememberMe' => true],
                    'query' => 'mutation SendAccountAuthEmailMutation($email: String!, $captchaValue: String, $operation: String, $type: AuthEmailFlowType!, $redirect: String, $fullName: String, $rememberMe: Boolean!) { sendAcctAuthEmail(email: $email, captchaValue: $captchaValue, operation: $operation, type: $type, redirect: $redirect, fullName: $fullName, rememberMe: $rememberMe) { __typename ... on SusiMethod { value } ... on BadRequest { message } ... on FailedChallenge { message } ... on NotFound { message } } }',
                    'extensions' => ['clientLibrary' => ['name' => 'apollo-kotlin', 'version' => '4.4.1']],
                ],
            ],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'data.sendAcctAuthEmail.__typename', 'value' => 'NotFound'], ['field' => 'json', 'path' => 'data.sendAcctAuthEmail.message', 'value' => 'User does not exist']]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'data.sendAcctAuthEmail.__typename', 'value' => 'SusiMethod'], ['field' => 'json', 'path' => 'data.sendAcctAuthEmail.value', 'value' => 'login']]], 'status' => 'Registered'],
            ],
        ],
        [
            'key' => 'dreame', 'category' => 'entertainment', 'site_name' => 'Dreame',
            'site_url' => 'https://www.dreame.com', 'source' => 'email_scan/entertainment/dreame.py',
            'request' => [
                'method' => 'POST', 'url' => 'https://wap-api.dreame.com/Login/loginWithEmail', 'timeout' => 6,
                'headers' => ['User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', 'Origin' => 'https://www.dreame.com', 'Referer' => 'https://www.dreame.com/'],
                'query' => ['systemFlag' => 'android', 'channel' => 'dreamepmian-173', 'product' => '1', 'osType' => '2', 'userKey' => '', 'language' => 'en', 'user' => '{target}', 'password' => 'jnW1Pjcoiyrg+dkxNw8SsMdrWC3Iho1qwaUEwXKeC5cXvvySR0iaYJLLYBNSceDB6KSQoNn9BVk9ZzIpSr8iAkwnNp/Ou0HbXcPXZiLK6t+b/DvlWVxaadaOke15mibcYWgg82C8BBWSHMX6NyWs2YkVitTlWVCZ22rNz1ahFaQ='],
                'body' => [],
            ],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'msg', 'operator' => 'contains_ci', 'value' => 'account or password is incorrect']]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'msg', 'operator' => 'contains_ci', 'value' => 'has not been registered']]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'hoichoi', 'category' => 'entertainment', 'site_name' => 'Hoichoi',
            'site_url' => 'https://www.hoichoi.tv', 'source' => 'email_scan/entertainment/hoichoi.py',
            'request' => [
                'method' => 'POST', 'url' => 'https://prod-api.hoichoi.dev/core/api/v1/auth/signinup/code',
                'query' => ['platform' => 'ANDROID_MOBILE', 'language' => 'english', 'appVersion' => '3.1.44', 'deviceId' => '{hex16}'],
                'headers' => ['User-Agent' => 'Mozilla/5.0 (Linux; Android 13; wv) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', 'x-hoichoi-siteid' => 'hoichoitv', 'x-bypass-proxy' => 'true', 'Content-Type' => 'application/json'],
                'body' => ['email' => '{target}', 'deviceId' => '{hex16}'],
            ],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 400], ['field' => 'json', 'path' => 'errorCode', 'value' => 'EMAIL_SIGNUP_NOT_SUPPORTED']]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'status', 'value' => 'OK'], ['field' => 'json', 'path' => 'flowType', 'value' => 'USER_INPUT_CODE']]], 'status' => 'Registered'],
            ],
        ],
        [
            'key' => 'sunnxt', 'category' => 'entertainment', 'site_name' => 'Sunnxt',
            'site_url' => 'https://www.sunnxt.com', 'source' => 'email_scan/entertainment/sunnxt.py',
            'request' => [
                'method' => 'GET', 'url' => 'https://api.sunnxt.com/user/v2/userAccountStatus/', 'timeout' => 20,
                'query' => ['userid' => '{target}', 'partner_id' => '', 'source' => ''],
                'headers' => ['User-Agent' => 'Dalvik/2.1.0 (Linux; U; Android 13; Pixel 6 Build/TP1A.220624.021)', 'clientkey' => '1bd3195692e434d1ad72ad4db17e7b00841d6ef0f16616cfe33d8bb2e5a719e3', 'accept-language' => 'en', 'contentlanguage' => 'english', 'x-myplex-platform' => 'android', 'appversion' => '200380', 'x-myplex-maturity-level' => '', 'x-ucv' => '5'],
            ],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'status', 'value' => 'SUCCESS'], ['field' => 'json', 'path' => 'user_available', 'operator' => 'falsy']]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'status', 'value' => 'SUCCESS'], ['field' => 'json', 'path' => 'user_available', 'operator' => 'truthy']]], 'status' => 'Registered', 'metadata_from_json' => ['subscription status' => 'subscription_status', 'partner id' => 'partner_id', 'password available' => 'password_available', 'account type' => 'login_account_type']],
            ],
        ],
        [
            'key' => 'weverse', 'category' => 'entertainment', 'site_name' => 'Weverse',
            'site_url' => 'https://weverse.io', 'source' => 'email_scan/entertainment/weverse.py',
            'request' => [
                'method' => 'POST', 'url' => 'https://sdk.weverse.io/api/v1/auth/password-reset/otp-sessions', 'timeout' => 6,
                'headers' => ['User-Agent' => 'AppName/weverse AppVersion/4.5.0 BundleId/co.benx.weverse OS/Android SystemVersion/Android13 DeviceModel/Pixel 6', 'x-sdk-language' => 'en', 'x-clog-producer' => 'account-android', 'x-sdk-service-secret' => '9d79660be5ca452ab8c93bcaee310bb7', 'x-clog-user-device-id' => '{hex30}', 'x-sdk-platform' => 'Android', 'x-sdk-device-model' => 'Pixel 6', 'x-sdk-app-version' => '3.17.0', 'x-sdk-platform-version' => '13', 'x-sdk-service-id' => 'weverse', 'x-sdk-trace-id' => '{hex32_upper}', 'x-sdk-version' => '4.5.0', 'Content-Type' => 'application/json; charset=UTF-8'],
                'body' => ['email' => '{target}'],
            ],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'otpSessionId', 'operator' => 'exists']]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 404], ['field' => 'json', 'path' => 'message', 'operator' => 'contains_ci', 'value' => 'account does not exist']]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'hautesauce', 'category' => 'shopping', 'site_name' => 'Hautesauce',
            'site_url' => 'https://www.buyhautesauce.com', 'source' => 'email_scan/shopping/hautesauce.py',
            'request' => [
                'method' => 'POST', 'url' => 'https://www.buyhautesauce.com/api/2024-04/graphql.json', 'timeout' => 6,
                'headers' => ['User-Agent' => 'okhttp/4.12.0', 'Accept' => 'application/graphql+json, application/json', 'Content-Type' => 'application/json', 'x-shopify-storefront-access-token' => '7b89272b5ed5a3ff00ad881bf63b130a'],
                'body' => [
                    'query' => 'mutation customerCreate($input: CustomerCreateInput!) { customerCreate(input: $input) { customer { id firstName lastName acceptsMarketing email } customerUserErrors { field message code } } }',
                    'operationName' => 'customerCreate',
                    'variables' => ['input' => ['acceptsMarketing' => false, 'email' => '{target}', 'password' => '', 'firstName' => 'Lost', 'lastName' => 'Knight']],
                ],
            ],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'data.customerCreate.customerUserErrors', 'operator' => 'array_any', 'where' => ['any' => [['field' => 'item', 'path' => 'code', 'value' => 'TAKEN'], ['field' => 'item', 'path' => 'message', 'operator' => 'contains_ci', 'value' => 'already been taken'], ['field' => 'item', 'path' => 'message', 'operator' => 'contains_ci', 'value' => 'already exists']]]]]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'data.customerCreate.customerUserErrors', 'operator' => 'array_any', 'where' => ['field' => 'item', 'path' => 'message', 'operator' => 'contains_ci', 'value' => 'verify your email address']]]], 'status' => 'Registered', 'metadata' => ['is_verified' => 'False']],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'data.customerCreate.customerUserErrors', 'operator' => 'array_any', 'where' => ['any' => [['field' => 'item', 'path' => 'code', 'value' => 'BLANK'], ['field' => 'item', 'path' => 'message', 'operator' => 'contains_ci', 'value' => 'password'], ['field' => 'item', 'path' => 'field', 'operator' => 'contains', 'value' => 'password']]]]]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'tatacliq', 'category' => 'shopping', 'site_name' => 'Tatacliq',
            'site_url' => 'https://tatadigital.com', 'source' => 'email_scan/shopping/tatacliq.py',
            'request' => [
                'method' => 'POST', 'url' => 'https://api.tatadigital.com/api/v2/sso/check-email', 'timeout' => 6,
                'headers' => ['User-Agent' => 'okhttp/5.2.0', 'client_id' => 'TATACLIQ-ANDROID-APP', 'appversion' => '103.22.0', 'appplatform' => 'android', 'Content-Type' => 'application/json; charset=UTF-8'],
                'body' => ['email' => '{target}', 'sendOtp' => false],
            ],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['any' => [['field' => 'json', 'path' => 'userType', 'operator' => 'in', 'value' => ['existing', 'migrated']], ['field' => 'json', 'path' => 'emailEnrolled', 'operator' => 'truthy']]]]], 'status' => 'Registered', 'metadata_from_json' => ['user_type' => 'userType', 'email_enrolled' => 'emailEnrolled', 'email_verified' => 'emailVerified', 'phone_masked' => 'phoneMasked']],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['any' => [['field' => 'json', 'path' => 'userType', 'value' => 'new'], ['field' => 'json', 'path' => 'message', 'value' => 'User is not found']]]]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'locket', 'category' => 'social', 'site_name' => 'Locket',
            'site_url' => 'https://locketcamera.com', 'source' => 'email_scan/social/locket.py',
            'request' => [
                'method' => 'POST', 'url' => 'https://api.locketcamera.com/validateEmailAddress', 'timeout' => 6,
                'headers' => ['User-Agent' => 'okhttp/4.12.0', 'Content-Type' => 'application/json; charset=utf-8'],
                'body' => ['data' => [
                    'analytics' => [
                        'amplitude' => ['device_id' => '{uuid}R', 'session_id' => '{epoch_ms}'],
                        'experiments' => ['android_flag_2' => 800, 'android_flag_1' => 0, 'android_flag_9' => 0, 'android_flag_8' => 807, 'android_flag_7' => 200, 'android_flag_6' => 200, 'android_flag_5' => 403, 'android_flag_4' => 0, 'android_flag_10' => 0, 'android_flag_3' => 0],
                        'google_analytics' => ['app_instance_id' => '{hex32}'], 'android_version' => '1.234.2', 'android_build' => '577', 'platform' => 'android',
                    ],
                    'operation' => 'sign_in', 'email' => '{target}',
                ]],
            ],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'result.needs_registration', 'operator' => 'falsy'], ['field' => 'json', 'path' => 'result.status', 'value' => 200]]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['any' => [['field' => 'json', 'path' => 'result.status', 'value' => 601], ['field' => 'json', 'path' => 'result.error', 'operator' => 'contains_ci', 'value' => 'does not exist']]]]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'lovenudge', 'category' => 'social', 'site_name' => 'Lovenudge',
            'site_url' => 'https://lovenudgeapp.com', 'source' => 'email_scan/social/lovenudge.py',
            'request' => ['method' => 'GET', 'url' => 'https://api.lovenudgeapp.com/api/register/check', 'timeout' => 6, 'headers' => ['User-Agent' => 'okhttp/4.11.0', 'Content-Type' => 'application/json'], 'query' => ['email' => '{target}', 'time_zone' => 'UTC', 'app_version' => '5.2.10']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'data', 'operator' => 'truthy']]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'data', 'operator' => 'falsy']]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'meeff', 'category' => 'social', 'site_name' => 'Meeff',
            'site_url' => 'https://play.google.com/store/apps/details?id=com.noyesrun.meeff.kr', 'source' => 'email_scan/social/meeff.py',
            'semantic_statuses' => [401],
            'request' => [
                'method' => 'POST', 'url' => 'https://api.meeff.com/user/login/v4',
                'headers' => ['User-Agent' => 'okhttp/5.3.2', 'Content-Type' => 'application/json; charset=utf-8', 'Cookie' => 'locale=en'],
                'body' => ['provider' => 'email', 'providerId' => '{target}', 'providerToken' => '', 'os' => 'Android v11', 'platform' => 'android', 'device' => 'BRAND: VIVO, MODEL: VIVOY16, DEVICE: VIVOY16, PRODUCT: VIVOY16, DISPLAY: VIVOY!6_13_K.1098', 'pushToken' => '', 'deviceUniqueId' => '{hex16}', 'deviceLanguage' => 'en', 'deviceRegion' => 'US', 'simRegion' => '', 'deviceGmtOffset' => '+0530', 'deviceRooted' => 0, 'deviceEmulator' => 0, 'appVersion' => '7.1.2', 'locale' => 'en'],
            ],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 401], ['field' => 'json', 'path' => 'errorMessage', 'operator' => 'contains_ci', 'value' => "hasn't been signed up yet"]]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 401], ['field' => 'json', 'path' => 'errorMessage', 'operator' => 'contains_ci', 'value' => 'passwords do not match']]], 'status' => 'Registered'],
            ],
        ],
        [
            'key' => 'slowly', 'category' => 'social', 'site_name' => 'Slowly',
            'site_url' => 'https://getslowly.com', 'source' => 'email_scan/social/slowly.py',
            'request' => [
                'method' => 'POST', 'url' => 'https://api.getslowly.com/auth/email/passcode',
                'headers' => ['User-Agent' => 'okhttp/4.11.0', 'Content-Type' => 'application/json'],
                'body' => ['email' => '{target}', 'device' => '{"platform":"android","bundleId":"com.slowlyapp","device":"Pixel6","deviceType":"Handset","brand":"Google","system":"13","res":"1080x2400","ui_language":"en","deviceLocales":[{"isRTL":false,"languageTag":"en-US","countryCode":"US","languageCode":"en"}],"currency":["USD"],"timezone":"America/New_York","jb":false,"hook":false,"adb":false,"canMock":false,"externalStorage":false,"uuid":"{hex16}","carrier":"","serial":"unknown","locationEnabled":false,"isEmulator":false,"fontScale":1,"pushPermission":"granted","networkType":"wifi","version":"9.5.2"}', 'checkpass' => true],
            ],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 400], ['field' => 'json', 'path' => 'error', 'value' => 'email_not_found']]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'success', 'operator' => 'in', 'value' => ['email_sent', 'email_already_sent']]]], 'status' => 'Registered'],
            ],
        ],
        [
            'key' => 'superlive', 'category' => 'social', 'site_name' => 'Superlive',
            'site_url' => 'https://superlive.io', 'source' => 'email_scan/social/superlive.py',
            'request' => [
                'method' => 'POST', 'url' => 'https://api.sskkjp1l6cx7.link/api/v1/user/signup/send_password_renewal_code', 'timeout' => 6,
                'headers' => ['User-Agent' => 'SuperLive/2.38.1 (Pixel 6; Android 13; Scale/mdpi)', 'device-id' => '08f890cd6e0b9235cde1d50522f5f4ed', 'Content-Type' => 'application/json; charset=UTF-8'],
                'body' => ['email' => '{target}', 'force_new' => true, 'client_params' => ['adid' => 'c66aefe62fa926b6e76dc1ebef756baf', 'android_id' => '1db33b89299a9d90', 'app_language' => 'en', 'brand_name' => 'google', 'device_language' => 'en', 'device_preferred_languages' => ['en'], 'initial_country' => 'US', 'model_name' => 'Pixel 6', 'network_type' => 'WIFI', 'os_type' => 'android']],
            ],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 400], ['field' => 'json', 'path' => 'error.message', 'operator' => 'contains_ci', 'value' => 'no registered users with this email']]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'password_renewal_id', 'operator' => 'exists']]], 'status' => 'Registered'],
            ],
        ],
        [
            'key' => 'besoccer', 'category' => 'sports', 'site_name' => 'Besoccer',
            'site_url' => 'https://www.besoccer.com/', 'source' => 'email_scan/sports/besoccer.py',
            'request' => ['method' => 'GET', 'url' => 'https://fast.okcats.com/scripts/api/api.php', 'headers' => ['User-Agent' => 'okhttp/4.12.0'], 'query' => ['key' => 'b3fcd6725e03f4e5d588f6624cac5522', 'format' => 'json', 'site' => 'ResultadosAndroid', 'appCountry' => '', 'lang' => 'en-US', 'req' => 'register', 'device' => 'android', 'user' => 'scanner_user_{digits7}', 'email' => '{target}', 'password' => '']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'errors', 'operator' => 'array_any', 'where' => ['field' => 'item', 'path' => 'message', 'value' => 'Email is already in use']]]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'errors', 'operator' => 'array_any', 'where' => ['any' => [['field' => 'item', 'path' => 'message', 'value' => 'You must introduce a valid email address'], ['field' => 'item', 'path' => 'message', 'value' => 'You must introduce a password']]]]]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'playtomic', 'category' => 'sports', 'site_name' => 'Playtomic',
            'site_url' => 'https://playtomic.io/', 'source' => 'email_scan/sports/playtomic.py',
            'request' => ['method' => 'GET', 'url' => 'https://api.app.playtomic.io/v3/auth/methods', 'headers' => ['User-Agent' => 'Android 11', 'accept-language' => 'en', 'Content-Type' => 'application/json', 'x-requested-with' => 'com.playtomic.app 6.79.0'], 'query' => ['email' => '{target}']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'action_required', 'operator' => 'falsy']]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'action_required', 'operator' => 'truthy']]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'femometer', 'category' => 'women_health', 'site_name' => 'Femometer',
            'site_url' => 'https://www.femometer.com', 'source' => 'email_scan/women_health/femometer.py',
            'request' => ['method' => 'GET', 'url' => 'https://api.femometer.com/v1/user_location', 'timeout' => 6, 'headers' => ['Host' => 'api-us.femometer.com', 'User-Agent' => 'okhttp/4.12.0', 'Content-Type' => 'application/json; charset=utf-8'], 'query' => ['country_code' => '0', 'phone_no' => '0', 'email' => '{target}', 'app_flag' => '1', 'language' => '2', 'localTimezone' => 'UTC', 'app_version' => '5.43.5(4260)', 'platform_type' => '1', 'isGooglePlay' => 'true']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'userId', 'value' => 0], ['field' => 'json', 'path' => 'userLocation', 'value' => 0]]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'userId', 'operator' => 'neq', 'value' => 0], ['field' => 'json', 'path' => 'userId', 'operator' => 'exists']]], 'status' => 'Registered', 'metadata_from_json' => ['user id' => 'userId', 'server location' => 'url']],
            ],
        ],
        [
            'key' => 'glow', 'category' => 'women_health', 'site_name' => 'Glow',
            'site_url' => 'https://glowing.com', 'source' => 'email_scan/women_health/glow.py',
            'request' => [
                'method' => 'POST', 'url' => 'https://account.glowing.com/a/v3/users/signup_email', 'timeout' => 6, 'body_mode' => 'form',
                'query' => ['fc' => '1', 'random' => '{digits12}', 'android_version' => '11.22.1', 'unit' => 'imperial'],
                'headers' => ['Host' => 'glow.glowing.com', 'User-Agent' => 'okhttp/4.9.3', 'app-version' => '11.22.1', 'build-number' => '112201', 'bundle-id' => 'com.glow.android', 'client-platform' => 'android', 'device-id' => '{hex16}', 'device-model' => 'Google Pixel 6', 'glowoccurtime' => '{epoch_ms}', 'language' => 'en', 'platform' => 'android', 'platform-version' => '33', 'rn-version' => '5.0.3', 'time-zone' => 'UTC', 'x-app-code-name' => 'emma', 'x-app-device-id' => '{hex16}', 'x-app-locale' => 'en_US', 'x-app-timezone' => 'UTC', 'x-app-version-code' => '112201', 'x-platform' => 'Android', 'x-sub-app' => 'glow', 'x-sub-app-version' => '11.22.1'],
                'body' => ['email' => '{target}'],
            ],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'data.valid', 'value' => 1]]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['any' => [['field' => 'json', 'path' => 'data.valid', 'value' => 0], ['field' => 'json', 'path' => 'msg', 'operator' => 'contains_ci', 'value' => 'already been taken']]]]], 'status' => 'Registered'],
            ],
        ],
        [
            'key' => 'iyoni', 'category' => 'women_health', 'site_name' => 'Iyoni',
            'site_url' => 'https://play.google.com/store/apps/details?id=pl.lifebite.iyoni', 'source' => 'email_scan/women_health/iyoni.py',
            'request' => ['method' => 'POST', 'url' => 'https://www.googleapis.com/identitytoolkit/v3/relyingparty/verifyPassword', 'timeout' => 6, 'query' => ['key' => 'AIzaSyCBpmgku8MoHzusfTQlPwMcUObhPGUHc-w'], 'headers' => ['User-Agent' => 'Dalvik/2.1.0 (Linux; U; Android 13; Pixel 6 Build/TP1A.220624.021)', 'Content-Type' => 'application/json', 'X-Android-Package' => 'pl.lifebite.iyoni', 'Accept-Language' => 'en-US'], 'body' => ['email' => '{target}', 'password' => 'generic_password_123', 'returnSecureToken' => true, 'clientType' => 'CLIENT_TYPE_ANDROID']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 400], ['field' => 'json', 'path' => 'error.message', 'value' => 'EMAIL_NOT_FOUND']]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 400], ['field' => 'json', 'path' => 'error.message', 'value' => 'INVALID_PASSWORD']]], 'status' => 'Registered'],
            ],
        ],
        [
            'key' => 'meetyou', 'category' => 'women_health', 'site_name' => 'Meetyou',
            'site_url' => 'https://www.meetyouintl.com', 'source' => 'email_scan/women_health/meetyou.py',
            'request' => ['method' => 'GET', 'url' => 'https://ad.meetyouintl.com/v2/email_check', 'timeout' => 6, 'query' => ['email' => '{target}', 'v' => '5.0.1', 'platform' => 'android'], 'headers' => ['Host' => 'users.meetyouintl.com', 'User-Agent' => 'com.meetyou.intl/5.0.1 MeetYouClient/2.0.0 (2930501020100000)', 'country' => 'US', 'open-person-ad' => '1', 'bundleid' => '201', 'platform' => 'android', 'mode' => '3', 'syslang' => 'en-US', 'zone' => '0', 'is-em' => '0000300-NONE', 'client' => '0', 'simcountry' => 'US', 'lang' => 'en-US', 'uregion' => 'singapore', 'version' => '5.0.1', 'myclient' => '2930501020100000', 'clang' => 'en-US', 'v' => '5.0.1', 'channel_id' => '201', 'syscountry' => 'US', 'session-id' => '{epoch_ms}']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'code', 'value' => 0], ['not' => ['field' => 'json', 'path' => 'message', 'operator' => 'contains_ci', 'value' => 'registered']]]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['any' => [['field' => 'json', 'path' => 'code', 'value' => 11001130], ['field' => 'json', 'path' => 'message', 'operator' => 'contains_ci', 'value' => 'registered']]]]], 'status' => 'Registered'],
            ],
        ],
        [
            'key' => 'myperiodtracker', 'category' => 'women_health', 'site_name' => 'Myperiodtracker',
            'site_url' => 'https://period-tracker.com', 'source' => 'email_scan/women_health/myperiodtracker.py',
            'request' => ['method' => 'POST', 'url' => 'https://data.period-tracker.com/databackup/data.php', 'timeout' => 6, 'body_mode' => 'form', 'query' => ['action' => 'checkAccount'], 'headers' => ['User-Agent' => 'Dart/3.10 (dart:io)'], 'body' => ['email' => '{target}', 'app_type' => 'calendar', 'app_platform' => 'android', 'data_version' => '33', 'app_version' => '12.7.6']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'status', 'value' => 'NoAccount']]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'status', 'value' => 'Success']]], 'status' => 'Registered'],
            ],
        ],
        [
            'key' => 'premom', 'category' => 'women_health', 'site_name' => 'Premom',
            'site_url' => 'https://premom.com', 'source' => 'email_scan/women_health/premom.py',
            'semantic_statuses' => [510],
            'request' => [
                'method' => 'POST', 'url' => 'https://api.premom.com/user/user/sign/up', 'timeout' => 6,
                'headers' => ['User-Agent' => 'Premom/1.104.0 (Google Pixel 6; Android 13; GMT+00:00)', 'Content-Type' => 'application/json', 'country' => 'US', 'appVersion' => '1.104.0', 'os' => 'Android 13', 'city' => 'New York', 'timezone' => 'America/New_York', 'Charset' => 'UTF-8', 'language' => '0', 'appVersionCode' => '388', 'System-Language' => 'en', 'system-language-code' => 'en_US', 'apiVersion' => '99', 'InstallId' => '{hex23}', 'device' => 'Pixel 6', 'System-Country' => 'US'],
                'body' => ['bindAnonymous' => false, 'anonymousId' => '{anonymous_id}', 'password' => 'generic_password_123', 'protocolInfo' => [['protocolType' => 'TERMS_OF_SERVICE', 'protocolUrl' => 'https://api.premom.com/app/webview/protocol?version=38&protocolType=TERMS_OF_SERVICE', 'publishDate' => '2025-12-25 15:47:01', 'version' => 38], ['protocolType' => 'PRIVACY_POLICY', 'protocolUrl' => 'https://api.premom.com/app/webview/protocol?version=76&protocolType=PRIVACY_POLICY', 'publishDate' => '2026-02-16 14:27:45', 'version' => 76]], 'OSType' => 'Pixel6_33', 'phoneID' => '{uuid}', 'processType' => '', 'email' => '{target}'],
            ],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 510], ['any' => [['field' => 'json', 'path' => 'code', 'value' => 'SIGN_DUPLICATED_EMAIL'], ['field' => 'json', 'path' => 'message', 'operator' => 'contains_ci', 'value' => 'already been registered']]]]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 510], ['any' => [['field' => 'json', 'path' => 'code', 'value' => 'ERR_VALIDATE_ERROR'], ['field' => 'json', 'path' => 'message', 'operator' => 'contains_ci', 'value' => 'empty first name']]]]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'womanlog', 'category' => 'women_health', 'site_name' => 'Womanlog',
            'site_url' => 'https://womanlog.com', 'source' => 'email_scan/women_health/womanlog.py',
            'request' => ['method' => 'POST', 'url' => 'https://proactiveapp.com/account/login_native_v1', 'timeout' => 6, 'body_mode' => 'form', 'headers' => ['User-Agent' => 'Dalvik/2.1.0 (Linux; U; Android 13; WayDroid x86_64 Device Build/TQ3A.230901.001)'], 'body' => ['email' => '{target}', 'password' => 'dummy_password_xyz123', 'package' => 'com.womanlog', 'app_version' => '7.3.5', 'os' => 'A']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'error_code', 'value' => 'INCORRECT_PASSWORD']]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'error_code', 'value' => 'EMAIL_NOT_FOUND']]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'canva', 'category' => 'creator', 'site_name' => 'Canva',
            'site_url' => 'https://www.canva.com/', 'source' => 'email_scan/creator/canva.py',
            'json_from_braces' => true, 'transport_profile' => 'chrome120',
            'request' => ['method' => 'POST', 'url' => 'https://www.canva.com/_ajax/authnflow/user-authentication-methods', 'body_mode' => 'raw', 'headers' => ['Referer' => 'https://www.canva.com/login', 'X-Requested-With' => 'XMLHttpRequest', 'Content-Type' => 'application/json', 'Accept' => 'application/json, text/plain, */*'], 'body' => '{"C":"Linux / Chrome","A?":"E","L":"{target_json}"}'],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'body', 'operator' => 'contains_ci', 'value' => '<title>Client Challenge</title>']]], 'status' => 'Error', 'reason' => 'Canva returned an anti-bot client challenge (HTTP 200); account status unknown'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'B.A?', 'value' => 'A']]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'B.A?', 'value' => 'B']]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'figma', 'category' => 'creator', 'site_name' => 'Figma',
            'site_url' => 'https://www.figma.com/', 'source' => 'email_scan/creator/figma.py',
            'transport_profile' => 'chrome120',
            'request' => ['method' => 'GET', 'url' => 'https://www.figma.com/api/session/available_auth_methods', 'headers' => ['Referer' => 'https://www.figma.com/signup', 'Accept' => 'application/json, text/plain, */*'], 'query' => ['email' => '{target}', 'form_intent' => 'sign_up']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'error', 'operator' => 'neq', 'value' => true], ['field' => 'json', 'path' => 'meta.available_methods', 'operator' => 'contains', 'value' => 'sign_in']]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'error', 'operator' => 'neq', 'value' => true], ['field' => 'json', 'path' => 'meta.available_methods', 'operator' => 'contains', 'value' => 'sign_up']]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'atlassian', 'category' => 'dev', 'site_name' => 'Atlassian',
            'site_url' => 'https://id.atlassian.com', 'source' => 'email_scan/dev/atlassian.py',
            'transport_profile' => 'chrome',
            'request' => ['method' => 'POST', 'url' => 'https://id.atlassian.com/rest/check-username', 'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json', 'Referer' => 'https://id.atlassian.com/login', 'Accept-Language' => 'en-US,en;q=0.9'], 'body' => ['username' => '{target}']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'action', 'value' => 'signup']]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'action', 'value' => 'no_action']]], 'status' => 'Registered', 'metadata' => ['auth_type' => 'Password / Social']],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'action', 'value' => 'social_login']]], 'status' => 'Registered', 'metadata' => ['auth_type' => 'Social']],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'action', 'value' => 'redirect']]], 'status' => 'Registered', 'metadata' => ['auth_type' => 'SSO / SAML'], 'metadata_from_json' => ['sso_type' => 'redirect_type']],
            ],
        ],
        [
            'key' => 'annaabi', 'category' => 'learning', 'site_name' => 'Annaabi',
            'site_url' => 'https://annaabi.ee', 'source' => 'email_scan/learning/annaabi.py',
            'transport_profile' => 'browser',
            'request' => ['method' => 'GET', 'url' => 'https://annaabi.ee/register.php', 'headers' => ['Referer' => 'https://annaabi.ee/register.php'], 'query' => ['go' => '1', 'emailcheck' => '{target}']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'body', 'operator' => 'contains', 'value' => 'value="2" id="psemail"'], ['field' => 'body', 'operator' => 'contains', 'value' => 'Sellise emailiga kasutaja on juba registreerinud']]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'body', 'operator' => 'contains', 'value' => 'value="1" id="psemail"'], ['field' => 'body', 'operator' => 'contains', 'value' => 'See email on vaba']]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'linga', 'category' => 'learning', 'site_name' => 'Linga',
            'site_url' => 'https://linga.io', 'source' => 'email_scan/learning/linga.py',
            'request' => ['method' => 'POST', 'url' => 'https://app.linga.io/api/v2/recovery', 'body_mode' => 'raw', 'headers' => ['Content-Type' => 'application/json', 'x-linga-platform' => 'web', 'x-linga-appver' => '3.5.2/275', 'x-linga-device-id' => 'scanner', 'x-linga-timezone-offset' => '{minute}', 'x-linga-signature' => '{linga_signature}'], 'body' => '{"email":"{target_json}"}'],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 404], ['field' => 'json', 'path' => 'message', 'value' => 'Not found']]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'message', 'value' => 'Email was sent']]], 'status' => 'Registered'],
            ],
        ],
        [
            'key' => 'start_me', 'category' => 'other', 'site_name' => 'StartMe',
            'site_url' => 'https://start.me', 'source' => 'email_scan/other/start_me.py',
            'transport_profile' => 'chrome',
            'request' => ['method' => 'GET', 'url' => 'https://start.me/users/check_email', 'headers' => ['Accept' => 'application/json', 'Referer' => 'https://start.me/users/sign_up', 'Accept-Language' => 'en-US,en;q=0.9'], 'query' => ['email' => '{target}']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'exists', 'operator' => 'truthy']]], 'status' => 'Registered', 'metadata_from_json' => ['account_locked' => 'locked']],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'exists', 'operator' => 'falsy']]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'cv_ee', 'category' => 'jobs', 'site_name' => 'CvEe',
            'site_url' => 'https://www.cv.ee', 'source' => 'email_scan/jobs/cv_ee.py', 'transport_profile' => 'browser',
            'request' => ['method' => 'POST', 'url' => 'https://www.cv.ee/api/v1/authentication/password/forgot-password', 'body' => ['email' => '{target}']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 404], ['field' => 'json_root', 'value' => ['errorType' => 'RESOURCE_NOT_FOUND', 'message' => 'Resource not found.']]]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'body', 'operator' => 'empty']]], 'status' => 'Registered'],
            ],
        ],
        [
            'key' => 'cv_lv', 'category' => 'jobs', 'site_name' => 'CvLv',
            'site_url' => 'https://www.cv.lv', 'source' => 'email_scan/jobs/cv_lv.py', 'transport_profile' => 'browser',
            'request' => ['method' => 'POST', 'url' => 'https://www.cv.lv/api/v1/authentication/password/forgot-password', 'body' => ['email' => '{target}']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 404], ['field' => 'json_root', 'value' => ['errorType' => 'RESOURCE_NOT_FOUND', 'message' => 'Resource not found.']]]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'body', 'operator' => 'empty']]], 'status' => 'Registered'],
            ],
        ],
        [
            'key' => 'cvonline_lt', 'category' => 'jobs', 'site_name' => 'CvonlineLt',
            'site_url' => 'https://www.cvonline.lt', 'source' => 'email_scan/jobs/cvonline_lt.py', 'transport_profile' => 'browser',
            'request' => ['method' => 'POST', 'url' => 'https://www.cvonline.lt/api/v1/authentication/password/forgot-password', 'body' => ['email' => '{target}']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 404], ['field' => 'json_root', 'value' => ['errorType' => 'RESOURCE_NOT_FOUND', 'message' => 'Resource not found.']]]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'body', 'operator' => 'empty']]], 'status' => 'Registered'],
            ],
        ],
        [
            'key' => 'dirbam', 'category' => 'jobs', 'site_name' => 'Dirbam',
            'site_url' => 'https://www.dirbam.lt', 'source' => 'email_scan/jobs/dirbam.py', 'transport_profile' => 'browser',
            'request' => ['method' => 'GET', 'url' => 'https://www.dirbam.lt/patikrinti/el-pastas', 'query' => ['email' => '{target}']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'body', 'operator' => 'contains', 'value' => 'Paskyra su tokių el.paštu jau yra']]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'body', 'transform' => 'trim', 'value' => '"true"']]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'otsintood', 'category' => 'jobs', 'site_name' => 'Otsintood',
            'site_url' => 'https://www.otsintood.ee', 'source' => 'email_scan/jobs/otsintood.py', 'transport_profile' => 'browser',
            'request' => ['method' => 'GET', 'url' => 'https://www.otsintood.ee/kontrolli/e-post', 'query' => ['email' => '{target}']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'body', 'operator' => 'contains', 'value' => 'Selle e-postiga on juba kasutaja registreeritud']]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'body', 'transform' => 'trim', 'value' => '"true"']]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'pulser', 'category' => 'jobs', 'site_name' => 'Pulser',
            'site_url' => 'https://pulser.hr', 'source' => 'email_scan/jobs/pulser.py', 'transport_profile' => 'browser',
            'request' => ['method' => 'POST', 'url' => 'https://cms.zadovoljstvozaposlenika.hr/api/auth/password/request-reset', 'headers' => ['Accept' => 'application/json, text/plain, */*'], 'body' => ['email' => '{target}']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 422], ['field' => 'json', 'path' => 'message', 'value' => 'The selected email is invalid.']]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'data.status', 'operator' => 'truthy']]], 'status' => 'Registered'],
            ],
        ],
        [
            'key' => 'visidarbi', 'category' => 'jobs', 'site_name' => 'Visidarbi',
            'site_url' => 'https://www.visidarbi.lv', 'source' => 'email_scan/jobs/visidarbi.py', 'transport_profile' => 'browser',
            'request' => ['method' => 'GET', 'url' => 'https://www.visidarbi.lv/parbaude/e-pasts', 'query' => ['email' => '{target}']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'body', 'operator' => 'contains', 'value' => 'Profils ar šādu e-pasta adresi jau ir reģistrēts']]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'body', 'transform' => 'trim', 'value' => '"true"']]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'disqus', 'category' => 'community', 'site_name' => 'Disqus',
            'site_url' => 'https://disqus.com', 'source' => 'email_scan/community/disqus.py',
            'bootstrap' => [[
                'method' => 'GET', 'url' => 'https://disqus.com/profile/signup/',
                'headers' => ['User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'Accept-Language' => 'en-US,en;q=0.9', 'Origin' => 'https://disqus.com', 'Referer' => 'https://disqus.com/profile/signup/'],
                'capture' => ['csrf' => ['from' => 'body_regex', 'pattern' => '~name=[\'\"]csrfmiddlewaretoken[\'\"]\\s+value=[\'\"]([^\'\"]+)[\'\"]~', 'error_reason' => 'Disqus CSRF token extraction failed']],
            ]],
            'request' => ['method' => 'POST', 'url' => 'https://disqus.com/profile/signup/', 'body_mode' => 'form', 'headers' => ['User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'Origin' => 'https://disqus.com', 'Referer' => 'https://disqus.com/profile/signup/', 'Content-Type' => 'application/x-www-form-urlencoded'], 'body' => ['csrfmiddlewaretoken' => '{csrf}', 'display_name' => 'osintuser', 'email' => '{target}', 'password' => '', 'tos' => 'on', 'privacy-policy' => 'on', 'age-declaration' => 'on', 'data-sharing' => 'on']],
            'rules' => [
                ['when' => ['field' => 'body', 'operator' => 'contains', 'value' => 'The e-mail address you specified is already in use'], 'status' => 'Registered'],
                ['when' => ['field' => 'body', 'operator' => 'contains', 'value' => 'errorlist'], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'quora', 'category' => 'community', 'site_name' => 'Quora',
            'site_url' => 'https://www.quora.com', 'source' => 'email_scan/community/quora.py', 'transport_profile' => 'browser',
            'bootstrap' => [[
                'method' => 'GET', 'url' => 'https://www.quora.com',
                'capture' => ['formkey' => ['from' => 'body_regex', 'pattern' => '~"formkey":\\s*"([a-f0-9]+)"~', 'error_reason' => 'Quora form key extraction failed']],
            ]],
            'request' => ['method' => 'POST', 'url' => 'https://www.quora.com/graphql/gql_para_POST', 'query' => ['q' => 'SignupEmailForm_validateEmail_Query'], 'headers' => ['quora-formkey' => '{formkey}'], 'body' => ['queryName' => 'SignupEmailForm_validateEmail_Query', 'variables' => ['email' => '{target}'], 'extensions' => ['hash' => '1db80096407be846d5581fe1b42b12fd05e0b40a5d3095ed40a0b4bd28f49fe7']]],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'data.validateEmail', 'operator' => 'in', 'value' => ['IN_USE', 'NOT_CONFIRMED']]]], 'status' => 'Registered', 'metadata_from_json' => ['signup_state' => 'data.validateEmail']],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'data.validateEmail', 'value' => 'OK']]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'leanpub', 'category' => 'creator', 'site_name' => 'Leanpub',
            'site_url' => 'https://leanpub.com', 'source' => 'email_scan/creator/leanpub.py',
            'request' => ['method' => 'POST', 'url' => 'https://leanpub.com/authors/create/book', 'body_mode' => 'form', 'body' => ['title' => 'Email availability probe', 'publisherId' => 'Z2lkOi8vbGVhbnB1Yi9Pcmc6OlB1Ymxpc2hlci8x', 'slug' => 'user-scanner-email-probe', 'languageId' => 'Z2lkOi8vbGVhbnB1Yi9TaGFyZWQ6Okxhbmd1YWdlLzEyNA', 'syncMode' => 'lexical', 'name' => 'Email Probe', 'username' => 'user-scanner-email-probe', 'email' => '{target}', 'password' => 'x']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'body', 'operator' => 'contains', 'value' => 'Password must be at least 8 characters long'], ['field' => 'body', 'operator' => 'contains_ci', 'value' => '{target}'], ['field' => 'body', 'operator' => 'contains', 'value' => 'Email This email address can not be used to create a new account. If this is your email address, try signing in instead.']]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'body', 'operator' => 'contains', 'value' => 'Password must be at least 8 characters long'], ['field' => 'body', 'operator' => 'contains_ci', 'value' => '{target}'], ['not' => ['field' => 'body', 'operator' => 'contains', 'value' => 'Email This email address can not be used to create a new account.']]]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'threadless', 'category' => 'creator', 'site_name' => 'Threadless',
            'site_url' => 'https://www.threadless.com', 'source' => 'email_scan/creator/threadless.py', 'transport_profile' => 'browser',
            'bootstrap' => [[
                'method' => 'GET', 'url' => 'https://profile.threadless.com/artist/shops/',
                'capture' => ['csrf' => ['from' => 'body_regex', 'pattern' => '~name=[\'\"]csrfmiddlewaretoken[\'\"]\\s+value=[\'\"]([^\'\"]+)~', 'error_reason' => 'Threadless CSRF token extraction failed']],
            ]],
            'request' => ['method' => 'POST', 'url' => 'https://profile.threadless.com/artist/shops/', 'body_mode' => 'form', 'headers' => ['x-requested-with' => 'XMLHttpRequest'], 'body' => ['validate' => 'true', 'email' => '{target}', 'csrfmiddlewaretoken' => '{csrf}']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'is_valid', 'operator' => 'falsy']]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'is_valid', 'operator' => 'truthy']]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'vimeo', 'category' => 'creator', 'site_name' => 'Vimeo',
            'site_url' => 'https://vimeo.com', 'source' => 'email_scan/creator/vimeo.py',
            'bootstrap' => [[
                'method' => 'GET', 'url' => 'https://vimeo.com/_next/viewer', 'headers' => ['Accept' => 'application/json', 'Referer' => 'https://vimeo.com/join'],
                'capture' => ['xsrft' => ['from' => 'json', 'path' => 'xsrft', 'error_reason' => 'Vimeo token extraction failed']],
            ]],
            'request' => ['method' => 'POST', 'url' => 'https://vimeo.com/join', 'body_mode' => 'form', 'headers' => ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest', 'Content-Type' => 'application/x-www-form-urlencoded', 'Origin' => 'https://vimeo.com', 'Referer' => 'https://vimeo.com/join'], 'body' => ['email' => '{target}', 'token' => '{xsrft}', 'action' => 'join', 'service' => 'vimeo', 'email_validation' => 'true']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'has_error_user_exists', 'operator' => 'truthy']]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json_root', 'operator' => 'is_empty_list']]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'codepen', 'category' => 'dev', 'site_name' => 'Codepen',
            'site_url' => 'https://codepen.io', 'source' => 'email_scan/dev/codepen.py', 'transport_profile' => 'browser',
            'bootstrap' => [[
                'method' => 'GET', 'url' => 'https://codepen.io/accounts/signup/user/free',
                'capture' => ['csrf' => ['from' => 'body_regex', 'pattern' => '~<meta name="csrf-token" content="([^"]+)"~', 'error_reason' => 'CodePen CSRF token extraction failed']],
            ]],
            'request' => ['method' => 'POST', 'url' => 'https://codepen.io/accounts/duplicate_check', 'body_mode' => 'form', 'headers' => ['x-csrf-token' => '{csrf}'], 'body' => ['attribute' => 'email', 'value' => '{target}', 'context' => 'user']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'success', 'operator' => 'truthy'], ['field' => 'json', 'path' => 'payload.errors.email', 'value' => ['That Email is already taken. Please choose another!']]]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'success', 'operator' => 'truthy'], ['field' => 'json', 'path' => 'payload.errors.email', 'operator' => 'empty']]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'stackb', 'category' => 'gaming', 'site_name' => 'Stackb',
            'site_url' => 'https://stackb.net', 'source' => 'email_scan/gaming/stackb.py',
            'bootstrap' => [[
                'method' => 'GET', 'url' => 'https://stackb.net/login',
                'capture' => [
                    'csrf' => ['from' => 'body_regex', 'pattern' => '~<meta name="csrf-token" content="([^"]+)"~', 'transform' => 'html_decode', 'error_reason' => 'StackB CSRF token extraction failed'],
                    'snapshot' => ['from' => 'body_regex', 'pattern' => '~<div(?=[^>]*wire:name="login-form")(?=[^>]*wire:snapshot="([^"]+)")[^>]*>~', 'transform' => 'html_decode', 'error_reason' => 'StackB snapshot extraction failed'],
                ],
            ]],
            'request' => ['method' => 'POST', 'url' => 'https://stackb.net/livewire/update', 'headers' => ['X-CSRF-TOKEN' => '{csrf}', 'X-Livewire' => 'true'], 'body' => ['components' => [['snapshot' => '{snapshot}', 'updates' => ['identifier' => '{target}', 'password' => 'StackB-not-the-password-12345'], 'calls' => [['path' => '', 'method' => 'submitLogin', 'params' => []]]]]]],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'components', 'operator' => 'array_contains_ci', 'value' => 'Пароль введен неверно']]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'components', 'operator' => 'array_contains_ci', 'value' => 'Таких пользователей не нашлось']]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'cvkeskus', 'category' => 'jobs', 'site_name' => 'Cvkeskus',
            'site_url' => 'https://www.cvkeskus.ee', 'source' => 'email_scan/jobs/cvkeskus.py', 'transport_profile' => 'browser',
            'bootstrap' => [['method' => 'GET', 'url' => 'https://www.cvkeskus.ee/remind/username', 'expect' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'body', 'operator' => 'contains', 'value' => 'id="reminderForm"']]], 'capture' => ['csrf' => ['from' => 'body_regex', 'pattern' => '~name=[\'\"]csrf_token[\'\"] value=[\'\"]([^\'\"]+)~']]]],
            'request' => ['method' => 'POST', 'url' => 'https://www.cvkeskus.ee/remind/username', 'body_mode' => 'form', 'headers' => ['Referer' => 'https://www.cvkeskus.ee/remind/username'], 'body' => ['csrf_token' => '{csrf}', 'email' => '{target}']],
            'rules' => [
                ['when' => ['field' => 'body', 'operator' => 'contains', 'value' => 'Sisestatud e-posti aadress puudub meie süsteemis.'], 'status' => 'Not Registered'],
                ['when' => ['field' => 'body', 'operator' => 'contains', 'value' => 'Selle e-posti aadressiga on seotud mitu kontot.'], 'status' => 'Registered', 'metadata' => ['multiple_accounts' => true]],
                ['when' => ['field' => 'body', 'operator' => 'contains', 'value' => 'Saatsime Sinu kasutajanime meeldetuletuse e-postile:'], 'status' => 'Registered', 'metadata' => ['multiple_accounts' => false]],
            ],
        ],
        [
            'key' => 'cvmarket_lt', 'category' => 'jobs', 'site_name' => 'CvmarketLt',
            'site_url' => 'https://www.cvmarket.lt', 'source' => 'email_scan/jobs/cvmarket_lt.py', 'transport_profile' => 'browser',
            'bootstrap' => [['method' => 'GET', 'url' => 'https://www.cvmarket.lt/remind/username', 'capture' => ['csrf' => ['from' => 'body_regex', 'pattern' => '~name=[\'\"]csrf_token[\'\"] value=[\'\"]([^\'\"]+)~']]]],
            'request' => ['method' => 'POST', 'url' => 'https://www.cvmarket.lt/remind/username', 'body_mode' => 'form', 'headers' => ['Referer' => 'https://www.cvmarket.lt/remind/username'], 'body' => ['csrf_token' => '{csrf}', 'email' => '{target}']],
            'rules' => [
                ['when' => ['field' => 'body', 'operator' => 'contains', 'value' => 'Su tokiu el. paštu registracijos nėra.'], 'status' => 'Not Registered'],
                ['when' => ['any' => [['field' => 'body', 'operator' => 'contains', 'value' => 'Jeigu su šiuo el. paštu yra paskyrą, jūs turėjote gauti'], ['field' => 'body', 'operator' => 'contains', 'value' => 'Laiško nurodytu adresu {target} išsiųsti nepavyko!']]], 'status' => 'Registered'],
            ],
        ],
        [
            'key' => 'cvmarket_lv', 'category' => 'jobs', 'site_name' => 'CvmarketLv',
            'site_url' => 'https://www.cvmarket.lv', 'source' => 'email_scan/jobs/cvmarket_lv.py', 'transport_profile' => 'browser',
            'bootstrap' => [['method' => 'GET', 'url' => 'https://www.cvmarket.lv/remind/username', 'capture' => ['csrf' => ['from' => 'body_regex', 'pattern' => '~name=[\'\"]csrf_token[\'\"] value=[\'\"]([^\'\"]+)~']]]],
            'request' => ['method' => 'POST', 'url' => 'https://www.cvmarket.lv/remind/username', 'body_mode' => 'form', 'headers' => ['Referer' => 'https://www.cvmarket.lv/remind/username'], 'body' => ['csrf_token' => '{csrf}', 'email' => '{target}']],
            'rules' => [
                ['when' => ['field' => 'body', 'operator' => 'contains', 'value' => 'Trūkst e-pasta adreses!'], 'status' => 'Not Registered'],
                ['when' => ['any' => [['field' => 'body', 'operator' => 'contains', 'value' => 'Ja esat reģistrējies portālā ar norādīto e-pasta adresi'], ['field' => 'body', 'operator' => 'contains', 'value' => 'Pēdējās stundas laikā paroles maiņas pieprasījumu šai e-pasta adresei jau tika saņemts.']]], 'status' => 'Registered'],
            ],
        ],
        [
            'key' => 'hercul', 'category' => 'jobs', 'site_name' => 'Hercul',
            'site_url' => 'https://app.hercul.hr', 'source' => 'email_scan/jobs/hercul.py', 'transport_profile' => 'browser',
            'bootstrap' => [[
                'method' => 'GET', 'url' => 'https://api.hercul.hr/api/auth/email-available/{target_url}',
                'expect' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'isAvailable', 'operator' => 'exists']]],
                'stop_rules' => [['when' => ['field' => 'json', 'path' => 'isAvailable', 'operator' => 'truthy'], 'status' => 'Not Registered']],
            ]],
            'request' => ['method' => 'POST', 'url' => 'https://api.hercul.hr/api/auth/users/reset-password', 'body' => ['email' => '{target}']],
            'rules' => [['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'body', 'operator' => 'empty']]], 'status' => 'Registered']],
        ],
        [
            'key' => 'jobs_cz', 'category' => 'jobs', 'site_name' => 'JobsCz',
            'site_url' => 'https://www.jobs.cz', 'source' => 'email_scan/jobs/jobs_cz.py', 'transport_profile' => 'browser',
            'bootstrap' => [['method' => 'GET', 'url' => 'https://www.jobs.cz/obnova-hesla/', 'capture' => ['csrf' => ['from' => 'body_regex', 'pattern' => '~name="user_email\\[_token\\]" value="([^"]+)"~']]]],
            'request' => ['method' => 'POST', 'url' => 'https://www.jobs.cz/obnova-hesla/', 'body_mode' => 'form', 'body' => ['user_email[email]' => '{target}', 'user_email[submit]' => '', 'user_email[_token]' => '{csrf}']],
            'rules' => [
                ['when' => ['field' => 'body', 'operator' => 'contains', 'value' => 'Zadaný e-mail není registrovaný.'], 'status' => 'Not Registered'],
                ['when' => ['field' => 'body', 'operator' => 'contains', 'value' => 'Posíláme odkaz pro obnovení hesla'], 'status' => 'Registered'],
            ],
        ],
        [
            'key' => 'kandideeri', 'category' => 'jobs', 'site_name' => 'Kandideeri',
            'site_url' => 'https://www.kandideeri.ee', 'source' => 'email_scan/jobs/kandideeri.py', 'transport_profile' => 'browser',
            'bootstrap' => [['method' => 'GET', 'url' => 'https://www.kandideeri.ee/registration/?user_group_id=JobSeeker', 'expect' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'body', 'operator' => 'contains', 'value' => 'id="registr-form"']]], 'capture' => ['csrf' => ['from' => 'body_regex', 'pattern' => '~<meta name="csrf-token" content="([^"]+)"~']]]],
            'request' => ['method' => 'POST', 'url' => 'https://www.kandideeri.ee/registration/?user_group_id=JobSeeker', 'body_mode' => 'form', 'headers' => ['X-Requested-With' => 'XMLHttpRequest'], 'body' => ['csrf-token' => '{csrf}', 'username' => '{target}', 'validate_username' => '✓']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'body', 'transform' => 'trim', 'value' => 'NOT_UNIQUE_VALUE']]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'body', 'operator' => 'empty']]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'memrise', 'category' => 'learning', 'site_name' => 'Memrise',
            'site_url' => 'https://www.memrise.com', 'source' => 'email_scan/learning/memrise.py',
            'bootstrap' => [['method' => 'GET', 'url' => 'https://app.memrise.com/password/reset/', 'capture' => ['csrf' => ['from' => 'body_regex', 'pattern' => '~name="csrfmiddlewaretoken" value="([^"]+)"~', 'error_reason' => 'Memrise CSRF token extraction failed']]]],
            'request' => ['method' => 'POST', 'url' => 'https://app.memrise.com/password/reset/', 'body_mode' => 'form', 'follow_redirects' => false, 'headers' => ['Referer' => 'https://app.memrise.com/password/reset/'], 'body' => ['csrfmiddlewaretoken' => '{csrf}', 'email' => '{target}']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 302], ['field' => 'header', 'name' => 'Location', 'value' => '/password/reset/done/']]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'body', 'operator' => 'contains', 'value' => 'No such user.']]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'alza_at', 'category' => 'shopping', 'site_name' => 'AlzaAt',
            'site_url' => 'https://www.alza.at', 'source' => 'email_scan/shopping/alza_at.py',
            'bootstrap' => [['method' => 'GET', 'url' => 'https://www.alza.at', 'headers' => ['Referer' => 'https://www.alza.at', 'Origin' => 'https://www.alza.at', 'Accept' => 'application/json']]],
            'request' => ['method' => 'POST', 'url' => 'https://www.alza.at/Services/EShopService.svc/CheckLoginAvailability', 'headers' => ['Referer' => 'https://www.alza.at', 'Origin' => 'https://www.alza.at', 'Accept' => 'application/json'], 'body' => ['login' => '{target}']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'LoginAvailabilityType', 'value' => 0]]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'LoginAvailabilityType', 'operator' => 'in', 'value' => [1, 2]]]], 'status' => 'Registered', 'metadata_from_json' => ['login_availability_type' => 'LoginAvailabilityType']],
            ],
        ],
        [
            'key' => 'alza_cz', 'category' => 'shopping', 'site_name' => 'AlzaCz',
            'site_url' => 'https://www.alza.cz', 'source' => 'email_scan/shopping/alza_cz.py',
            'bootstrap' => [['method' => 'GET', 'url' => 'https://www.alza.cz', 'headers' => ['Referer' => 'https://www.alza.cz', 'Origin' => 'https://www.alza.cz', 'Accept' => 'application/json']]],
            'request' => ['method' => 'POST', 'url' => 'https://www.alza.cz/Services/EShopService.svc/CheckLoginAvailability', 'headers' => ['Referer' => 'https://www.alza.cz', 'Origin' => 'https://www.alza.cz', 'Accept' => 'application/json'], 'body' => ['login' => '{target}']],
            'rules' => [['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'LoginAvailabilityType', 'value' => 0]]], 'status' => 'Not Registered'], ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'LoginAvailabilityType', 'operator' => 'in', 'value' => [1, 2]]]], 'status' => 'Registered']],
        ],
        [
            'key' => 'alza_de', 'category' => 'shopping', 'site_name' => 'AlzaDe',
            'site_url' => 'https://www.alza.de', 'source' => 'email_scan/shopping/alza_de.py',
            'bootstrap' => [['method' => 'GET', 'url' => 'https://www.alza.de', 'headers' => ['Referer' => 'https://www.alza.de', 'Origin' => 'https://www.alza.de', 'Accept' => 'application/json']]],
            'request' => ['method' => 'POST', 'url' => 'https://www.alza.de/Services/EShopService.svc/CheckLoginAvailability', 'headers' => ['Referer' => 'https://www.alza.de', 'Origin' => 'https://www.alza.de', 'Accept' => 'application/json'], 'body' => ['login' => '{target}']],
            'rules' => [['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'LoginAvailabilityType', 'value' => 0]]], 'status' => 'Not Registered'], ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'LoginAvailabilityType', 'operator' => 'in', 'value' => [1, 2]]]], 'status' => 'Registered']],
        ],
        [
            'key' => 'alza_hu', 'category' => 'shopping', 'site_name' => 'AlzaHu',
            'site_url' => 'https://www.alza.hu', 'source' => 'email_scan/shopping/alza_hu.py',
            'bootstrap' => [['method' => 'GET', 'url' => 'https://www.alza.hu', 'headers' => ['Referer' => 'https://www.alza.hu', 'Origin' => 'https://www.alza.hu', 'Accept' => 'application/json']]],
            'request' => ['method' => 'POST', 'url' => 'https://www.alza.hu/Services/EShopService.svc/CheckLoginAvailability', 'headers' => ['Referer' => 'https://www.alza.hu', 'Origin' => 'https://www.alza.hu', 'Accept' => 'application/json'], 'body' => ['login' => '{target}']],
            'rules' => [['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'LoginAvailabilityType', 'value' => 0]]], 'status' => 'Not Registered'], ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'LoginAvailabilityType', 'operator' => 'in', 'value' => [1, 2]]]], 'status' => 'Registered']],
        ],
        [
            'key' => 'alza_sk', 'category' => 'shopping', 'site_name' => 'AlzaSk',
            'site_url' => 'https://www.alza.sk', 'source' => 'email_scan/shopping/alza_sk.py',
            'bootstrap' => [['method' => 'GET', 'url' => 'https://www.alza.sk', 'headers' => ['Referer' => 'https://www.alza.sk', 'Origin' => 'https://www.alza.sk', 'Accept' => 'application/json']]],
            'request' => ['method' => 'POST', 'url' => 'https://www.alza.sk/Services/EShopService.svc/CheckLoginAvailability', 'headers' => ['Referer' => 'https://www.alza.sk', 'Origin' => 'https://www.alza.sk', 'Accept' => 'application/json'], 'body' => ['login' => '{target}']],
            'rules' => [['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'LoginAvailabilityType', 'value' => 0]]], 'status' => 'Not Registered'], ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'LoginAvailabilityType', 'operator' => 'in', 'value' => [1, 2]]]], 'status' => 'Registered']],
        ],
        [
            'key' => 'rappi', 'category' => 'shopping', 'site_name' => 'Rappi',
            'site_url' => 'https://www.rappi.com', 'source' => 'email_scan/shopping/rappi.py',
            'request' => ['method' => 'POST', 'url' => 'https://services.rappi.com.ar/api/rocket/user/account/check-email', 'timeout' => 6, 'headers' => ['User-Agent' => 'Dalvik/2.1.0 (Linux; U; Android 13; Pixel 6 Build/TP1A.220624.021)', 'user_id' => '', 'custom_country_code' => 'AR', 'deviceid' => '{hex16}', 'app-version' => '88721', 'app-version-name' => '8.35.20260724-88721', 'store-platform' => 'google', 'amplitude-session-id' => '{epoch_ms}', 'timestamp' => '{epoch_ms}', 'request_timestamp' => '{epoch_ms}', 'accept-language' => 'en-US', 'language' => 'en', 'country-code' => 'AR', 'fp_dp_id' => '3703578013', 'Content-Type' => 'application/json; charset=UTF-8'], 'body' => ['email' => '{target}']],
            'rules' => [
                [
                    'when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'exists', 'operator' => 'truthy']]],
                    'status' => 'Registered',
                    'reason' => 'Use allow_loud to run optional phone-hint enrichment',
                    'optional_followup' => [
                        'option' => 'allow_loud',
                        'request' => [
                            'method' => 'POST',
                            'url' => 'https://services.rappi.com.ar/api/rocket/login/email/application_user',
                            'timeout' => 6,
                            'headers' => ['User-Agent' => 'Dalvik/2.1.0 (Linux; U; Android 13; Pixel 6 Build/TP1A.220624.021)', 'user_id' => '', 'custom_country_code' => 'AR', 'deviceid' => '{hex16}', 'app-version' => '88721', 'app-version-name' => '8.35.20260724-88721', 'store-platform' => 'google', 'amplitude-session-id' => '{epoch_ms}', 'timestamp' => '{epoch_ms}', 'request_timestamp' => '{epoch_ms}', 'accept-language' => 'en-US', 'language' => 'en', 'country-code' => 'AR', 'fp_dp_id' => '3703578013', 'Content-Type' => 'application/json; charset=UTF-8'],
                            'body' => ['email' => '{target}', 'scope' => 'all'],
                        ],
                        'semantic_statuses' => [200, 400, 401, 422],
                        'metadata_from_json' => ['phone' => 'error.verification_value', 'verification_type' => 'error.verification_type'],
                    ],
                ],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'exists', 'operator' => 'falsy']]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'tindie', 'category' => 'shopping', 'site_name' => 'Tindie',
            'site_url' => 'https://www.tindie.com/', 'source' => 'email_scan/shopping/tindie.py', 'transport_profile' => 'browser',
            'bootstrap' => [[
                'method' => 'GET', 'url' => 'https://www.tindie.com/accounts/signup/',
                'capture' => ['csrf' => ['from' => 'header_regex', 'name' => 'Set-Cookie', 'pattern' => '~(?:^|[,;]\\s*)csrftoken=([^;,]+)~', 'error_reason' => 'Tindie CSRF token extraction failed']],
            ]],
            'request' => ['method' => 'POST', 'url' => 'https://www.tindie.com/accounts/check_email/', 'body_mode' => 'form', 'headers' => ['Referer' => 'https://www.tindie.com/accounts/signup/', 'X-CSRFToken' => '{csrf}'], 'body' => ['email' => '{target}']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'valid', 'operator' => 'falsy'], ['field' => 'json', 'path' => 'errors', 'operator' => 'contains', 'value' => 'Email already registered!']]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'valid', 'operator' => 'truthy'], ['field' => 'json', 'path' => 'errors', 'operator' => 'empty'], ['field' => 'json', 'path' => 'message', 'value' => 'Email available!']]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'tumblr', 'category' => 'social', 'site_name' => 'Tumblr',
            'site_url' => 'https://tumblr.com', 'source' => 'email_scan/social/tumblr.py', 'transport_profile' => 'chrome131',
            'bootstrap' => [
                ['method' => 'GET', 'url' => 'https://www.tumblr.com/', 'capture' => ['api_token' => ['from' => 'body_regex', 'pattern' => '~"API_TOKEN":"([^"]+)"~', 'error_reason' => 'Tumblr API token extraction failed']]],
                ['method' => 'GET', 'url' => 'https://www.tumblr.com/api/v2/radar', 'headers' => ['Accept' => 'application/json', 'Authorization' => 'Bearer {api_token}', 'Origin' => 'https://www.tumblr.com', 'Referer' => 'https://www.tumblr.com/'], 'capture' => ['csrf' => ['from' => 'header', 'name' => 'X-Csrf', 'error_reason' => 'Tumblr CSRF extraction failed']]],
            ],
            'request' => ['method' => 'POST', 'url' => 'https://www.tumblr.com/api/v2/register/account/validate', 'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json', 'Authorization' => 'Bearer {api_token}', 'X-CSRF' => '{csrf}', 'Origin' => 'https://www.tumblr.com', 'Referer' => 'https://www.tumblr.com/register'], 'body' => ['email' => '{target}', 'password' => 'Password123!@#', 'tumblelog' => 'osintuserprobe']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'operator' => 'in', 'value' => [200, 400]], ['field' => 'json', 'path' => 'response.code', 'value' => 2], ['field' => 'json', 'path' => 'response.error', 'operator' => 'contains_ci', 'value' => 'user already exists']]], 'status' => 'Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'response', 'operator' => 'empty']]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'response.code', 'value' => 1030], ['field' => 'json', 'path' => 'response.error', 'operator' => 'contains_ci', 'value' => 'password']]], 'status' => 'Not Registered'],
            ],
        ],
        [
            'key' => 'redtube', 'category' => 'adult', 'site_name' => 'Redtube',
            'site_url' => 'https://redtube.com', 'source' => 'email_scan/adult/redtube.py',
            'bootstrap' => [['method' => 'GET', 'url' => 'https://www.redtube.com/register', 'headers' => ['User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 'Referer' => 'https://www.redtube.com/register'], 'capture' => ['token' => ['from' => 'body_regex', 'pattern' => '~page_params\\.token\\s*=\\s*"([^"]+)"~', 'error_reason' => 'RedTube dynamic token extraction failed']]]],
            'request' => ['method' => 'POST', 'url' => 'https://www.redtube.com/user/create_account_check', 'body_mode' => 'form', 'query' => ['token' => '{token}'], 'headers' => ['X-Requested-With' => 'XMLHttpRequest', 'Origin' => 'https://www.redtube.com', 'Referer' => 'https://www.redtube.com/register', 'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8'], 'body' => ['token' => '{token}', 'check_what' => 'email', 'email' => '{target}']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'email', 'value' => 'create_account_passed'], ['field' => 'json', 'path' => 'error_message', 'operator' => 'empty']]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'email', 'value' => 'create_account_failed'], ['field' => 'json', 'path' => 'error_message', 'operator' => 'contains_ci', 'value' => 'already registered']]], 'status' => 'Registered'],
                ['when' => ['field' => 'json', 'path' => 'email', 'value' => 'create_account_failed'], 'status' => 'Error', 'reason' => 'RedTube registration policy rejected the address; account status unknown'],
            ],
        ],
        [
            'key' => 'tube8', 'category' => 'adult', 'site_name' => 'Tube8',
            'site_url' => 'https://tube8.com', 'source' => 'email_scan/adult/tube8.py',
            'bootstrap' => [['method' => 'GET', 'url' => 'https://www.tube8.com/register', 'capture' => ['token' => ['from' => 'body_regex', 'pattern' => '~page_params\\.token\\s*=\\s*"([^"]+)"~', 'error_reason' => 'Tube8 dynamic token extraction failed']]]],
            'request' => ['method' => 'POST', 'url' => 'https://www.tube8.com/register/verify_email', 'body_mode' => 'form', 'query' => ['token' => '{token}'], 'headers' => ['X-Requested-With' => 'XMLHttpRequest', 'Origin' => 'https://www.tube8.com', 'Referer' => 'https://www.tube8.com/register', 'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8'], 'body' => ['token' => '{token}', 'email' => '{target}']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'success', 'operator' => 'truthy'], ['field' => 'json', 'path' => 'messages', 'operator' => 'empty']]], 'status' => 'Not Registered'],
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'success', 'operator' => 'falsy'], ['field' => 'json', 'path' => 'messages', 'operator' => 'array_contains_ci', 'value' => 'already registered']]], 'status' => 'Registered'],
                ['when' => ['field' => 'json', 'path' => 'messages', 'operator' => 'array_contains_ci', 'value' => 'does not meet our registration requirements'], 'status' => 'Error', 'reason' => 'Tube8 registration policy rejected the address; account status unknown'],
                ['when' => ['field' => 'json', 'path' => 'messages', 'operator' => 'array_contains_ci', 'value' => 'not available'], 'status' => 'Error', 'reason' => 'Rate limited, wait for a few minutes'],
            ],
        ],
        [
            'key' => 'youporn', 'category' => 'adult', 'site_name' => 'Youporn',
            'site_url' => 'https://youporn.com', 'source' => 'email_scan/adult/youporn.py',
            'bootstrap' => [['method' => 'GET', 'url' => 'https://www.youporn.com/register', 'capture' => ['token' => ['from' => 'body_regex', 'pattern' => '~page_params\\.token\\s*=\\s*"([^"]+)"~', 'error_reason' => 'YouPorn dynamic token extraction failed']]]],
            'request' => ['method' => 'POST', 'url' => 'https://www.youporn.com/register/verify_email', 'body_mode' => 'form', 'query' => ['token' => '{token}'], 'headers' => ['X-Requested-With' => 'XMLHttpRequest', 'Origin' => 'https://www.youporn.com', 'Referer' => 'https://www.youporn.com/register', 'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8'], 'body' => ['token' => '{token}', 'email' => '{target}']],
            'probe_request' => ['method' => 'POST', 'url' => 'https://www.youporn.com/register/verify_email', 'body_mode' => 'form', 'query' => ['token' => '{token}'], 'headers' => ['X-Requested-With' => 'XMLHttpRequest', 'Origin' => 'https://www.youporn.com', 'Referer' => 'https://www.youporn.com/register', 'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8'], 'body' => ['token' => '{token}', 'email' => '{probe_target}']],
            'rules' => [
                ['when' => ['all' => [['field' => 'status', 'value' => 200], ['field' => 'json', 'path' => 'success', 'operator' => 'truthy']]], 'status' => 'Not Registered'],
                ['when' => ['field' => 'json', 'path' => 'messages', 'operator' => 'array_contains_ci', 'value' => 'does not meet our registration requirements'], 'status' => 'Probe'],
            ],
            'probe_rules' => [
                ['when' => ['field' => 'json', 'path' => 'messages', 'operator' => 'array_contains_ci', 'value' => 'does not meet our registration requirements'], 'status' => 'Not Registered', 'reason' => 'Domain is rejected by YouPorn registration'],
                ['when' => ['field' => 'json', 'path' => 'messages', 'operator' => 'array_contains_ci', 'value' => 'not available'], 'status' => 'Error', 'reason' => 'Rate limited, wait for a few minutes'],
                ['when' => ['field' => 'status', 'value' => 200], 'status' => 'Registered'],
            ],
        ],
        [
            'key' => 'okcupid', 'category' => 'dating', 'site_name' => 'Okcupid',
            'site_url' => 'https://okcupid.com', 'source' => 'email_scan/dating/okcupid.py',
            'bootstrap' => [[
                'method' => 'POST', 'url' => 'https://e2p-okapi.api.okcupid.com/graphql/AnonAuthToken', 'timeout' => 6,
                'headers' => ['User-Agent' => 'Android 115.1.0', 'Content-Type' => 'application/json', 'x-apollo-operation' => 'AnonAuthToken', 'x-okcupid-device-id' => 'Android; Pixel 6; 13; {hex22};', 'x-okcupid-app' => 'OkCupid Android App 115.1.0', 'x-okcupid-platform' => 'Android', 'x-okcupid-version' => '115.1.0', 'x-okcupid-locale' => 'en-US', 'x-match-useragent' => 'OkCupid/115.1.0 REL/13', 'x-okcupid-auth-v' => '1', 'x-okcupid-emulator' => 'false', 'x-emb-path' => '/graphql/AnonAuthToken'],
                'body' => ['operationName' => 'AnonAuthToken', 'variables' => ['input' => ['deviceId' => '{hex22}', 'siteCode' => 36]], 'query' => 'mutation AnonAuthToken($input: AuthAnonymousInput!) { authAnonymous(input: $input) { token } }', 'extensions' => ['clientLibrary' => ['name' => 'apollo-kotlin', 'version' => '4.4.3']]],
                'capture' => ['anon_token' => ['from' => 'json', 'path' => 'data.authAnonymous.token', 'error_reason' => 'OkCupid anonymous auth token was not returned']],
            ]],
            'request' => ['method' => 'POST', 'url' => 'https://e2p-okapi.api.okcupid.com/graphql/ValidateEmail', 'timeout' => 6, 'headers' => ['User-Agent' => 'Android 115.1.0', 'Content-Type' => 'application/json', 'authorization' => '{anon_token}', 'x-apollo-operation' => 'ValidateEmail', 'x-okcupid-device-id' => 'Android; Pixel 6; 13; {hex22};', 'x-okcupid-app' => 'OkCupid Android App 115.1.0', 'x-okcupid-platform' => 'Android', 'x-okcupid-version' => '115.1.0', 'x-okcupid-locale' => 'en-US', 'x-match-useragent' => 'OkCupid/115.1.0 REL/13', 'x-okcupid-auth-v' => '1', 'x-okcupid-emulator' => 'false', 'x-emb-path' => '/graphql/ValidateEmail'], 'body' => ['operationName' => 'ValidateEmail', 'variables' => ['email' => '{target}'], 'query' => 'query ValidateEmail($email: String!) { auth { isEmailValid(email: $email) } }', 'extensions' => ['clientLibrary' => ['name' => 'apollo-kotlin', 'version' => '4.4.3']]]],
            'probe_request' => ['method' => 'POST', 'url' => 'https://e2p-okapi.api.okcupid.com/graphql/ValidateEmail', 'timeout' => 6, 'headers' => ['User-Agent' => 'Android 115.1.0', 'Content-Type' => 'application/json', 'authorization' => '{anon_token}', 'x-apollo-operation' => 'ValidateEmail', 'x-okcupid-device-id' => 'Android; Pixel 6; 13; {hex22};', 'x-okcupid-app' => 'OkCupid Android App 115.1.0', 'x-okcupid-platform' => 'Android', 'x-okcupid-version' => '115.1.0', 'x-okcupid-locale' => 'en-US', 'x-match-useragent' => 'OkCupid/115.1.0 REL/13', 'x-okcupid-auth-v' => '1', 'x-okcupid-emulator' => 'false', 'x-emb-path' => '/graphql/ValidateEmail'], 'body' => ['operationName' => 'ValidateEmail', 'variables' => ['email' => '{probe_target}'], 'query' => 'query ValidateEmail($email: String!) { auth { isEmailValid(email: $email) } }', 'extensions' => ['clientLibrary' => ['name' => 'apollo-kotlin', 'version' => '4.4.3']]]],
            'rules' => [
                ['when' => ['field' => 'json', 'path' => 'data.auth.isEmailValid', 'operator' => 'truthy'], 'status' => 'Not Registered'],
                ['when' => ['field' => 'json', 'path' => 'data.auth.isEmailValid', 'operator' => 'falsy'], 'status' => 'Probe'],
            ],
            'probe_rules' => [
                ['when' => ['field' => 'json', 'path' => 'data.auth.isEmailValid', 'operator' => 'truthy'], 'status' => 'Registered'],
                ['when' => ['field' => 'json', 'path' => 'data.auth.isEmailValid', 'operator' => 'falsy'], 'status' => 'Not Registered', 'reason' => 'OkCupid rejects registrations from this domain'],
            ],
        ],
    ],
];
