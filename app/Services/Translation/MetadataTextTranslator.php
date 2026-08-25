<?php

namespace App\Services\Translation;

use App\Services\Translation\Contracts\TextTranslationProvider;
use App\Services\Translation\Providers\NullTextTranslationProvider;
use Throwable;

class MetadataTextTranslator
{
    /**
     * @var array<string, TextTranslationResult>
     */
    private array $cache = [];

    public function __construct(
        private readonly ?TextTranslationProvider $translationProvider = null,
    ) {
    }

    public function configured(): bool
    {
        return $this->translator()->configured();
    }

    public function shouldTranslate(?string $targetLocale): bool
    {
        $targetLocale = $this->primaryLocale($targetLocale ?? app()->getLocale());

        return $targetLocale !== '' && $targetLocale !== 'en';
    }

    public function translate(string $text, ?string $targetLocale = null, ?string $sourceLocale = null): TextTranslationResult
    {
        $targetLocale = $this->primaryLocale($targetLocale ?? app()->getLocale());
        $sourceLocale = $sourceLocale !== null ? $this->primaryLocale($sourceLocale) : null;
        $text = trim($text);

        if ($text === '' || ! $this->shouldTranslate($targetLocale) || ! $this->configured()) {
            return TextTranslationResult::original($text);
        }

        $cacheKey = sha1(implode("\0", [$targetLocale, $sourceLocale ?? '', $text]));

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        try {
            $result = $this->translator()->translate($text, $targetLocale, $sourceLocale);
        } catch (Throwable) {
            $result = TextTranslationResult::original($text);
        }

        if (trim($result->text) === '') {
            $result = TextTranslationResult::original($text);
        }

        return $this->cache[$cacheKey] = $result;
    }

    private function translator(): TextTranslationProvider
    {
        return $this->translationProvider ?? app(TextTranslationProvider::class) ?? new NullTextTranslationProvider();
    }

    private function primaryLocale(mixed $locale): string
    {
        if (! is_string($locale)) {
            return '';
        }

        return strtolower(strtok(str_replace('_', '-', trim($locale)), '-') ?: '');
    }
}
