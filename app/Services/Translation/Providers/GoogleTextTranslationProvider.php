<?php

namespace App\Services\Translation\Providers;

use App\Services\ExternalServices\ExternalServiceSettings;
use App\Services\Translation\Contracts\TextTranslationProvider;
use App\Services\Translation\TextTranslationResult;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Throwable;

class GoogleTextTranslationProvider implements TextTranslationProvider
{
    public function __construct(
        private readonly ?ExternalServiceSettings $settings = null,
    ) {
    }

    public function configured(): bool
    {
        return $this->apiKey() !== '' && $this->baseUrl() !== '';
    }

    public function translate(string $text, string $targetLocale, ?string $sourceLocale = null): TextTranslationResult
    {
        $targetLocale = $this->primaryLocale($targetLocale);

        if (trim($text) === '' || $targetLocale === '' || ! $this->configured()) {
            return TextTranslationResult::original($text);
        }

        try {
            $response = Http::timeout(8)
                ->asForm()
                ->withQueryParameters(['key' => $this->apiKey()])
                ->post($this->baseUrl(), [
                    'q' => $text,
                    'target' => $targetLocale,
                    'format' => 'text',
                ]);

            if (! $response->successful()) {
                return TextTranslationResult::original($text);
            }

            $translation = Arr::get($response->json(), 'data.translations.0');

            if (! is_array($translation)) {
                return TextTranslationResult::original($text);
            }

            $detectedSourceLanguage = $this->primaryLocale(Arr::get($translation, 'detectedSourceLanguage'));

            if ($detectedSourceLanguage !== '' && $detectedSourceLanguage === $targetLocale) {
                return TextTranslationResult::original($text);
            }

            $translatedText = Arr::get($translation, 'translatedText');

            if (! is_string($translatedText) || trim($translatedText) === '') {
                return TextTranslationResult::original($text);
            }

            $decodedText = html_entity_decode($translatedText, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return TextTranslationResult::translated($decodedText);
        } catch (Throwable) {
            return TextTranslationResult::original($text);
        }
    }

    private function apiKey(): string
    {
        return trim((string) $this->settings()->getSecret(
            'google_translation',
            'api_key',
            config('services.translation.google.api_key', ''),
        ));
    }

    private function baseUrl(): string
    {
        return trim((string) config('services.translation.google.base_url', ''));
    }

    private function primaryLocale(mixed $locale): string
    {
        if (! is_string($locale)) {
            return '';
        }

        return strtolower(strtok(str_replace('_', '-', trim($locale)), '-') ?: '');
    }

    private function settings(): ExternalServiceSettings
    {
        return $this->settings ?? app(ExternalServiceSettings::class);
    }
}
