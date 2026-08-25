<?php

namespace Tests\Unit;

use App\Services\Translation\Providers\GoogleTextTranslationProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleTextTranslationProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.translation.google.api_key' => 'google-test-key',
            'services.translation.google.base_url' => 'https://translation.googleapis.com/language/translate/v2',
        ]);
    }

    public function test_google_provider_translates_an_english_description_to_french(): void
    {
        Http::fake([
            'https://translation.googleapis.com/language/translate/v2*' => Http::response([
                'data' => [
                    'translations' => [
                        [
                            'translatedText' => 'Mario explore l&#39;espace.',
                            'detectedSourceLanguage' => 'en',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = (new GoogleTextTranslationProvider())->translate('Mario explores space.', 'fr', 'en');

        $this->assertTrue($result->translated);
        $this->assertSame("Mario explore l'espace.", $result->text);

        Http::assertSent(function ($request): bool {
            return str_starts_with($request->url(), 'https://translation.googleapis.com/language/translate/v2?key=google-test-key')
                && str_contains($request->body(), 'q=Mario+explores+space.')
                && str_contains($request->body(), 'target=fr')
                && ! str_contains($request->body(), 'source=');
        });
    }

    public function test_google_provider_returns_original_when_detected_source_matches_target(): void
    {
        Http::fake([
            'https://translation.googleapis.com/language/translate/v2*' => Http::response([
                'data' => [
                    'translations' => [
                        [
                            'translatedText' => 'Mario explores space.',
                            'detectedSourceLanguage' => 'en',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = (new GoogleTextTranslationProvider())->translate('Mario explores space.', 'en', null);

        $this->assertFalse($result->translated);
        $this->assertSame('Mario explores space.', $result->text);
    }

    public function test_google_provider_returns_original_when_api_returns_an_error(): void
    {
        Http::fake([
            'https://translation.googleapis.com/language/translate/v2*' => Http::response([
                'error' => [
                    'message' => 'Invalid API key',
                ],
            ], 403),
        ]);

        $result = (new GoogleTextTranslationProvider())->translate('Mario explores space.', 'fr', null);

        $this->assertFalse($result->translated);
        $this->assertSame('Mario explores space.', $result->text);
    }
}
