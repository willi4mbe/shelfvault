<?php

namespace App\Http\Controllers;

use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\ItemLoan;
use App\Services\Installer\InstallationState;
use App\Services\Library\LibrarySettings;
use App\Support\AdminNavigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminLoanController extends Controller
{
    public function index(
        InstallationState $installationState,
        Request $request,
        AdminNavigation $navigation,
        LibrarySettings $librarySettings,
    ): RedirectResponse|View {
        if ($redirect = $this->guardAccess($installationState, $librarySettings)) {
            return $redirect;
        }

        $activeLoans = ItemLoan::query()
            ->with('item')
            ->active()
            ->latest('loaned_at')
            ->get();

        $returnedLoans = ItemLoan::query()
            ->with('item')
            ->returned()
            ->latest('returned_at')
            ->limit(50)
            ->get();

        $loanableItems = Item::query()
            ->whereIn('type', $librarySettings->enabledTypeValues())
            ->whereDoesntHave('itemLoans', fn ($query) => $query->active())
            ->orderByRaw('LOWER(COALESCE(sort_title, title)) asc')
            ->get();

        return view('admin.loans.index', [
            'navigation' => $navigation->items($request->route()?->getName()),
            'activeLoans' => $activeLoans,
            'returnedLoans' => $returnedLoans,
            'loanableItems' => $loanableItems,
            'selectedItemId' => $request->integer('item') ?: null,
        ]);
    }

    public function store(
        InstallationState $installationState,
        Request $request,
        LibrarySettings $librarySettings,
    ): RedirectResponse {
        if ($redirect = $this->guardAccess($installationState, $librarySettings)) {
            return $redirect;
        }

        $validated = $request->validate([
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'borrower_name' => ['required', 'string', 'max:120'],
            'loaned_at' => ['required', 'date'],
            'expected_return_at' => ['nullable', 'date', 'after_or_equal:loaned_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'item_id' => __('admin.loans.fields.item'),
            'borrower_name' => __('admin.loans.fields.borrower_name'),
            'loaned_at' => __('admin.loans.fields.loaned_at'),
            'expected_return_at' => __('admin.loans.fields.expected_return_at'),
            'notes' => __('admin.loans.fields.notes'),
        ]);

        $item = Item::query()->findOrFail($validated['item_id']);

        if (! $librarySettings->isTypeEnabled($item->type)) {
            throw ValidationException::withMessages([
                'item_id' => __('admin.loans.validation.disabled_type'),
            ]);
        }

        if ($item->itemLoans()->active()->exists()) {
            throw ValidationException::withMessages([
                'item_id' => __('admin.loans.validation.already_loaned'),
            ]);
        }

        $loan = ItemLoan::query()->create([
            'item_id' => $item->id,
            'borrower_name' => $validated['borrower_name'],
            'loaned_at' => $validated['loaned_at'],
            'expected_return_at' => $validated['expected_return_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        $item->forceFill(['status' => ItemStatus::Loaned])->save();

        return redirect()
            ->route('admin.loans.index')
            ->with('status', __('admin.loans.notifications.created', ['title' => $loan->item->title]));
    }

    public function markReturned(
        InstallationState $installationState,
        LibrarySettings $librarySettings,
        ItemLoan $loan,
    ): RedirectResponse {
        if ($redirect = $this->guardAccess($installationState, $librarySettings)) {
            return $redirect;
        }

        if ($loan->returned_at === null) {
            $loan->forceFill(['returned_at' => now()->toDateString()])->save();
        }

        $item = $loan->item;

        if ($item !== null && ! $item->itemLoans()->active()->exists()) {
            $item->forceFill(['status' => ItemStatus::Owned])->save();
        }

        return redirect()
            ->route('admin.loans.index')
            ->with('status', __('admin.loans.notifications.returned', ['title' => $loan->item?->title ?? __('admin.loans.item_missing')]));
    }

    private function guardAccess(InstallationState $installationState, LibrarySettings $librarySettings): ?RedirectResponse
    {
        if (! $installationState->installed()) {
            return redirect()->route('install.show');
        }

        if (! Auth::check()) {
            return redirect()->route('login');
        }

        abort_unless($librarySettings->loansEnabled(), 404);

        return null;
    }
}
