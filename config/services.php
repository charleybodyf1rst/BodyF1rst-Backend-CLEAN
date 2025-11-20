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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'passio' => [
        'api_key' => env('PASSIO_API_KEY', ''),
        'base_url' => env('PASSIO_BASE_URL', 'https://api.passiolife.com'),
        'timeout' => env('PASSIO_TIMEOUT', 15),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY', env('CHATGPT_API_KEY', '')),
        'organization' => env('OPENAI_ORGANIZATION', ''),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'timeout' => env('OPENAI_TIMEOUT', 60),
    ],

    'elevenlabs' => [
        'api_key' => env('ELEVENLABS_API_KEY', ''),
        'voice_id' => env('ELEVENLABS_VOICE_ID', ''),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY', ''),
        'secret' => env('STRIPE_SECRET', ''),
        'public_key' => env('STRIPE_PUBLIC_KEY', ''),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
    ],

    'onesignal' => [
        'app_id' => env('ONESIGNAL_APP_ID', ''),
        'rest_api_key' => env('ONESIGNAL_REST_API_KEY', ''),
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID', ''),
        'auth_token' => env('TWILIO_AUTH_TOKEN', ''),
        'phone_number' => env('TWILIO_PHONE_NUMBER', ''),
    ],

    'replicate' => [
        'api_key' => env('REPLICATE_API_KEY', ''),
        'base_url' => env('REPLICATE_BASE_URL', 'https://api.replicate.com/v1'),
    ],

    'scenario' => [
        'api_key' => env('SCENARIO_API_KEY', ''),
        'base_url' => env('SCENARIO_BASE_URL', 'https://api.scenario.gg/v1'),
    ],

    'fatsecret' => [
        'client_id' => env('FATSECRET_CLIENT_ID', ''),
        'client_secret' => env('FATSECRET_CLIENT_SECRET', ''),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID', ''),
        'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI', ''),
    ],

    'did' => [
        'api_key' => env('DID_API_KEY', ''),
        'base_url' => env('DID_BASE_URL', 'https://api.d-id.com'),
    ],

    'aiml' => [
        'api_key' => env('AIML_API_KEY', ''),
        'base_url' => env('AIML_BASE_URL', 'https://api.aimlapi.com/v2'),
    ],

    'support_mail' => [
        'host' => env('SUPPORT_MAIL_HOST', 'smtp.gmail.com'),
        'port' => env('SUPPORT_MAIL_PORT', 587),
        'username' => env('SUPPORT_MAIL_USERNAME', ''),
        'password' => env('SUPPORT_MAIL_PASSWORD', ''),
        'encryption' => env('SUPPORT_MAIL_ENCRYPTION', 'tls'),
        'from_address' => env('SUPPORT_MAIL_FROM_ADDRESS', ''),
        'from_name' => env('SUPPORT_MAIL_FROM_NAME', 'BodyF1rst Support'),
    ],

];
