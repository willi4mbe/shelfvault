<?php

namespace App\Services\Translation\Providers;

use App\Services\Translation\Contracts\TextTranslationProvider;
use App\Services\Translation\TextTranslationResult;

class NullTextTranslationProvider implements TextTranslationProvider
{
    public function configured(): bool
    {
        return false;
    }

    public function translate(string $text, string $targetLocale, ?string $sourceLocale = null): TextTranslationResult
    {
        return TextTranslationResult::original($text);
    }
}
