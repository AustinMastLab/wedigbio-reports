<?php

use App\Support\ResponseCache\ArchivedEventChartCacheProfile;
use Spatie\ResponseCache\Hasher\DefaultHasher;
use Spatie\ResponseCache\Replacers\CsrfTokenReplacer;
use Spatie\ResponseCache\Serializers\JsonSerializer;

return [
    'enabled' => env('RESPONSE_CACHE_ENABLED', true),

    'cache' => [
        'store' => env('RESPONSE_CACHE_DRIVER', env('CACHE_STORE', 'redis')),
        'lifetime_in_seconds' => (int) env('RESPONSE_CACHE_LIFETIME', 60 * 60 * 24 * 7),
        'tag' => env('RESPONSE_CACHE_TAG', ''),
    ],

    'bypass' => [
        'header_name' => env('CACHE_BYPASS_HEADER_NAME'),
        'header_value' => env('CACHE_BYPASS_HEADER_VALUE'),
    ],

    'debug' => [
        'enabled' => env('APP_DEBUG', false),
        'cache_time_header_name' => 'X-Cache-Time',
        'cache_status_header_name' => 'X-Cache-Status',
        'cache_age_header_name' => 'X-Cache-Age',
        'cache_key_header_name' => 'X-Cache-Key',
    ],

    'ignored_query_parameters' => [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'gclid',
        'fbclid',
    ],

    'cache_profile' => ArchivedEventChartCacheProfile::class,

    'hasher' => DefaultHasher::class,

    'serializer' => JsonSerializer::class,

    'replacers' => [
        CsrfTokenReplacer::class,
    ],
];

