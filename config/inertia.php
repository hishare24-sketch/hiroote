<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | Where Inertia page components live. `assertInertia(...)->component(...)`
    | resolves names through these paths, so a typo'd component becomes a
    | failing test instead of a blank screen found later in the browser.
    |
    */

    'pages' => [
        'paths' => [
            resource_path('js/Pages'),
        ],

        'extensions' => [
            'tsx',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    */

    'testing' => [
        'ensure_pages_exist' => true,
    ],

];
