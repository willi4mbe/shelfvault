<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemLoan;
use App\Services\Installer\InstallationState;
use App\Support\AdminNavigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(InstallationState $installationState, AdminNavigation $navigation): RedirectResponse|View
    {
        if (! $installationState->installed()) {
            return redirect()->route('install.show');
        }

        if (! Auth::check()) {
            return redirect()->route('login');
        }

        return view('admin.dashboard', [
            'navigation' => $navigation->items(request()->route()?->getName()),
            'stats' => $this->stats(),
            'quickAccess' => $this->quickAccess(),
            'overview' => $this->overview(),
            'activity' => $this->activity(),
            'setupStatus' => $this->setupStatus(),
        ]);
    }

    /**
     * @return array<int, array{key: string, value: int, tone: string}>
     */
    private function stats(): array
    {
        return [
            ['key' => 'total_items', 'value' => Item::query()->count(), 'tone' => 'blue', 'icon' => 'total_items'],
            ['key' => 'films', 'value' => Item::film()->count(), 'tone' => 'violet', 'icon' => 'films'],
            ['key' => 'video_games', 'value' => Item::videoGame()->count(), 'tone' => 'emerald', 'icon' => 'video_games'],
            ['key' => 'board_games', 'value' => Item::boardGame()->count(), 'tone' => 'amber', 'icon' => 'board_games'],
            ['key' => 'loans', 'value' => ItemLoan::active()->count(), 'tone' => 'rose', 'icon' => 'loans'],
            ['key' => 'recent_additions', 'value' => Item::recent()->count(), 'tone' => 'slate', 'icon' => 'recent_additions'],
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
            ['key' => 'settings', 'icon' => 'settings', 'note' => 'settings_note', 'soon' => false, 'tone' => 'emerald'],
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
