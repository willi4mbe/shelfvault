<?php

namespace App\Services\Updates;

final readonly class UpdatePreparation
{
    public function __construct(
        public bool $ready,
        public ReleaseCheckResult $check,
    ) {}
}
