<?php

namespace App\Services\Library;

use App\Enums\ItemType;
use App\Services\ExternalServices\ExternalServiceSettings;

class LibrarySettings
{
    public const SERVICE = 'library';

    public const NAME_KEY = 'name';

    public const LOANS_ENABLED_KEY = 'loans_enabled';

    public const ACCENT_COLOR_KEY = 'accent_color';

    public const LOCATIONS_ENABLED_KEY = 'locations_enabled';

    public const LOCATIONS_KEY = 'locations';

    public const VISIBILITY_KEY = 'visibility';

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_PRIVATE = 'private';

    public const DEFAULT_ACCENT_COLOR = 'orange';

    /**
     * @var array<string, array{rgb: string, contrast_rgb: string}>
     */
    private const ACCENT_COLORS = [
        'orange' => ['rgb' => '249 115 22', 'contrast_rgb' => '23 13 2'],
        'yellow' => ['rgb' => '245 197 24', 'contrast_rgb' => '27 19 0'],
        'green' => ['rgb' => '34 197 94', 'contrast_rgb' => '2 20 10'],
        'red' => ['rgb' => '239 68 68', 'contrast_rgb' => '27 5 5'],
    ];

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

    public function accentColor(): string
    {
        $accent = $this->settings->get(self::SERVICE, self::ACCENT_COLOR_KEY, self::DEFAULT_ACCENT_COLOR);

        return array_key_exists((string) $accent, self::ACCENT_COLORS)
            ? (string) $accent
            : self::DEFAULT_ACCENT_COLOR;
    }

    public function setAccentColor(string $accentColor): void
    {
        $this->settings->set(
            self::SERVICE,
            self::ACCENT_COLOR_KEY,
            array_key_exists($accentColor, self::ACCENT_COLORS) ? $accentColor : self::DEFAULT_ACCENT_COLOR,
            false,
        );
    }

    public function locationsEnabled(): bool
    {
        return $this->booleanValue($this->settings->get(self::SERVICE, self::LOCATIONS_ENABLED_KEY, '1'), true);
    }

    public function setLocationsEnabled(bool $enabled): void
    {
        $this->settings->set(self::SERVICE, self::LOCATIONS_ENABLED_KEY, $enabled ? '1' : '0', false);
    }

    /**
     * @return array<int, string>
     */
    public function locations(): array
    {
        $locations = preg_split('/\R/u', (string) $this->settings->get(self::SERVICE, self::LOCATIONS_KEY, '')) ?: [];

        return $this->normalizeLocations($locations);
    }

    /**
     * @param  array<int, string>  $locations
     */
    public function setLocations(array $locations): void
    {
        $this->settings->set(self::SERVICE, self::LOCATIONS_KEY, implode("\n", $this->normalizeLocations($locations)), false);
    }

    public function locationsText(): string
    {
        return implode("\n", $this->locations());
    }

    public function visibility(): string
    {
        $visibility = $this->settings->get(self::SERVICE, self::VISIBILITY_KEY, self::VISIBILITY_PUBLIC);

        return in_array($visibility, [self::VISIBILITY_PUBLIC, self::VISIBILITY_PRIVATE], true)
            ? (string) $visibility
            : self::VISIBILITY_PUBLIC;
    }

    public function setVisibility(string $visibility): void
    {
        $this->settings->set(
            self::SERVICE,
            self::VISIBILITY_KEY,
            in_array($visibility, [self::VISIBILITY_PUBLIC, self::VISIBILITY_PRIVATE], true)
                ? $visibility
                : self::VISIBILITY_PUBLIC,
            false,
        );
    }

    public function isPrivate(): bool
    {
        return $this->visibility() === self::VISIBILITY_PRIVATE;
    }

    /**
     * @return array<string, array{rgb: string, contrast_rgb: string}>
     */
    public function accentColorOptions(): array
    {
        return self::ACCENT_COLORS;
    }

    /**
     * @return array{key: string, rgb: string, contrastRgb: string}
     */
    public function accentTheme(): array
    {
        $key = $this->accentColor();

        return [
            'key' => $key,
            'rgb' => self::ACCENT_COLORS[$key]['rgb'],
            'contrastRgb' => self::ACCENT_COLORS[$key]['contrast_rgb'],
        ];
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

    /**
     * @param  array<int, string>  $locations
     * @return array<int, string>
     */
    private function normalizeLocations(array $locations): array
    {
        return collect($locations)
            ->map(fn (string $location): string => trim($location))
            ->filter(fn (string $location): bool => $location !== '')
            ->unique(fn (string $location): string => mb_strtolower($location))
            ->values()
            ->all();
    }
}
