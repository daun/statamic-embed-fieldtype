<?php

namespace Daun\StatamicEmbed\Support;

use Daun\StatamicEmbed\Services\EmbedService;
use Daun\StatamicEmbed\Services\EmbedUrlFinder;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Collection;

use function Illuminate\Support\defer;

class EmbedProviders
{
    /**
     * Seconds until the list is rebuilt in the background, in case content changed without events.
     */
    public const TTL = 3600;

    public function __construct(
        protected EmbedService $service,
        protected EmbedUrlFinder $finder,
        protected Repository $cache,
    ) {}

    /**
     * @return Collection<int, array{slug: string, name: ?string, url: ?string, embeds: int}>
     */
    public function all(): Collection
    {
        $cached = $this->cache->get($this->key());

        if (! is_array($cached)) {
            $cached = $this->build(fetch: false);
        } elseif (now()->getTimestamp() >= $cached['built_at'] + self::TTL) {
            $this->rebuildLater();
        }

        return collect($cached['providers']);
    }

    /**
     * @return string[]
     */
    public function slugs(): array
    {
        return $this->all()->pluck('slug')->all();
    }

    public function build(bool $fetch = true): array
    {
        $urls = $this->finder->items(published: true)->flatMap(fn ($item) => $item['urls']);
        $data = $this->lookup($urls);
        $uncached = $data->filter(fn ($info) => $info === null)->keys();

        if ($fetch && $uncached->isNotEmpty()) {
            $this->service->warm($uncached->all());
            $data = $this->lookup($urls);
        }

        $cached = [
            'providers' => $this->group($urls, $data)->all(),
            'built_at' => now()->getTimestamp(),
        ];

        $this->cache->forever($this->key(), $cached);

        if (! $fetch && $uncached->isNotEmpty()) {
            $this->rebuildLater();
        }

        return $cached;
    }

    /**
     * Mark the list as stale to rebuild it after the next request.
     */
    public function invalidate(): void
    {
        if (is_array($cached = $this->cache->get($this->key()))) {
            $this->cache->forever($this->key(), [...$cached, 'built_at' => 0]);
        }
    }

    public function forget(): void
    {
        $this->cache->forget($this->key());
    }

    protected function rebuildLater(): void
    {
        // Let only one of many concurrent requests rebuild the list
        if ($this->cache->add($this->key().':building', true, 60)) {
            defer(function () {
                $this->build();
                $this->cache->forget($this->key().':building');
            }, $this->key());
        }
    }

    protected function lookup(Collection $urls): Collection
    {
        return $urls->unique()->mapWithKeys(fn ($url) => [$url => $this->service->info($url, fetch: false)]);
    }

    protected function group(Collection $urls, Collection $data): Collection
    {
        return $urls
            ->map(fn ($url) => $data[$url]['provider'] ?? null)
            ->filter(fn ($provider) => $provider['slug'] ?? null)
            ->groupBy('slug')
            ->map(fn ($group) => [
                'slug' => $group->first()['slug'],
                'name' => $group->first()['name'] ?? null,
                'url' => $group->first()['url'] ?? null,
                'embeds' => $group->count(),
            ])
            ->sortBy([['embeds', 'desc'], ['slug', 'asc']])
            ->values();
    }

    protected function key(): string
    {
        return 'statamic-embed:v'.EmbedService::CACHE_VERSION.':providers';
    }
}
