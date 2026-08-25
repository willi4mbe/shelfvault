<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'tmdb' => [
        'api_key' => env('TMDB_API_KEY'),
        'bearer_token' => env('TMDB_BEARER_TOKEN'),
        'language' => env('TMDB_LANGUAGE', 'fr-FR'),
        'region' => env('TMDB_REGION', 'FR'),
        'image_base_url' => env('TMDB_IMAGE_BASE_URL', 'https://image.tmdb.org/t/p/w500'),
    ],

    'igdb' => [
        'client_id' => env('IGDB_CLIENT_ID'),
        'client_secret' => env('IGDB_CLIENT_SECRET'),
        'access_token' => env('IGDB_ACCESS_TOKEN'),
        'base_url' => env('IGDB_BASE_URL', 'https://api.igdb.com/v4'),
        'token_url' => env('IGDB_TOKEN_URL', 'https://id.twitch.tv/oauth2/token'),
        'image_size' => env('IGDB_IMAGE_SIZE', 'cover_big'),
    ],

    'translation' => [
        'provider' => env('TRANSLATION_PROVIDER'),
        'source_locale' => env('TRANSLATION_SOURCE_LOCALE', 'en'),
        'google' => [
            'api_key' => env('GOOGLE_TRANSLATE_API_KEY'),
            'base_url' => env('GOOGLE_TRANSLATE_BASE_URL', 'https://translation.googleapis.com/language/translate/v2'),
        ],
    ],

    'bgg' => [
        'token' => env('BGG_TOKEN'),
    ],

    'omdb' => [
        'api_key' => env('OMDB_API_KEY'),
    ],

];
