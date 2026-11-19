<?php

namespace Daun\StatamicEmbed\Listeners;

use Daun\StatamicEmbed\Support\EmbedProviders;

class InvalidateEmbedProviders
{
    public function __construct(protected EmbedProviders $providers) {}

    public function handle(): void
    {
        $this->providers->invalidate();
    }
}
