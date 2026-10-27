<?php


return [
    'instance_id' => env('ULTRAMSG_INSTANCE_ID'),
    'token'       => env('ULTRAMSG_API_TOKEN'),
    'base_url'    => env('ULTRAMSG_URI', 'https://api.ultramsg.com'),
    'otp_ttl' => 300,
    'otp_length' => 6,
];
