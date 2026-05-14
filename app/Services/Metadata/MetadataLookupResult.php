<?php

namespace App\Services\Metadata;

class MetadataLookupResult
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly array $data = [],
        public readonly ?string $source = null,
        public readonly int $statusCode = 200,
        public readonly array $warnings = [],
    ) {
    }

    public static function noSource(string $message): self
    {
        return new self('no_source', $message, [], null, 200);
    }

    public static function notFound(string $message, array $data = [], ?string $source = null): self
    {
        return new self('not_found', $message, $data, $source, 200);
    }

    public static function found(array $data, string $message, ?string $source = null, array $warnings = []): self
    {
        return new self('found', $message, $data, $source, 200, $warnings);
    }

    public static function invalid(string $message): self
    {
        return new self('invalid', $message, [], null, 422);
    }

    public static function error(string $message, int $statusCode = 500): self
    {
        return new self('error', $message, [], null, $statusCode);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'status' => $this->status,
            'message' => $this->message,
            'source' => $this->source,
            'data' => $this->data,
        ];

        if ($this->warnings !== []) {
            $payload['warnings'] = $this->warnings;
        }

        return $payload;
    }
}
