<?php

namespace Daun\StatamicEmbed\Tags;

use Daun\StatamicEmbed\Support\EmbedProviders as Providers;
use Statamic\Tags\Tags;

class EmbedProviders extends Tags
{
    protected static $handle = 'embed_providers';

    /**
     * {{ embed_providers }} {{ slug }} {{ name }} {{ url }} {{ embeds }} {{ /embed_providers }}
     */
    public function index(): array
    {
        return app(Providers::class)->all()->all();
    }

    /**
     * {{ embed_providers:slugs }} {{ value }} {{ /embed_providers:slugs }}
     */
    public function slugs(): array
    {
        return app(Providers::class)->slugs();
    }
}
