<?php

namespace App\Http\Controllers;

use App\Enums\ItemType;
use App\Services\Installer\InstallationState;
use App\Services\Metadata\BarcodeLookupService;
use App\Services\Metadata\MetadataLookupResult;
use App\Services\Metadata\TmdbMovieSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AdminMetadataLookupController extends Controller
{
    public function search(
        InstallationState $installationState,
        Request $request,
        BarcodeLookupService $barcodeLookupService,
        TmdbMovieSearchService $tmdbMovieSearchService,
    ): JsonResponse {
        $guard = $this->guardAdmin($installationState, __('admin.collection.metadata.tmdb_not_configured'));

        if ($guard !== null) {
            return $guard;
        }

        $validated = $request->validate([
            'mode' => ['required', Rule::in(['title', 'barcode'])],
            'type' => ['nullable', 'string', 'max:32'],
            'title' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:128'],
            'release_year' => ['nullable', 'integer', 'between:1800,2100'],
        ]);

        if ($validated['mode'] === 'title') {
            $result = $this->searchByTitle($validated, $tmdbMovieSearchService);
        } else {
            $result = $this->searchByBarcode($validated, $barcodeLookupService, $tmdbMovieSearchService);
        }

        return response()->json($result->toArray(), $result->statusCode);
    }

    public function import(
        InstallationState $installationState,
        Request $request,
        BarcodeLookupService $barcodeLookupService,
        TmdbMovieSearchService $tmdbMovieSearchService,
    ): JsonResponse {
        $guard = $this->guardAdmin($installationState, __('admin.collection.metadata.tmdb_not_configured'));

        if ($guard !== null) {
            return $guard;
        }

        $validated = $request->validate([
            'source' => ['required', Rule::in(['tmdb', 'barcode'])],
            'tmdb_id' => ['nullable', 'integer', 'min:1'],
            'barcode' => ['nullable', 'string', 'max:128'],
            'type' => ['nullable', 'string', 'max:32'],
        ]);

        $result = $validated['source'] === 'barcode'
            ? $this->importBarcodeResult($validated, $barcodeLookupService)
            : $tmdbMovieSearchService->importMovie((int) ($validated['tmdb_id'] ?? 0));

        return response()->json($result->toArray(), $result->statusCode);
    }

    public function tmdbSearch(
        InstallationState $installationState,
        Request $request,
        BarcodeLookupService $barcodeLookupService,
        TmdbMovieSearchService $tmdbMovieSearchService,
    ): JsonResponse {
        return $this->search($installationState, $request->merge(['mode' => 'title']), $barcodeLookupService, $tmdbMovieSearchService);
    }

    public function tmdbImport(
        InstallationState $installationState,
        Request $request,
        BarcodeLookupService $barcodeLookupService,
        TmdbMovieSearchService $tmdbMovieSearchService,
    ): JsonResponse {
        return $this->import($installationState, $request->merge(['source' => 'tmdb']), $barcodeLookupService, $tmdbMovieSearchService);
    }

    public function barcodeLookup(
        InstallationState $installationState,
        Request $request,
        BarcodeLookupService $barcodeLookupService,
        TmdbMovieSearchService $tmdbMovieSearchService,
    ): JsonResponse {
        return $this->search($installationState, $request->merge(['mode' => 'barcode']), $barcodeLookupService, $tmdbMovieSearchService);
    }

    private function guardAdmin(InstallationState $installationState, string $notInstalledMessage): ?JsonResponse
    {
        if (! $installationState->installed()) {
            return response()->json([
                'status' => 'not_installed',
                'message' => $notInstalledMessage,
                'data' => [],
                'source' => null,
            ], 404);
        }

        if (! Auth::check()) {
            return response()->json([
                'status' => 'unauthenticated',
                'message' => __('admin.auth.failed'),
                'data' => [],
                'source' => null,
            ], 401);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function searchByTitle(array $validated, TmdbMovieSearchService $tmdbMovieSearchService): MetadataLookupResult
    {
        $type = (string) ($validated['type'] ?? '');
        $title = trim((string) ($validated['title'] ?? ''));
        $releaseYear = isset($validated['release_year']) ? (int) $validated['release_year'] : null;

        if ($type === '') {
            return MetadataLookupResult::invalid(__('admin.collection.lookup.choose_type_before_searching'));
        }

        if ($title === '') {
            return MetadataLookupResult::invalid(__('admin.collection.lookup.enter_title_to_search'));
        }

        if ($type !== ItemType::Film->value) {
            return MetadataLookupResult::invalid(__('admin.collection.lookup.automatic_search_not_available_for_this_type'));
        }

        return $tmdbMovieSearchService->search($title, $releaseYear);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function searchByBarcode(array $validated, BarcodeLookupService $barcodeLookupService, TmdbMovieSearchService $tmdbMovieSearchService): MetadataLookupResult
    {
        $barcode = trim((string) ($validated['barcode'] ?? ''));
        $type = trim((string) ($validated['type'] ?? ''));

        if ($barcode === '') {
            return MetadataLookupResult::invalid(__('admin.collection.lookup.enter_barcode_to_search'));
        }

        if (! preg_match('/^\d{8,14}$/', $barcode)) {
            return MetadataLookupResult::invalid(__('admin.collection.lookup.enter_valid_barcode'));
        }

        $lookup = $barcodeLookupService->lookup($barcode, $type !== '' ? $type : null);

        if ($lookup->status !== 'found') {
            return $this->translateBarcodeLookupResult($lookup, $barcode);
        }

        $data = $lookup->data;

        if ($tmdbMovieSearchService->configured() && $this->shouldSearchTmdbFromBarcode($data)) {
            $tmdbResult = $tmdbMovieSearchService->search(
                (string) ($data['title'] ?? ''),
                isset($data['release_year']) ? (int) $data['release_year'] : null,
            );

            if ($tmdbResult->status === 'found') {
                return $tmdbResult;
            }
        }

        return MetadataLookupResult::found([
            'query' => $barcode,
            'candidates' => [$this->mapBarcodeCandidate($data)],
        ], __('admin.collection.metadata.results_found'), 'barcode');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function importBarcodeResult(array $validated, BarcodeLookupService $barcodeLookupService): MetadataLookupResult
    {
        $barcode = trim((string) ($validated['barcode'] ?? ''));

        if ($barcode === '') {
            return MetadataLookupResult::invalid(__('admin.collection.lookup.enter_barcode_to_search'));
        }

        if (! preg_match('/^\d{8,14}$/', $barcode)) {
            return MetadataLookupResult::invalid(__('admin.collection.lookup.enter_valid_barcode'));
        }

        $lookup = $barcodeLookupService->lookup($barcode, isset($validated['type']) ? (string) $validated['type'] : null);

        if ($lookup->status !== 'found') {
            return $this->translateBarcodeLookupResult($lookup, $barcode);
        }

        return MetadataLookupResult::found($lookup->data, __('admin.collection.metadata.metadata_imported'), 'barcode');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function mapBarcodeCandidate(array $data): array
    {
        return array_filter([
            'source' => 'barcode',
            'id' => (string) ($data['barcode'] ?? $data['title'] ?? ''),
            'barcode' => $data['barcode'] ?? null,
            'title' => $data['title'] ?? null,
            'original_title' => $data['original_title'] ?? null,
            'release_year' => $data['release_year'] ?? null,
            'overview' => $data['description'] ?? null,
            'poster_path' => $data['cover_path'] ?? null,
            'poster_url' => $data['cover_url'] ?? null,
            'type' => $data['type'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function shouldSearchTmdbFromBarcode(array $data): bool
    {
        return (($data['type'] ?? null) === ItemType::Film->value || ! filled($data['type'] ?? null))
            && filled($data['title'] ?? null);
    }

    private function translateBarcodeLookupResult(\App\Services\Metadata\BarcodeLookupResult $lookup, string $barcode): MetadataLookupResult
    {
        return match ($lookup->status) {
            'no_source' => MetadataLookupResult::noSource($lookup->message),
            'invalid' => MetadataLookupResult::invalid($lookup->message),
            'not_found' => MetadataLookupResult::notFound($lookup->message, ['barcode' => $barcode], 'barcode'),
            default => MetadataLookupResult::error($lookup->message, $lookup->statusCode),
        };
    }
}
