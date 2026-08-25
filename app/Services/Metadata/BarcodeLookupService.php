<?php

namespace App\Services\Metadata;

use App\Services\Metadata\Contracts\BarcodeLookupProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class BarcodeLookupService
{
    /**
     * @param  array<int, string>  $providerClasses
     */
    public function __construct(
        private readonly array $providerClasses = [],
    ) {
    }

    public function lookup(string $barcode, ?string $typeHint = null): BarcodeLookupResult
    {
        $barcode = trim($barcode);

        if ($barcode === '') {
            return BarcodeLookupResult::invalid(__('admin.collection.lookup.invalid_barcode'));
        }

        $providers = $this->providerClasses !== [] ? $this->providerClasses : config('barcode.providers', []);

        if ($providers === []) {
            return BarcodeLookupResult::noSource();
        }

        $lastNotFound = null;

        foreach ($providers as $providerClass) {
            if (! is_string($providerClass) || ! class_exists($providerClass)) {
                continue;
            }

            $provider = app($providerClass);

            if (! $provider instanceof BarcodeLookupProvider) {
                continue;
            }

            $result = $provider->lookup($barcode, $typeHint);

            if ($result->status === 'found') {
                return $this->hydrateCover($result, $barcode, $providerClass);
            }

            if ($result->status === 'no_source') {
                $lastNotFound = $result;
                continue;
            }

            if ($result->status === 'invalid') {
                return $result;
            }

            $lastNotFound = $result;
        }

        return $lastNotFound ?? BarcodeLookupResult::notFound($barcode);
    }

    private function hydrateCover(BarcodeLookupResult $result, string $barcode, string $providerClass): BarcodeLookupResult
    {
        $data = $result->data;

        if (! empty($data['cover_path'])) {
            if (empty($data['cover_url'])) {
                $data['cover_url'] = Storage::disk('public')->url($data['cover_path']);
            }

            return new BarcodeLookupResult(
                $result->status,
                $result->message,
                $data,
                $result->source ?? class_basename($providerClass),
                $result->statusCode,
            );
        }

        if (empty($data['cover_url']) || ! is_string($data['cover_url'])) {
            return new BarcodeLookupResult(
                $result->status,
                $result->message,
                $data,
                $result->source ?? class_basename($providerClass),
                $result->statusCode,
            );
        }

        try {
            $response = Http::accept('image/*')
                ->timeout((int) config('barcode.cover_timeout', 8))
                ->get($data['cover_url']);

            if (! $response->successful()) {
                return new BarcodeLookupResult(
                    $result->status,
                    $result->message,
                    $data,
                    $result->source ?? class_basename($providerClass),
                    $result->statusCode,
                );
            }

            $contentType = (string) $response->header('Content-Type', '');
            $extension = $this->extensionForContentType($contentType, $data['cover_url']);
            $path = sprintf(
                'covers/lookup-%s-%s.%s',
                Str::slug($barcode),
                Str::lower(Str::random(8)),
                $extension,
            );

            Storage::disk('public')->put($path, $response->body());
            $data['cover_path'] = $path;
            $data['cover_url'] = Storage::disk('public')->url($path);
        } catch (Throwable) {
            // Ignore remote cover import failures. Metadata lookup still succeeds.
            unset($data['cover_url']);
        }

        return new BarcodeLookupResult(
            $result->status,
            $result->message,
            $data,
            $result->source ?? class_basename($providerClass),
            $result->statusCode,
        );
    }

    private function extensionForContentType(string $contentType, string $url): string
    {
        $normalized = strtolower(trim(strtok($contentType, ';') ?: ''));

        return match ($normalized) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => $this->extensionFromUrl($url),
        };
    }

    private function extensionFromUrl(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? $extension : 'jpg';
    }
}
