<?php

namespace App\Providers;

use App\Services\ExternalServices\ExternalServiceSettings;
use App\Services\Translation\Contracts\TextTranslationProvider;
use App\Services\Translation\Providers\GoogleTextTranslationProvider;
use App\Services\Translation\Providers\NullTextTranslationProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TextTranslationProvider::class, function ($app): TextTranslationProvider {
            $settings = $app->make(ExternalServiceSettings::class);
            $provider = trim((string) $settings->get('google_translation', 'provider', config('services.translation.provider', '')));

            if (strtolower($provider) === 'google') {
                $apiKey = trim((string) $settings->getSecret('google_translation', 'api_key', config('services.translation.google.api_key', '')));

                return $apiKey !== ''
                    ? $app->make(GoogleTextTranslationProvider::class)
                    : new NullTextTranslationProvider();
            }

            if ($provider !== ''
                && class_exists($provider)
                && is_a($provider, TextTranslationProvider::class, true)
            ) {
                return $app->make($provider);
            }

            return new NullTextTranslationProvider();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
