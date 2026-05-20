<?php

return [

    'domain' => env('HORIZON_DOMAIN'),
    'path' => env('HORIZON_PATH', 'horizon'),
    'middleware' => ['web'],

    'waits' => [
        'redis:emails'  => 30,
        'redis:default' => 60,
        'redis:media'   => 120,
    ],

    'trim' => [
        'recent'        => 60 * 24,
        'pending'       => 60 * 24,
        'completed'     => 60 * 24,
        'recent_failed' => 60 * 24 * 7,
        'failed'        => 60 * 24 * 30,
        'monitored'     => 60 * 24 * 7,
    ],

    'environments' => [
        'production' => [
            'supervisor-1' => [
                'connection'  => 'redis',
                'queue'       => ['emails', 'default', 'media'],
                'balance'     => 'auto',
                'autoScaling' => [
                    'maxProcesses' => 10,
                    'maxWorkTime'  => 5,
                    'maxIdleTime'  => 30,
                ],
                'maxProcesses' => 10,
                'maxTime'      => 0,
                'maxJobs'      => 1000,
                'memory'       => 256,
                'tries'        => 3,
                'timeout'      => 120,
                'nice'         => 0,
                'balanceCooldown' => 3,
                'balanceMaxShift'  => 1,
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue'      => ['emails', 'default', 'media'],
                'balance'    => 'simple',
                'processes'  => 3,
                'tries'      => 3,
                'timeout'    => 90,
                'memory'     => 128,
            ],
        ],

        'staging' => [
            'supervisor-1' => [
                'connection'  => 'redis',
                'queue'       => ['emails', 'default', 'media'],
                'balance'     => 'auto',
                'autoScaling' => ['maxProcesses' => 5],
                'maxProcesses' => 5,
                'maxJobs'      => 500,
                'memory'       => 128,
                'tries'        => 3,
                'timeout'      => 120,
            ],
        ],
    ],

    'graceful_shutdown' => env('HORIZON_GRACEFUL_SHUTDOWN', 60),
    'fast_completion' => env('HORIZON_FAST_COMPLETION', true),
];