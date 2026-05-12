<?php

return [
    'key_id'      => env('APNS_KEY_ID'),
    'team_id'     => env('APNS_TEAM_ID'),
    'key_path'    => env('APNS_KEY_PATH'),  // absolute path to the .p8 file
    'bundle_id'   => env('APNS_BUNDLE_ID'),
    'production'  => env('APNS_PRODUCTION', false),
];
