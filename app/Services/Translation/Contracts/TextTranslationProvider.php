<?php

namespace App\Services\Translation\Contracts;

use App\Services\Translation\TextTranslationResult;

interface TextTranslationProvider
{
    public function configured(): bool;

    public function translate(string $text, string $targetLocale, ?string $sourceLocale = null): TextTranslationResult;
}
