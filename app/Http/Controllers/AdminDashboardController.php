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
        ]);
    }

    /**
     * @return array<int, array{key: string, value: int, tone: string}>
     */
    private function stats(): array
    {
        return [
            ['key' => 'total_items', 'value' => Item::query()->count(), 'tone' => 'blue', 'icon' => 'total_items'],
            ['key' => 'films', 'value' => Item::film()->count(), 'tone' => 'amber', 'icon' => 'films'],
            ['key' => 'tv_series', 'value' => Item::tvSeries()->count(), 'tone' => 'sky', 'icon' => 'tv_series'],
            ['key' => 'video_games', 'value' => Item::videoGame()->count(), 'tone' => 'emerald', 'icon' => 'video_games'],
            ['key' => 'board_games', 'value' => Item::boardGame()->count(), 'tone' => 'violet', 'icon' => 'board_games'],
            ['key' => 'loans', 'value' => ItemLoan::active()->count(), 'tone' => 'rose', 'icon' => 'loans'],
            ['key' => 'recent_additions', 'value' => Item::recent()->count(), 'tone' => 'slate', 'icon' => 'recent_additions'],
        ];
    }
}
