<?php

namespace App\Services\Updates;

final readonly class ReleaseInfo
{
    public function __construct(
        public string $tagName,
        public string $version,
        public string $name,
        public string $htmlUrl,
        public string $body,
        public ?string $publishedAt,
        public string $zipUrl = '',
        public string $sha256 = '',
        public ?string $minimumPhp = null,
        public bool $requiresMigrations = true,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromGithubPayload(array $payload): ?self
    {
        $tagName = self::cleanString($payload['tag_name'] ?? null, 80);
        $version = self::versionFromTag($tagName);
        $htmlUrl = self::cleanString($payload['html_url'] ?? null, 400);

        if ($tagName === '' || $version === null || ! self::isSafeGithubUrl($htmlUrl)) {
            return null;
        }

        $name = self::cleanString($payload['name'] ?? null, 140);
        $body = self::cleanString($payload['body'] ?? null, 12000);
        $publishedAt = self::cleanString($payload['published_at'] ?? null, 60);

        return new self(
            tagName: $tagName,
            version: $version,
            name: $name !== '' ? $name : $tagName,
            htmlUrl: $htmlUrl,
            body: $body,
            publishedAt: $publishedAt !== '' ? $publishedAt : null,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromManifestPayload(array $payload): ?self
    {
        $version = self::versionFromTag(self::cleanString($payload['version'] ?? null, 80));
        $tagName = self::cleanString($payload['tag_name'] ?? null, 80);
        $zipUrl = self::cleanString($payload['zip_url'] ?? null, 600);
        $sha256 = strtolower(self::cleanString($payload['sha256'] ?? null, 128));

        if ($version === null || ! self::isHttpsUrl($zipUrl) || preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1) {
            return null;
        }

        if ($tagName === '') {
            $tagName = 'v'.$version;
        }

        $htmlUrl = self::cleanString($payload['html_url'] ?? null, 600);

        if ($htmlUrl === '' || ! self::isHttpsUrl($htmlUrl)) {
            $htmlUrl = $zipUrl;
        }

        $minimumPhp = self::cleanString($payload['minimum_php'] ?? null, 40);

        return new self(
            tagName: $tagName,
            version: $version,
            name: self::cleanString($payload['name'] ?? null, 140) ?: $tagName,
            htmlUrl: $htmlUrl,
            body: self::cleanString($payload['notes'] ?? $payload['body'] ?? null, 12000),
            publishedAt: self::cleanString($payload['published_at'] ?? null, 60) ?: null,
            zipUrl: $zipUrl,
            sha256: $sha256,
            minimumPhp: $minimumPhp !== '' ? $minimumPhp : null,
            requiresMigrations: filter_var($payload['requires_migrations'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'tag_name' => $this->tagName,
            'version' => $this->version,
            'name' => $this->name,
            'html_url' => $this->htmlUrl,
            'body' => $this->body,
            'published_at' => $this->publishedAt,
            'zip_url' => $this->zipUrl,
            'sha256' => $this->sha256,
            'minimum_php' => $this->minimumPhp,
            'requires_migrations' => $this->requiresMigrations,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public static function fromArray(?array $payload): ?self
    {
        if ($payload === null) {
            return null;
        }

        $tagName = self::cleanString($payload['tag_name'] ?? null, 80);
        $version = self::cleanString($payload['version'] ?? null, 80);
        $htmlUrl = self::cleanString($payload['html_url'] ?? null, 400);

        $zipUrl = self::cleanString($payload['zip_url'] ?? null, 600);
        $sha256 = strtolower(self::cleanString($payload['sha256'] ?? null, 128));

        if ($tagName === '' || $version === '' || ! self::isHttpsUrl($htmlUrl)) {
            return null;
        }

        return new self(
            tagName: $tagName,
            version: $version,
            name: self::cleanString($payload['name'] ?? null, 140) ?: $tagName,
            htmlUrl: $htmlUrl,
            body: self::cleanString($payload['body'] ?? null, 12000),
            publishedAt: self::cleanString($payload['published_at'] ?? null, 60) ?: null,
            zipUrl: self::isHttpsUrl($zipUrl) ? $zipUrl : '',
            sha256: preg_match('/^[a-f0-9]{64}$/', $sha256) === 1 ? $sha256 : '',
            minimumPhp: self::cleanString($payload['minimum_php'] ?? null, 40) ?: null,
            requiresMigrations: filter_var($payload['requires_migrations'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
        );
    }

    private static function versionFromTag(string $tagName): ?string
    {
        if (preg_match('/^v?(\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?)$/', trim($tagName), $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private static function isSafeGithubUrl(string $url): bool
    {
        if (! self::isHttpsUrl($url)) {
            return false;
        }

        $parts = parse_url($url);

        return ($parts['scheme'] ?? null) === 'https'
            && in_array(strtolower((string) ($parts['host'] ?? '')), ['github.com', 'www.github.com'], true);
    }

    private static function isHttpsUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        return ($parts['scheme'] ?? null) === 'https';
    }

    private static function cleanString(mixed $value, int $maxLength): string
    {
        if (! is_string($value)) {
            return '';
        }

        return mb_substr(trim($value), 0, $maxLength);
    }
}
