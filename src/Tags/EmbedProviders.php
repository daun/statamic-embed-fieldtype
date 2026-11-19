<?php

namespace Daun\StatamicEmbed\Tags;

use Daun\StatamicEmbed\Support\EmbedProviders as Providers;
use Statamic\Tags\Tags;

class EmbedProviders extends Tags
{
    protected static $handle = 'embed_providers';

    public function index(): array
    {
        return app(Providers::class)->all()->all();
    }

    public function slugs(): array
    {
        return app(Providers::class)->slugs();
    }
}
