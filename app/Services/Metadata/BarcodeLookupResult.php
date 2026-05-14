<?php

namespace App\Services\Metadata;

class BarcodeLookupResult
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly array $data = [],
        public readonly ?string $source = null,
        public readonly int $statusCode = 200,
    ) {
    }

    public static function noSource(): self
    {
        return new self(
            'no_source',
            __('admin.collection.metadata.barcode_source_unavailable'),
            [],
            null,
            200,
        );
    }

    public static function notFound(string $barcode, ?string $source = null): self
    {
        return new self(
            'not_found',
            __('admin.collection.lookup.no_result_found'),
            ['barcode' => $barcode],
            $source,
            200,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function found(array $data, ?string $source = null): self
    {
        return new self(
            'found',
            __('admin.collection.lookup.information_found'),
            $data,
            $source,
            200,
        );
    }

    public static function invalid(string $message): self
    {
        return new self('invalid', $message, [], null, 422);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'message' => $this->message,
            'source' => $this->source,
            'data' => $this->data,
        ];
    }
}
