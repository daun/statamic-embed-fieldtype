<?php

namespace Daun\StatamicEmbed\Commands;

use Daun\StatamicEmbed\Services\EmbedService;
use Daun\StatamicEmbed\Services\EmbedUrlFinder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Statamic\Console\RunsInPlease;

class ClearCommand extends Command
{
    use RunsInPlease;

    protected $signature = 'statamic:embed:clear
        {url? : Only clear a single URL}
        {--provider= : Only clear URLs of a provider, e.g. youtube or vimeo.com}';

    protected $description = 'Clear cached embed data';

    public function handle(EmbedService $service, EmbedUrlFinder $finder): int
    {
        if ($url = $this->argument('url')) {
            $service->forget($url);
            $this->components->info("Cleared embed data for {$url}.");

            return self::SUCCESS;
        }

        if ($provider = $this->option('provider')) {
            $urls = $finder->all()->filter(fn ($url) => $this->matchesProvider($service, $url, $provider));
            $urls->each(fn ($url) => $service->forget($url));
            $this->components->info("Cleared embed data for {$urls->count()} URLs of {$provider}.");

            return self::SUCCESS;
        }

        // Flushing a shared store would wipe unrelated data
        if (config('statamic-embed-fieldtype.cache_store')) {
            $urls = $finder->all();
            $urls->each(fn ($url) => $service->forget($url));
            $this->components->info("Cleared embed data for {$urls->count()} URLs.");
        } else {
            $service->flush();
            $this->components->info('Cleared all embed data.');
        }

        return self::SUCCESS;
    }

    protected function matchesProvider(EmbedService $service, string $url, string $provider): bool
    {
        $info = $service->info($url, fetch: false)['provider'] ?? [];

        if (($info['slug'] ?? null) === Str::slug($provider)) {
            return true;
        }

        $provider = $this->hostname($provider);
        $hosts = array_filter([$this->hostname($url), $this->hostname($info['url'] ?? null)]);

        foreach ($hosts as $host) {
            if ($host === $provider || str_ends_with($host, ".{$provider}")) {
                return true;
            }
        }

        return false;
    }

    protected function hostname(?string $url): ?string
    {
        $host = parse_url(str_contains($url ?? '', '://') ? $url : "https://{$url}", PHP_URL_HOST);

        return $host ? preg_replace('/^www\d?\./i', '', strtolower($host)) : null;
    }
}
