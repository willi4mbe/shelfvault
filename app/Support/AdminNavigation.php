<?php

namespace App\Support;

class AdminNavigation
{
    /**
     * @return array<int, array{key: string, route: string, visible: bool, active?: bool, interactive?: bool, icon: string, soon?: bool, logout?: bool}>
     */
    public function items(?string $currentRouteName = null): array
    {
        $currentRouteName ??= '';
        $collectionActive = str_starts_with($currentRouteName, 'admin.collection.');
        $settingsActive = str_starts_with($currentRouteName, 'admin.settings.');

        return array_values(array_filter([
            [
                'key' => 'dashboard',
                'route' => route('admin'),
                'visible' => true,
                'active' => $currentRouteName === 'admin',
                'interactive' => true,
                'icon' => 'dashboard',
            ],
            [
                'key' => 'collection',
                'route' => route('admin.collection.index'),
                'visible' => true,
                'active' => $collectionActive,
                'interactive' => true,
                'icon' => 'collection',
            ],
            ['key' => 'films', 'route' => '#', 'visible' => false, 'interactive' => false, 'icon' => 'films'],
            ['key' => 'video_games', 'route' => '#', 'visible' => false, 'interactive' => false, 'icon' => 'video_games'],
            ['key' => 'board_games', 'route' => '#', 'visible' => false, 'interactive' => false, 'icon' => 'board_games'],
            ['key' => 'loans', 'route' => '#', 'visible' => true, 'interactive' => false, 'icon' => 'loans'],
            [
                'key' => 'settings',
                'route' => route('admin.settings.index'),
                'visible' => true,
                'active' => $settingsActive,
                'interactive' => true,
                'icon' => 'settings',
            ],
            ['key' => 'logout', 'route' => '#', 'visible' => true, 'interactive' => true, 'icon' => 'logout', 'logout' => true],
        ], static fn (array $item): bool => $item['visible']));
    }
}
