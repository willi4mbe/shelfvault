<?php

namespace App\Services\Library;

use App\Enums\ItemType;
use App\Services\ExternalServices\ExternalServiceSettings;

class LibrarySettings
{
    public const SERVICE = 'library';

    public const NAME_KEY = 'name';

    public const LOANS_ENABLED_KEY = 'loans_enabled';

    /**
     * @var array<string, string>
     */
    private const TYPE_KEYS = [
        ItemType::Film->value => 'type_film_enabled',
        ItemType::TvSeries->value => 'type_tv_series_enabled',
        ItemType::VideoGame->value => 'type_video_game_enabled',
        ItemType::BoardGame->value => 'type_board_game_enabled',
    ];

    public function __construct(private readonly ExternalServiceSettings $settings)
    {
    }

    public function libraryName(): string
    {
        return $this->settings->get(self::SERVICE, self::NAME_KEY, config('app.name', 'ShelfVault')) ?: 'ShelfVault';
    }

    public function setLibraryName(string $name): void
    {
        $this->settings->set(self::SERVICE, self::NAME_KEY, $name, false);
    }

    public function loansEnabled(): bool
    {
        return $this->booleanValue($this->settings->get(self::SERVICE, self::LOANS_ENABLED_KEY, '1'), true);
    }

    public function setLoansEnabled(bool $enabled): void
    {
        $this->settings->set(self::SERVICE, self::LOANS_ENABLED_KEY, $enabled ? '1' : '0', false);
    }

    /**
     * @return array<string, bool>
     */
    public function enabledTypes(): array
    {
        $types = [];

        foreach (self::TYPE_KEYS as $type => $key) {
            $types[$type] = $this->booleanValue($this->settings->get(self::SERVICE, $key, '1'), true);
        }

        return $types;
    }

    /**
     * @param  array<int, string>  $types
     */
    public function setEnabledTypes(array $types): void
    {
        $enabledTypes = array_fill_keys($types, true);

        foreach (self::TYPE_KEYS as $type => $key) {
            $this->settings->set(self::SERVICE, $key, isset($enabledTypes[$type]) ? '1' : '0', false);
        }
    }

    public function isTypeEnabled(ItemType|string $type): bool
    {
        $value = $type instanceof ItemType ? $type->value : $type;

        return $this->enabledTypes()[$value] ?? false;
    }

    /**
     * @return array<int, string>
     */
    public function enabledTypeValues(): array
    {
        return array_keys(array_filter($this->enabledTypes()));
    }

    /**
     * @return array<int, string>
     */
    public function allTypeValues(): array
    {
        return array_keys(self::TYPE_KEYS);
    }

    private function booleanValue(?string $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
