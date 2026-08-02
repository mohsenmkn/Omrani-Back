<?php


return [
//
//    'paths' => [
//        'api/*',
//        'sanctum/csrf-cookie',
//	    'login',
//        'logout',
//        'register',
//        'password/*',
//        'forgot-password',
//        'reset-password',
//    ],
//
//    'allowed_methods' => ['*'],
//
//    'allowed_origins' => [
//        'https://omrani.gttmco.ir',
//        'http://localhost:5173/',
//    ],
//
//    'allowed_origins_patterns' => [],
//
//    'allowed_headers' => ['*'],
//
//    'exposed_headers' => [],
//
//    'max_age' => 0,
//
//    'supports_credentials' => true,


'paths' => ['api/*', 'sanctum/csrf-cookie', 'login'], // مسیرهای خود را اضافه کنید
   'allowed_methods' => ['*'],
   'allowed_origins' => ['http://localhost:5173', 'http://127.0.0.1:8000', 'http://localhost:3000'], // آدرس دقیق فرانت‌اند ویو خود را بنویسید
   'allowed_origins_patterns' => [],
   'allowed_headers' => ['*'],
   'exposed_headers' => [],
   'max_age' => 0,
   'supports_credentials' => true,// این خط باید حتماً true باشد
];
