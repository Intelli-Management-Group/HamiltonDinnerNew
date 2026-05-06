<?php

return [
    'key_id'      => env('APNS_KEY_ID'),
    'team_id'     => env('APNS_TEAM_ID'),
    'private_key' => env('APNS_PRIVATE_KEY'),  // full contents of the .p8 file
    'bundle_id'   => env('APNS_BUNDLE_ID'),
    'production'  => env('APNS_PRODUCTION', false),
];
