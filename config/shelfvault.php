<?php

return [
    'version' => env('SHELFVAULT_VERSION', '0.1.0-dev'),

    'installer' => [
        'lock_path' => env('SHELFVAULT_INSTALLED_LOCK', storage_path('app/shelfvault/installed.lock')),
    ],

    'updates' => [
        'repository' => env('SHELFVAULT_RELEASE_REPOSITORY', 'willi4mbe/shelfvault'),
        'api_base_url' => env('SHELFVAULT_RELEASE_API_BASE_URL', 'https://api.github.com'),
        'timeout' => (int) env('SHELFVAULT_RELEASE_TIMEOUT', 5),
        'installation_mode' => env('SHELFVAULT_INSTALLATION_MODE', 'auto'),
        'backup_required' => true,
    ],

    'locales' => [
        'en' => 'English',
        'fr' => 'Français',
    ],
];
