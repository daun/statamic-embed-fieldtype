<?php

namespace Daun\StatamicEmbed\Jobs;

use Daun\StatamicEmbed\Services\EmbedService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class WarmEmbedData implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string[]  $urls
     */
    public function __construct(public array $urls) {}

    public function handle(EmbedService $service): void
    {
        $service->warm($this->urls);
    }
}
