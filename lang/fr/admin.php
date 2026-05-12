<?php

return [
    'brand' => 'ShelfVault',
    'footer' => [
        'version' => 'ShelfVault v0.1.0-dev',
    ],
    'navigation' => [
        'home' => 'Accueil',
        'dashboard' => 'Tableau de bord',
        'collection' => 'Collection',
        'films' => 'Films',
        'video_games' => 'Jeux vidéo',
        'board_games' => 'Jeux de société',
        'loans' => 'Prêts',
        'settings' => 'Paramètres',
    ],
    'sidebar' => [
        'title' => 'Admin ShelfVault',
        'navigation' => 'Navigation',
    ],
    'actions' => [
        'logout' => 'Se déconnecter',
    ],
    'auth' => [
        'page_title' => 'Connexion admin ShelfVault',
        'badge' => 'Accès admin',
        'title' => 'Connectez-vous à ShelfVault',
        'subtitle' => 'Utilisez le login ou l’email admin créés pendant la configuration.',
        'identifier_label' => 'Login ou email',
        'identifier_help' => 'Utilisez l’identifiant créé dans l’assistant.',
        'password_label' => 'Mot de passe',
        'submit' => 'Se connecter',
        'failed' => 'Ces identifiants ne correspondent à aucun compte.',
        'sidebar_title' => 'Connexion privée',
        'sidebar_note' => 'Utilisez Accueil pour revenir au site public.',
    ],
    'dashboard' => [
        'page_title' => 'Administration ShelfVault',
        'title' => 'Tableau de bord',
        'stats_heading' => 'Vue d’ensemble',
        'stats_title' => 'Indicateurs clés',
        'quick_access_heading' => 'Accès rapides',
        'quick_access_title' => 'Raccourcis pour la suite',
        'overview_heading' => 'Vue d’ensemble de la bibliothèque',
        'overview_title' => 'Couverture actuelle des données',
        'activity_heading' => 'Activité récente',
        'activity_title' => 'Derniers signaux de l’espace admin',
        'activity_empty' => 'Aucune activité pour le moment.',
        'setup_heading' => 'État de la configuration',
        'setup_title' => 'Ce qui est déjà en place',
        'soon' => 'Bientôt',
        'blocks' => [
            'move_block' => 'Déplacer le bloc',
            'collapse_block' => 'Réduire le bloc',
            'expand_block' => 'Développer le bloc',
        ],
        'stats' => [
            'total_items' => [
                'label' => 'Total',
                'hint' => 'Nombre total d’objets dans votre bibliothèque.',
            ],
            'films' => [
                'label' => 'Films',
                'hint' => 'Films enregistrés dans votre collection.',
            ],
            'video_games' => [
                'label' => 'Jeux vidéo',
                'hint' => 'Jeux vidéo enregistrés dans votre collection.',
            ],
            'board_games' => [
                'label' => 'Jeux de société',
                'hint' => 'Jeux de société enregistrés dans votre collection.',
            ],
            'loans' => [
                'label' => 'Prêts',
                'hint' => 'Objets actuellement prêtés.',
            ],
            'recent_additions' => [
                'label' => 'Ajouts récents',
                'hint' => 'Objets ajoutés récemment.',
            ],
        ],
        'quick_access' => [
            'collection_note' => 'Parcourir et gérer le catalogue principal.',
            'loans_note' => 'Suivre les prêts quand le module sera prêt.',
            'settings_note' => 'Ajuster les préférences et valeurs par défaut.',
        ],
        'overview' => [
            'catalog' => [
                'title' => 'Couverture du catalogue',
                'detail' => 'La part de la collection déjà structurée.',
            ],
            'sync' => [
                'title' => 'Fiches prêtes',
                'detail' => 'Les entrées préparées pour les premiers tableaux de bord.',
            ],
            'coverage' => [
                'title' => 'Couverture des données',
                'detail' => 'La part de la bibliothèque prête pour les prochains modules.',
            ],
        ],
        'setup' => [
            'admin' => [
                'title' => 'Compte admin',
                'detail' => 'Un seul compte protégé pilote la première version.',
                'state' => 'Prêt',
            ],
            'locale' => [
                'title' => 'Gestion de la langue',
                'detail' => 'L’interface suit la langue préférée de l’admin.',
                'state' => 'Prêt',
            ],
            'catalog' => [
                'title' => 'Socle du catalogue',
                'detail' => 'Le tableau de bord est prêt pour les prochains modules.',
                'state' => 'En attente',
            ],
        ],
    ],
];
