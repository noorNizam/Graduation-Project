<?php

return [
    // Internal Docker service hostnames, reachable from the app container.
    // These are what the health check probes to determine container status.
    'nginx_url' => env('NGINX_INTERNAL_URL', 'http://nginx/healthz'),
    'reverb_host' => env('REVERB_INTERNAL_HOST', 'reverb'),
    'reverb_port' => (int) env('REVERB_INTERNAL_PORT', env('REVERB_PORT', 8080)),
    'reverb_timeout' => (int) env('REVERB_HEALTH_TIMEOUT', 2),
];
