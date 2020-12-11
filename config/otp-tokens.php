<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Otp Token Defaults
    |--------------------------------------------------------------------------
    |
    | This option controls the default authentication "guard" and password
    | reset options for your application. You may change these defaults
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'otp_tokens' => 'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Otp Tokens
    |--------------------------------------------------------------------------
    |
    | You may specify multiple otp token configurations if you have more
    | than one user table or model in the application and you want to have
    | separate otp token settings based on the specific user types.
    |
    | The expire time is the number of seconds that the token should be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    */
    'otp_tokens' => [
        'users' => [
            'provider' => 'users',
            'table' => 'otp_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],
];
