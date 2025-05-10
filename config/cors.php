<?php


return [
    // 'paths' => ['api/*' , 'backend/*'],
    'paths' => ['*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'], // Use '*' to allow all origins
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];

