<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default TaskFlow Queue Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default driver that is used to store and
    | retrieve queued jobs. Supported: "database", "redis"
    |
    */
    'driver' => env('TASKFLOW_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Default Queue Name
    |--------------------------------------------------------------------------
    |
    | The default queue name jobs will be dispatched to unless explicitly
    | specified during dispatching.
    |
    */
    'default_queue' => 'default',
];
