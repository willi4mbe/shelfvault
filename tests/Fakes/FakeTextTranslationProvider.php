<?php

namespace Tests\Fakes;

use App\Services\Translation\Contracts\TextTranslationProvider;
use App\Services\Translation\TextTranslationResult;

class FakeTextTranslationProvider implements TextTranslationProvider
{
    public function configured(): bool
    {
        return true;
    }

    public function translate(string $text, string $targetLocale, ?string $sourceLocale = null): TextTranslationResult
    {
        return TextTranslationResult::translated('['.$targetLocale.'] '.$text);
    }
}
