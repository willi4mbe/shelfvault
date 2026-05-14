<?php

use App\Services\Metadata\Providers\NullBarcodeLookupProvider;

return [
    'providers' => array_values(array_filter(array_map(
        static fn (string $provider): string => trim($provider),
        explode(',', (string) env('SHELFVAULT_BARCODE_PROVIDERS', NullBarcodeLookupProvider::class))
    ))),
];
