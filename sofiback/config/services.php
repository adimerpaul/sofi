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


    // Firebase Web (Google Sign-In opcional en la encuesta publica).
    // Mismas credenciales que usa el frontend (son claves publicas de cliente).
    'firebase' => [
        'api_key'     => env('FB_API_KEY', 'AIzaSyDbMGAIVkyjmE-YctHlWZBuLHAw_ar6SgM'),
        'auth_domain' => env('FB_AUTH_DOMAIN', 'sofia-d7be0.firebaseapp.com'),
        'project_id'  => env('FB_PROJECT_ID', 'sofia-d7be0'),
        'app_id'      => env('FB_APP_ID', '1:324345481536:web:a79861fe237fb1b9d8d2a5'),
    ],

];
