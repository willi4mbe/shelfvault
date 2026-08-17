<?php

namespace App\Http\Controllers;

use App\Enums\ItemCondition;
use App\Enums\ItemStatus;
use App\Enums\ItemType;
use App\Http\Requests\Admin\ItemUpsertRequest;
use App\Models\Item;
use App\Services\Installer\InstallationState;
use App\Support\AdminNavigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

class AdminCollectionController extends Controller
{
    public function index(InstallationState $installationState, Request $request, AdminNavigation $navigation): RedirectResponse|View
    {
        if (! $installationState->installed()) {
            return redirect()->route('install.show');
        }

        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $filters = $this->filters($request);

        $query = Item::query()->orderByRaw('LOWER(COALESCE(sort_title, title)) asc');

        if ($filters['search'] !== null) {
            $search = $filters['search'];

            $query->where(function ($subQuery) use ($search): void {
                $subQuery->where('title', 'like', '%'.$search.'%')
                    ->orWhere('barcode', 'like', '%'.$search.'%');
            });
        }

        if ($filters['type'] !== null) {
            $query->where('type', $filters['type']);
        }

        if ($filters['status'] !== null) {
            $query->where('status', $filters['status']);
        }

        $items = $query->get();

        return view('admin.collection.index', [
            'navigation' => $navigation->items($request->route()?->getName()),
            'items' => $items,
            'filters' => $filters,
            'typeOptions' => $this->typeOptions(),
            'statusOptions' => $this->statusOptions(),
            'formatLabels' => $this->formatLabels(),
            'conditionLabels' => $this->conditionLabels(),
        ]);
    }

    public function create(InstallationState $installationState, Request $request, AdminNavigation $navigation): RedirectResponse|View
    {
        if (! $installationState->installed()) {
            return redirect()->route('install.show');
        }

        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $type = old('type', '');

        return view('admin.collection.create', [
            'navigation' => $navigation->items($request->route()?->getName()),
            'item' => new Item(),
            'typeOptions' => $this->typeOptions(),
            'statusOptions' => $this->statusOptions(),
            'conditionOptions' => $this->conditionOptions(),
            'formatOptions' => $this->formatOptions(),
            'selectedType' => $type,
            'backUrl' => route('admin.collection.index'),
        ]);
    }

    public function show(InstallationState $installationState, Request $request, AdminNavigation $navigation, Item $item): RedirectResponse|View
    {
        if (! $installationState->installed()) {
            return redirect()->route('install.show');
        }

        if (! Auth::check()) {
            return redirect()->route('login');
        }

        return view('admin.collection.show', [
            'navigation' => $navigation->items($request->route()?->getName()),
            'item' => $item,
            'backUrl' => route('admin.collection.index'),
        ]);
    }

    public function store(InstallationState $installationState, ItemUpsertRequest $request): RedirectResponse
    {
        if (! $installationState->installed()) {
            return redirect()->route('install.show');
        }

        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $data = $request->normalizedData();
        if ($request->hasFile('cover_image')) {
            $data['cover_path'] = $this->storeCover($request->file('cover_image'));
        }

        $item = Item::query()->create($data);

        return redirect()
            ->route('admin.collection.index')
            ->with('status', __('admin.collection.notifications.created', ['title' => $item->title]));
    }

    public function edit(InstallationState $installationState, Request $request, AdminNavigation $navigation, Item $item): RedirectResponse|View
    {
        if (! $installationState->installed()) {
            return redirect()->route('install.show');
        }

        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $selectedType = old('type', $item->type?->value ?? ItemType::Film->value);

        return view('admin.collection.edit', [
            'navigation' => $navigation->items($request->route()?->getName()),
            'item' => $item,
            'typeOptions' => $this->typeOptions(),
            'statusOptions' => $this->statusOptions(),
            'conditionOptions' => $this->conditionOptions(),
            'formatOptions' => $this->formatOptions(),
            'selectedType' => $selectedType,
            'backUrl' => route('admin.collection.index'),
        ]);
    }

    public function update(InstallationState $installationState, ItemUpsertRequest $request, Item $item): RedirectResponse
    {
        if (! $installationState->installed()) {
            return redirect()->route('install.show');
        }

        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $originalCoverPath = $item->cover_path;
        $data = $request->normalizedData();

        if ($request->hasFile('cover_image')) {
            $data['cover_path'] = $this->storeCover($request->file('cover_image'));
        } elseif ($request->boolean('remove_cover')) {
            $data['cover_path'] = null;
        } elseif (filled($data['cover_path']) && $data['cover_path'] !== $originalCoverPath) {
            $data['cover_path'] = $data['cover_path'];
        } else {
            $data['cover_path'] = $originalCoverPath;
        }

        $item->fill($data);
        $item->save();

        if ($originalCoverPath !== $item->cover_path) {
            $this->deleteStoredCover($originalCoverPath);
        }

        return redirect()
            ->route('admin.collection.index')
            ->with('status', __('admin.collection.notifications.updated', ['title' => $item->title]));
    }

    public function destroy(InstallationState $installationState, Item $item): RedirectResponse
    {
        if (! $installationState->installed()) {
            return redirect()->route('install.show');
        }

        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $title = $item->title;
        $this->deleteStoredCover($item->cover_path);
        $item->delete();

        return redirect()
            ->route('admin.collection.index')
            ->with('status', __('admin.collection.notifications.deleted', ['title' => $title]));
    }

