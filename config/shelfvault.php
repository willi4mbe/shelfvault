<?php

return [
    'installer' => [
        'lock_path' => env('SHELFVAULT_INSTALLED_LOCK', storage_path('app/shelfvault/installed.lock')),
    ],

    'locales' => [
        'en' => 'English',
        'fr' => 'Français',
    ],
];
