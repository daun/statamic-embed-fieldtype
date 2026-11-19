<?php

namespace Daun\StatamicEmbed\Commands;

use Daun\StatamicEmbed\Jobs\WarmEmbedData;
use Daun\StatamicEmbed\Services\EmbedService;
use Daun\StatamicEmbed\Services\EmbedUrlFinder;
use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;

class WarmCommand extends Command
{
    use RunsInPlease;

    protected $signature = 'statamic:embed:warm
        {--force : Refetch URLs that are already cached and fresh}
        {--queue : Dispatch queued jobs instead of fetching right away}
        {--chunk=10 : Number of URLs to fetch in parallel}';

    protected $description = 'Fetch and cache embed data for all embed fields';

    public function handle(EmbedService $service, EmbedUrlFinder $finder): int
    {
        $urls = $finder->all();

        if (! $this->option('force')) {
            $urls = $urls->reject(fn ($url) => $service->isFresh($url));
        }

        if ($urls->isEmpty()) {
            $this->components->info('Embed data is already warm.');

            return self::SUCCESS;
        }

        $chunks = $urls->chunk(max(1, (int) $this->option('chunk')))->map->values();

        if ($this->option('queue')) {
            $chunks->each(fn ($chunk) => WarmEmbedData::dispatch($chunk->all()));
            $this->components->info("Dispatched {$chunks->count()} jobs to warm {$urls->count()} URLs.");
        } else {
            $this->withProgressBar($chunks, fn ($chunk) => $service->warm($chunk->all()));
            $this->newLine();
            $this->components->info("Warmed {$urls->count()} URLs.");
        }

        return self::SUCCESS;
    }
}
