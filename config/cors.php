<?php


return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
	'login',
        'logout',
        'register',
        'password/*',
        'forgot-password',
        'reset-password',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://omrani.gttmco.ir',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
