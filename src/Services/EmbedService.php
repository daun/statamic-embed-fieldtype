<?php

namespace Daun\StatamicEmbed\Services;

use Daun\StatamicEmbed\Http\Resources\EmbedResource;
use Embed\Embed as EmbedLibrary;
use Embed\Extractor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EmbedService
{
    public function __construct(
        protected EmbedLibrary $embed
    ) {}

    public function info(?string $url, bool $load = true): ?array
    {
        if (! $url || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $key = "daun-statamic-embed-data-".md5($url);

        if (Cache::has($key)) {
            $data = Cache::get($key);
        } else if ($load) {
            $data = $this->data($url);
            Cache::set($key, $data, now()->addDays(7));
        } else {
            $data = null;
        }

        return $data;
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
}
