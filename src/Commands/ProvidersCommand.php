<?php

namespace Daun\StatamicEmbed\Commands;

use Daun\StatamicEmbed\Support\EmbedProviders;
use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;

class ProvidersCommand extends Command
{
    use RunsInPlease;

    protected $signature = 'statamic:embed:providers
        {--rebuild : Rebuild the list and fetch missing embed data}';

    protected $description = 'List embed providers used in published content';

    public function handle(EmbedProviders $providers): int
    {
        if ($this->option('rebuild')) {
            $providers->build();
        }

        $all = $providers->all();

        if ($all->isEmpty()) {
            $this->components->info('No embed providers found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Provider', 'Slug', 'Embeds', 'URL'],
            $all->map(fn ($provider) => [$provider['name'], $provider['slug'], $provider['embeds'], $provider['url']]),
        );

        return self::SUCCESS;
    }
}
