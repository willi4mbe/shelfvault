<?php

namespace App\Services\ExternalServices;

use App\Models\ExternalServiceSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class ExternalServiceSettings
{
    public function get(string $service, string $key, mixed $fallback = null): ?string
    {
        return $this->resolve($service, $key, $fallback, false);
    }

    public function getSecret(string $service, string $key, mixed $fallback = null): ?string
    {
        return $this->resolve($service, $key, $fallback, true);
    }

    public function set(string $service, string $key, ?string $value, bool $isSecret): ExternalServiceSetting
    {
        $setting = ExternalServiceSetting::query()->firstOrNew([
            'service' => $service,
            'key' => $key,
        ]);

        $setting->is_secret = $isSecret;

        if ($isSecret) {
            $setting->value = null;
            $setting->encrypted_value = $this->filled($value) ? Crypt::encryptString(trim((string) $value)) : null;
        } else {
            $setting->value = $this->filled($value) ? trim((string) $value) : null;
            $setting->encrypted_value = null;
        }

        $setting->save();

        return $setting;
    }

    public function delete(string $service, string $key): void
    {
        ExternalServiceSetting::query()
            ->where('service', $service)
            ->where('key', $key)
            ->delete();
    }

    /**
     * @param  array<int, string>  $keys
     */
    public function isConfigured(string $service, array $keys, bool $secret = true): bool
    {
        foreach ($keys as $key) {
            $value = $secret ? $this->getSecret($service, $key) : $this->get($service, $key);

            if (! $this->filled($value)) {
                return false;
            }
        }

        return true;
    }

    public function maskedSecret(string $service, string $key, mixed $fallback = null): ?string
    {
        return $this->filled($this->getSecret($service, $key, $fallback)) ? '**********' : null;
    }

    public function hasStoredValue(string $service, string $key): bool
    {
        try {
            return ExternalServiceSetting::query()
                ->where('service', $service)
                ->where('key', $key)
                ->where(function ($query): void {
                    $query->whereNotNull('value')
                        ->orWhereNotNull('encrypted_value');
                })
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function resolve(string $service, string $key, mixed $fallback, bool $secret): ?string
    {
        try {
            $setting = ExternalServiceSetting::query()
                ->where('service', $service)
                ->where('key', $key)
                ->first();
        } catch (QueryException) {
            return $this->normalize($fallback);
        } catch (Throwable) {
            return $this->normalize($fallback);
        }

        if ($setting === null) {
            return $this->normalize($fallback);
        }

        try {
            $value = $setting->resolvedValue();
        } catch (DecryptException) {
            return $this->normalize($fallback);
        }

        return $this->filled($value) ? trim((string) $value) : $this->normalize($fallback);
    }

    private function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function filled(mixed $value): bool
    {
        return trim((string) $value) !== '';
    }
}
