<?php

namespace App\Services\Metadata\Providers;

use App\Services\Metadata\BarcodeLookupResult;
use App\Services\Metadata\Contracts\BarcodeLookupProvider;

class NullBarcodeLookupProvider implements BarcodeLookupProvider
{
    public function lookup(string $barcode, ?string $typeHint = null): BarcodeLookupResult
    {
        return BarcodeLookupResult::noSource();
    }
}
