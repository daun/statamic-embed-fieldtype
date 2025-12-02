<?php

namespace Daun\StatamicEmbed\Services;

use Daun\StatamicEmbed\Http\Resources\EmbedResource;
use Embed\Embed;
use Embed\Extractor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EmbedService
{
    public function __construct(
        public readonly Embed $embed,
        protected int $ttl = 604800, // 7 days
    ) {}

    public function info(?string $url, bool $fetch = true, bool $refresh = false): ?array
    {
        if (! $this->isUrl($url)) {
            return null;
        }

        $key = "statamic-embed-data-".md5($url);

        if (! $refresh && Cache::has($key)) {
            return Cache::get($key);
        }

        if ($fetch) {
            return Cache::remember($key, $this->ttl, fn () => $this->data($url));
        }

        return null;
    }

    protected function data(string $url): array
    {
        if ($extractor = $this->extract($url)) {
            return (new EmbedResource($extractor))->resolve();
        } else {
            return [];
        }
    }

    protected function extract(string $url): ?Extractor
    {
        try {
            return $this->embed->get($url);
        } catch (\Throwable $th) {
            Log::error("Embed extraction failed for URL {$url}: {$th->getMessage()}", ['exception' => $th]);

            return null;
        }
    }

    protected function isUrl(mixed $url): bool
    {
        return $url
            && is_string($url)
            && filter_var($url, FILTER_VALIDATE_URL);
    }
}
