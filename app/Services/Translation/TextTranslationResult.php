<?php

namespace App\Services\Translation;

class TextTranslationResult
{
    public function __construct(
        public readonly string $text,
        public readonly bool $translated,
    ) {
    }

    public static function translated(string $text): self
    {
        return new self($text, true);
    }

    public static function original(string $text): self
    {
        return new self($text, false);
    }
}
