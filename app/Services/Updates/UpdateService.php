<?php

namespace App\Services\Updates;

final class UpdateService
{
    public function __construct(private readonly ReleaseChecker $releaseChecker) {}

    public function check(): ReleaseCheckResult
    {
        return $this->releaseChecker->check();
    }

    public function prepare(): UpdatePreparation
    {
        $check = $this->releaseChecker->check();

        return new UpdatePreparation($check->updateAvailable(), $check);
    }

    /**
     * @param  array<string, mixed>|null  $storedCheck
     * @return array<string, mixed>
     */
    public function summary(?array $storedCheck = null): array
    {
        $currentVersion = $this->currentVersion();
        $check = ReleaseCheckResult::fromArray($storedCheck);

        if ($check?->currentVersion !== $currentVersion) {
            $check = null;
        }

        $release = $check?->release;
        $installationMode = $this->installationMode();

        return [
            'current_version' => $currentVersion,
            'status' => $check?->status ?? 'unknown',
            'latest_version' => $release?->tagName,
            'release_name' => $release?->name,
            'release_url' => $release?->htmlUrl,
            'changelog' => $release?->body,
            'checked_at' => $check?->checkedAt,
            'installation_mode' => $installationMode,
            'strategy_label' => __('admin.settings.updates.installation_modes.'.$installationMode),
            'backup_required' => (bool) config('shelfvault.updates.backup_required', true),
            'auto_update_enabled' => false,
            'can_prepare' => $check?->updateAvailable() ?? false,
        ];
    }

    private function currentVersion(): string
    {
        return trim((string) config('shelfvault.version', '0.1.0-dev')) ?: '0.1.0-dev';
    }

    private function installationMode(): string
    {
        $configured = strtolower(trim((string) config('shelfvault.updates.installation_mode', 'auto')));

        if (in_array($configured, ['docker', 'classic'], true)) {
            return $configured;
        }

        return file_exists('/.dockerenv') ? 'docker' : 'classic';
    }
}