    /**
     * @return array{search: ?string, type: ?string, status: ?string}
     */
    private function filters(Request $request): array
    {
        $search = trim((string) $request->string('q'));
        $type = $request->string('type')->toString();
        $status = $request->string('status')->toString();

        $allowedTypes = array_map(static fn (ItemType $type): string => $type->value, ItemType::cases());
        $allowedStatuses = array_map(static fn (ItemStatus $status): string => $status->value, ItemStatus::cases());
        $allowedStatuses = array_values(array_filter($allowedStatuses, static fn (string $status): bool => $status !== ItemStatus::Wanted->value));

        return [
            'search' => $search !== '' ? $search : null,
            'type' => in_array($type, $allowedTypes, true) ? $type : null,
            'status' => in_array($status, $allowedStatuses, true) ? $status : null,
        ];
    }

    private function storeCover(?UploadedFile $file): ?string
    {
        if ($file === null) {
            return null;
        }

        return $file->storePublicly('covers', 'public');
    }

    private function deleteStoredCover(?string $coverPath): void
    {
        if ($coverPath === null || filter_var($coverPath, FILTER_VALIDATE_URL)) {
            return;
        }

        $disk = Storage::disk('public');

        if ($disk->exists($coverPath)) {
            $disk->delete($coverPath);
        }
    }

    /**
     * @return array<string, string>
     */
    private function typeOptions(): array
    {
        return [
            ItemType::Film->value => __('admin.collection.types.'.ItemType::Film->value),
            ItemType::TvSeries->value => __('admin.collection.types.'.ItemType::TvSeries->value),
            ItemType::VideoGame->value => __('admin.collection.types.'.ItemType::VideoGame->value),
            ItemType::BoardGame->value => __('admin.collection.types.'.ItemType::BoardGame->value),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function statusOptions(): array
    {
        return [
            ItemStatus::Owned->value => __('admin.collection.statuses.'.ItemStatus::Owned->value),
            ItemStatus::Loaned->value => __('admin.collection.statuses.'.ItemStatus::Loaned->value),
            ItemStatus::Archived->value => __('admin.collection.statuses.'.ItemStatus::Archived->value),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function conditionOptions(): array
    {
        return [
            '' => __('admin.collection.placeholders.none'),
            ItemCondition::New->value => __('admin.collection.conditions.'.ItemCondition::New->value),
            ItemCondition::VeryGood->value => __('admin.collection.conditions.'.ItemCondition::VeryGood->value),
            ItemCondition::Good->value => __('admin.collection.conditions.'.ItemCondition::Good->value),
            ItemCondition::Acceptable->value => __('admin.collection.conditions.'.ItemCondition::Acceptable->value),
            ItemCondition::Damaged->value => __('admin.collection.conditions.'.ItemCondition::Damaged->value),
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function formatOptions(): array
    {
        return [
            ItemType::Film->value => [
                '' => __('admin.collection.placeholders.none'),
                'dvd' => __('admin.collection.formats.film.dvd'),
                'blu_ray' => __('admin.collection.formats.film.blu_ray'),
                '4k_uhd' => __('admin.collection.formats.film.four_k_uhd'),
                'vhs' => __('admin.collection.formats.film.vhs'),
                'digital_copy' => __('admin.collection.formats.film.digital_copy'),
            ],
            ItemType::TvSeries->value => [
                '' => __('admin.collection.placeholders.none'),
                'dvd' => __('admin.collection.formats.tv_series.dvd'),
                'blu_ray' => __('admin.collection.formats.tv_series.blu_ray'),
                '4k_uhd' => __('admin.collection.formats.tv_series.four_k_uhd'),
                'box_set' => __('admin.collection.formats.tv_series.box_set'),
                'digital_copy' => __('admin.collection.formats.tv_series.digital_copy'),
            ],
            ItemType::VideoGame->value => [
                '' => __('admin.collection.placeholders.none'),
                'cartridge' => __('admin.collection.formats.video_game.cartridge'),
                'disc' => __('admin.collection.formats.video_game.disc'),
                'code_in_box' => __('admin.collection.formats.video_game.code_in_box'),
                'collector_edition' => __('admin.collection.formats.video_game.collector_edition'),
                'digital_copy' => __('admin.collection.formats.video_game.digital_copy'),
            ],
            ItemType::BoardGame->value => [
                '' => __('admin.collection.placeholders.none'),
                'box' => __('admin.collection.formats.board_game.box'),
                'expansion' => __('admin.collection.formats.board_game.expansion'),
                'card_game' => __('admin.collection.formats.board_game.card_game'),
                'accessory' => __('admin.collection.formats.board_game.accessory'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function formatLabels(): array
    {
        return array_merge(
            $this->formatOptions()[ItemType::Film->value],
            $this->formatOptions()[ItemType::TvSeries->value],
            $this->formatOptions()[ItemType::VideoGame->value],
            $this->formatOptions()[ItemType::BoardGame->value],
        );
    }

    /**
     * @return array<string, string>
     */
    private function conditionLabels(): array
    {
        return [
            ItemCondition::New->value => __('admin.collection.conditions.'.ItemCondition::New->value),
            ItemCondition::VeryGood->value => __('admin.collection.conditions.'.ItemCondition::VeryGood->value),
            ItemCondition::Good->value => __('admin.collection.conditions.'.ItemCondition::Good->value),
            ItemCondition::Acceptable->value => __('admin.collection.conditions.'.ItemCondition::Acceptable->value),
            ItemCondition::Damaged->value => __('admin.collection.conditions.'.ItemCondition::Damaged->value),
        ];
    }
}
