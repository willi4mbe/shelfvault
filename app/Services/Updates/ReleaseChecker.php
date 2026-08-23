<?php

namespace App\Services\Updates;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ReleaseChecker
{
    public function check(): ReleaseCheckResult
    {
        $currentVersion = $this->currentVersion();
        $repository = $this->repository();
        $apiBaseUrl = $this->apiBaseUrl();

        if ($repository === null || $apiBaseUrl === null) {
            Log::warning('ShelfVault update check skipped because release configuration is invalid.');

            return ReleaseCheckResult::unavailable($currentVersion);
        }

        try {
            $response = Http::baseUrl($apiBaseUrl)
                ->acceptJson()
                ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
                ->timeout(max(1, (int) config('shelfvault.updates.timeout', 5)))
                ->get('/repos/'.$repository.'/releases');
        } catch (Throwable $exception) {
            Log::warning('ShelfVault update check failed.', [
                'exception' => $exception::class,
            ]);

            return ReleaseCheckResult::unavailable($currentVersion);
        }

        if (! $response->successful()) {
            Log::warning('ShelfVault update check returned an HTTP error.', [
                'status' => $response->status(),
            ]);

            return ReleaseCheckResult::unavailable($currentVersion);
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            Log::warning('ShelfVault update check returned invalid JSON.');

            return ReleaseCheckResult::unavailable($currentVersion);
        }

        $release = collect($payload)
            ->filter(fn (mixed $entry): bool => is_array($entry)
                && ($entry['draft'] ?? true) === false
                && ($entry['prerelease'] ?? true) === false)
            ->map(fn (array $entry): ?ReleaseInfo => ReleaseInfo::fromGithubPayload($entry))
            ->filter()
            ->sortByDesc(fn (ReleaseInfo $release): string => $release->publishedAt ?? '')
            ->first();

        if (! $release instanceof ReleaseInfo) {
            Log::info('ShelfVault update check found no stable GitHub release.');

            return ReleaseCheckResult::unavailable($currentVersion);
        }

        return version_compare($release->version, $this->normalizedCurrentVersion($currentVersion), '>')
            ? ReleaseCheckResult::available($currentVersion, $release)
            : ReleaseCheckResult::current($currentVersion, $release);
    }

    private function currentVersion(): string
    {
        return trim((string) config('shelfvault.version', '0.1.0-dev')) ?: '0.1.0-dev';
    }

    private function normalizedCurrentVersion(string $version): string
    {
        return preg_replace('/^v/i', '', trim($version)) ?: $version;
    }

    private function repository(): ?string
    {
        $repository = trim((string) config('shelfvault.updates.repository'));

        return preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $repository) === 1
            ? $repository
            : null;
    }

    private function apiBaseUrl(): ?string
    {
        $url = rtrim(trim((string) config('shelfvault.updates.api_base_url')), '/');

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($url);

        return ($parts['scheme'] ?? null) === 'https' ? $url : null;
    }
}
