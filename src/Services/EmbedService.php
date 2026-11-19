<?php

namespace Daun\StatamicEmbed\Services;

use Daun\StatamicEmbed\Http\Resources\EmbedResource;
use Embed\Embed;
use Embed\Extractor;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Log;

use function Illuminate\Support\defer;

class EmbedService
{
    /**
     * Bump when the shape of the cached data changes to invalidate existing entries.
     */
    public const CACHE_VERSION = 1;

    /**
     * Seconds until a URL that has never been fetched successfully is retried.
     */
    public const FAILURE_TTL = 3600;

    public function __construct(
        public readonly Embed $embed,
        protected Repository $cache,
        protected ?int $ttl = 2592000,
    ) {}

    public function info(?string $url, bool $fetch = true, bool $refresh = false): ?array
    {
        if (! $this->isUrl($url)) {
            return null;
        }

        $cached = $this->cached($url);

        if ($cached && ! $refresh) {
            if ($fetch && $this->isStale($cached)) {
                defer(fn () => $this->fetch($url), $this->key($url));
            }

            return $cached['data'];
        }

        return $fetch ? $this->fetch($url) : ($cached['data'] ?? null);
    }

    /**
     * Fetch and cache multiple URLs, requesting their pages in parallel.
     *
     * @param  string[]  $urls
     */
    public function warm(array $urls): void
    {
        $urls = array_values(array_filter(array_unique($urls), $this->isUrl(...)));

        if (! $urls) {
            return;
        }

        try {
            $extractors = $this->embed->getMulti(...$urls);
        } catch (\Throwable) {
            // A single failed request aborts the whole batch: retry one by one
            $extractors = array_map($this->extract(...), $urls);
        }

        foreach ($urls as $index => $url) {
            $this->store($url, $this->validate($extractors[$index] ?? null, $url));
        }
    }

    public function isFresh(string $url): bool
    {
        $cached = $this->cached($url);

        return $cached && ! $this->isStale($cached);
    }

    public function forget(string $url): void
    {
        $this->cache->forget($this->key($url));
    }

    public function flush(): void
    {
        $this->cache->clear();
    }

    protected function fetch(string $url): array
    {
        return $this->store($url, $this->extract($url));
    }

    /**
     * Cache the extracted data, keeping previous data around if the fetch failed.
     */
    protected function store(string $url, ?Extractor $extractor): array
    {
        $data = $extractor ? $this->resolve($extractor, $url) : null;
        $previous = $this->cached($url)['data'] ?? null;

        if ($data === null && $previous) {
            Log::warning("Embed refresh failed for URL {$url}, keeping previously cached data");
        }

        $entry = ['data' => $data ?? $previous ?? [], 'fetched_at' => now()->getTimestamp()];
        $ttl = ($data === null && ! $previous) ? self::FAILURE_TTL : null;

        $this->cache->put($this->key($url), $entry, $ttl);

        return $entry['data'];
    }

    protected function resolve(Extractor $extractor, string $url): ?array
    {
        try {
            return (new EmbedResource($extractor))->resolve();
        } catch (\Throwable $th) {
            $this->logFailure($url, $th);

            return null;
        }
    }

    protected function extract(string $url): ?Extractor
    {
        try {
            return $this->validate($this->embed->get($url), $url);
        } catch (\Throwable $th) {
            $this->logFailure($url, $th);

            return null;
        }
    }

    protected function validate(?Extractor $extractor, string $url): ?Extractor
    {
        $status = $extractor?->getResponse()->getStatusCode();

        if ($status >= 400) {
            Log::error("Embed extraction failed for URL {$url}: HTTP status {$status}");

            return null;
        }

        return $extractor;
    }

    protected function cached(string $url): ?array
    {
        $cached = $this->cache->get($this->key($url));

        return is_array($cached) && isset($cached['fetched_at']) ? $cached : null;
    }

    protected function isStale(array $cached): bool
    {
        return $this->ttl !== null && now()->getTimestamp() >= $cached['fetched_at'] + $this->ttl;
    }

    protected function key(string $url): string
    {
        return 'statamic-embed:v'.self::CACHE_VERSION.':'.md5($url);
    }

    protected function logFailure(string $url, \Throwable $th): void
    {
        Log::error("Embed extraction failed for URL {$url}: {$th->getMessage()}", ['exception' => $th]);
    }

    protected function isUrl(mixed $url): bool
    {
        return $url
            && is_string($url)
            && filter_var($url, FILTER_VALIDATE_URL);
    }
}
