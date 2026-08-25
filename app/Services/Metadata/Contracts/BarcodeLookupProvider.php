<?php

namespace App\Services\Metadata\Contracts;

use App\Services\Metadata\BarcodeLookupResult;

interface BarcodeLookupProvider
{
    public function lookup(string $barcode, ?string $typeHint = null): BarcodeLookupResult;
}
