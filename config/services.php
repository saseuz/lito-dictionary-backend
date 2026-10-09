<?php

return [

    'dictionary' => [
        'provider' => env('DICTIONARY_PROVIDER', 'free_dictionary'),
        'lookup_mode' => env('DICTIONARY_LOOKUP_MODE', 'provider'),
        'timeout' => env('DICTIONARY_PROVIDER_TIMEOUT', 5),
        'cache_ttl' => env('DICTIONARY_PROVIDER_CACHE_TTL', 86400),
        'merriam_webster' => [
            'product' => env('MERRIAM_WEBSTER_PRODUCT', 'learners'),
            'keys' => [
                'learners' => env('MERRIAM_WEBSTER_LEARNERS_API_KEY'),
                'intermediate' => env('MERRIAM_WEBSTER_INTERMEDIATE_API_KEY'),
            ],
            'base_urls' => [
                'learners' => 'https://www.dictionaryapi.com/api/v3/references/learners/json',
                'intermediate' => 'https://www.dictionaryapi.com/api/v3/references/sd3/json',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
