<?php

namespace Daun\StatamicEmbed\Fieldtypes;

use Daun\StatamicEmbed\Services\EmbedService;
use Statamic\Fieldtypes\Video;

class Embed extends Video
{
    protected static $handle = 'embed';

    protected static $title = 'Embed';

    protected $icon = 'globe-world-wide-web';

    public function preload()
    {
        $url = $this->field->value();

        return [
            ...(parent::preload() ?? []),
            'route' => cp_route('embed.info'),
            'info' => app(EmbedService::class)->info($url, load: false),
        ];
    }
}
