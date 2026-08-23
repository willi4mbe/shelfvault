<?php

namespace App\Services\Updates;

final readonly class ReleaseCheckResult
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_CURRENT = 'current';

    public const STATUS_UNAVAILABLE = 'unavailable';

    public function __construct(
        public string $status,
        public string $currentVersion,
        public ?ReleaseInfo $release = null,
        public ?string $checkedAt = null,
    ) {}

    public static function unavailable(string $currentVersion): self
    {
        return new self(self::STATUS_UNAVAILABLE, $currentVersion, null, now()->toIso8601String());
    }

    public static function current(string $currentVersion, ?ReleaseInfo $release): self
    {
        return new self(self::STATUS_CURRENT, $currentVersion, $release, now()->toIso8601String());
    }

    public static function available(string $currentVersion, ReleaseInfo $release): self
    {
        return new self(self::STATUS_AVAILABLE, $currentVersion, $release, now()->toIso8601String());
    }

    public function updateAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE && $this->release !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'current_version' => $this->currentVersion,
            'release' => $this->release?->toArray(),
            'checked_at' => $this->checkedAt,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public static function fromArray(?array $payload): ?self
    {
        if ($payload === null) {
            return null;
        }

        $status = $payload['status'] ?? null;
        $currentVersion = $payload['current_version'] ?? null;

        if (! in_array($status, [self::STATUS_AVAILABLE, self::STATUS_CURRENT, self::STATUS_UNAVAILABLE], true)
            || ! is_string($currentVersion)
        ) {
            return null;
        }

        return new self(
            status: $status,
            currentVersion: $currentVersion,
            release: ReleaseInfo::fromArray(is_array($payload['release'] ?? null) ? $payload['release'] : null),
            checkedAt: is_string($payload['checked_at'] ?? null) ? $payload['checked_at'] : null,
        );
    }
}
