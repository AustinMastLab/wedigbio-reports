<?php

return [
    'refresh' => [
        // Live chart polling interval for dashboard + iframe charts.
        'live_chart_ms' => (int) env('WEDIGBIO_LIVE_CHART_REFRESH_MS', 900000),

        // Retry interval when live event has no data yet.
        'live_no_data_retry_ms' => (int) env('WEDIGBIO_LIVE_NO_DATA_RETRY_MS', 60000),
    ],
];

