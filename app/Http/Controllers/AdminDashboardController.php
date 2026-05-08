<?php

namespace App\Http\Controllers;

use App\Services\Installer\InstallationState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(InstallationState $installationState): RedirectResponse|View
    {
        if (! $installationState->installed()) {
            return redirect()->route('install.show');
        }

        if (! Auth::check()) {
            return redirect()->route('login');
        }

        return view('admin.dashboard', [
            'navigation' => $this->navigation(),
            'stats' => $this->stats(),
            'quickAccess' => $this->quickAccess(),
            'overview' => $this->overview(),
            'activity' => $this->activity(),
            'setupStatus' => $this->setupStatus(),
        ]);
    }

    /**
     * @return array<int, array{key: string, route: string, visible: bool, active?: bool}>
     */
    private function navigation(): array
    {
        return array_values(array_filter([
            ['key' => 'dashboard', 'route' => route('admin'), 'visible' => true, 'active' => true, 'interactive' => true, 'icon' => 'dashboard'],
            ['key' => 'collection', 'route' => '#', 'visible' => true, 'interactive' => false, 'icon' => 'collection', 'soon' => true],
            ['key' => 'films', 'route' => '#', 'visible' => false, 'interactive' => false, 'icon' => 'films'],
            ['key' => 'video_games', 'route' => '#', 'visible' => false, 'interactive' => false, 'icon' => 'video_games'],
            ['key' => 'board_games', 'route' => '#', 'visible' => false, 'interactive' => false, 'icon' => 'board_games'],
            ['key' => 'loans', 'route' => '#', 'visible' => true, 'interactive' => false, 'icon' => 'loans', 'soon' => true],
            ['key' => 'settings', 'route' => '#', 'visible' => true, 'interactive' => false, 'icon' => 'settings', 'soon' => true],
            ['key' => 'logout', 'route' => '#', 'visible' => true, 'interactive' => true, 'icon' => 'logout', 'logout' => true],
        ], static fn (array $item): bool => $item['visible']));
    }

    /**
     * @return array<int, array{key: string, value: int, tone: string}>
     */
    private function stats(): array
    {
        return [
            ['key' => 'total_items', 'value' => 0, 'tone' => 'blue', 'icon' => 'total_items'],
            ['key' => 'films', 'value' => 0, 'tone' => 'violet', 'icon' => 'films'],
            ['key' => 'video_games', 'value' => 0, 'tone' => 'emerald', 'icon' => 'video_games'],
            ['key' => 'board_games', 'value' => 0, 'tone' => 'amber', 'icon' => 'board_games'],
            ['key' => 'loans', 'value' => 0, 'tone' => 'rose', 'icon' => 'loans'],
            ['key' => 'recent_additions', 'value' => 0, 'tone' => 'slate', 'icon' => 'recent_additions'],
        ];
    }

    /**
     * @return array<int, array{key: string, title: string, note: string}>
     */
    private function quickAccess(): array
    {
        return [
            ['key' => 'collection', 'icon' => 'collection', 'note' => 'collection_note', 'soon' => true, 'tone' => 'blue'],
            ['key' => 'loans', 'icon' => 'loans', 'note' => 'loans_note', 'soon' => true, 'tone' => 'violet'],
            ['key' => 'settings', 'icon' => 'settings', 'note' => 'settings_note', 'soon' => true, 'tone' => 'emerald'],
        ];
    }

    /**
     * @return array<int, array{key: string, value: string, detail: string}>
     */
    private function overview(): array
    {
        return [
            ['key' => 'catalog', 'value' => '0', 'detail' => 'catalog_detail', 'icon' => 'overview', 'tone' => 'sky'],
            ['key' => 'sync', 'value' => '0', 'detail' => 'sync_detail', 'icon' => 'sync', 'tone' => 'amber'],
            ['key' => 'coverage', 'value' => '0%', 'detail' => 'coverage_detail', 'icon' => 'coverage', 'tone' => 'violet'],
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, value: string}>
     */
    private function activity(): array
    {
        return [];
    }

    /**
     * @return array<int, array{key: string, label: string, value: string}>
     */
    private function setupStatus(): array
    {
        return [
            ['key' => 'admin', 'label' => 'admin', 'value' => 'ready', 'icon' => 'admin', 'tone' => 'emerald'],
            ['key' => 'locale', 'label' => 'locale', 'value' => 'ready', 'icon' => 'locale', 'tone' => 'blue'],
            ['key' => 'catalog', 'label' => 'catalog', 'value' => 'pending', 'icon' => 'catalog', 'tone' => 'amber'],
        ];
    }
}
