<?php

return [
    'brand' => 'ShelfVault',
    'footer' => [
        'version' => 'ShelfVault v0.1.0-dev',
    ],
    'navigation' => [
        'home' => 'Home',
        'dashboard' => 'Dashboard',
        'collection' => 'Collection',
        'films' => 'Films',
        'video_games' => 'Video games',
        'board_games' => 'Board games',
        'loans' => 'Loans',
        'settings' => 'Settings',
    ],
    'sidebar' => [
        'title' => 'ShelfVault admin',
        'navigation' => 'Navigation',
    ],
    'actions' => [
        'logout' => 'Log out',
    ],
    'auth' => [
        'page_title' => 'ShelfVault admin login',
        'badge' => 'Admin access',
        'title' => 'Sign in to ShelfVault',
        'subtitle' => 'Use the admin login or email created during setup.',
        'identifier_label' => 'Login or email',
        'identifier_help' => 'Use the identifier created in the installer.',
        'password_label' => 'Password',
        'submit' => 'Sign in',
        'failed' => 'These credentials do not match our records.',
        'sidebar_title' => 'Private sign-in',
        'sidebar_note' => 'Use Home to return to the public site.',
    ],
    'dashboard' => [
        'page_title' => 'ShelfVault admin',
        'title' => 'Dashboard',
        'stats_heading' => 'Overview',
        'stats_title' => 'Key metrics',
        'quick_access_heading' => 'Quick access',
        'quick_access_title' => 'Shortcuts for the next steps',
        'overview_heading' => 'Library overview',
        'overview_title' => 'Current data coverage',
        'activity_heading' => 'Recent activity',
        'activity_title' => 'Latest signals from the admin workspace',
        'activity_empty' => 'No activity yet.',
        'setup_heading' => 'Setup status',
        'setup_title' => 'What is already in place',
        'soon' => 'Soon',
        'blocks' => [
            'move_block' => 'Move block',
            'collapse_block' => 'Collapse block',
            'expand_block' => 'Expand block',
        ],
        'stats' => [
            'total_items' => [
                'label' => 'Total',
                'hint' => 'Everything in the physical library.',
            ],
            'films' => [
                'label' => 'Films',
                'hint' => 'Cinema and TV will land later.',
            ],
            'video_games' => [
                'label' => 'Video games',
                'hint' => 'Console and PC items are reserved for a future pass.',
            ],
            'board_games' => [
                'label' => 'Board games',
                'hint' => 'Tabletop inventory is ready to be wired in.',
            ],
            'loans' => [
                'label' => 'Loans',
                'hint' => 'Borrowing support will land after the core catalog.',
            ],
            'recent_additions' => [
                'label' => 'Recent additions',
                'hint' => 'The latest items added to the library.',
            ],
        ],
        'quick_access' => [
            'collection_note' => 'Browse and manage the core catalog.',
            'loans_note' => 'Track lending activity once the module is ready.',
            'settings_note' => 'Adjust application defaults and preferences.',
        ],
        'overview' => [
            'catalog' => [
                'title' => 'Catalog coverage',
                'detail' => 'How much of the collection is already structured.',
            ],
            'sync' => [
                'title' => 'Ready records',
                'detail' => 'Entries prepared for the first dashboards.',
            ],
            'coverage' => [
                'title' => 'Data coverage',
                'detail' => 'The share of the library ready for the next modules.',
            ],
        ],
        'setup' => [
            'admin' => [
                'title' => 'Admin account',
                'detail' => 'A single protected account manages the first release.',
                'state' => 'Ready',
            ],
            'locale' => [
                'title' => 'Language handling',
                'detail' => 'The interface follows the admin preferred locale.',
                'state' => 'Ready',
            ],
            'catalog' => [
                'title' => 'Catalog backbone',
                'detail' => 'The dashboard is ready for the upcoming content modules.',
                'state' => 'Pending',
            ],
        ],
    ],
];
