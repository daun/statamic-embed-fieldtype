<?php

namespace Daun\StatamicEmbed\Fieldtypes;

use Daun\StatamicEmbed\Services\EmbedService;
use Statamic\Fieldtypes\Video;

class Embed extends Video
{
    protected static $handle = 'embed';

    protected static $title = 'Embed';

    protected $icon = 'globe-world-wide-web';


    protected function configFieldItems(): array
    {
        return [
            ...parent::configFieldItems(),
            [
                'display' => __('Frontend'),
                'fields' => [
                    'augment_to_embed_data' => [
                        'display' => __('Augment to Embed Data'),
                        'instructions' => __('Whether to automatically augment the field value to include the embed data. If disabled, only the URL will be returned on the frontend.'),
                        'type' => 'toggle',
                        'default' => true,
                    ],
                ],
            ],
        ];
    }

    public function preload()
    {
        $url = $this->field->value();

        return [
            ...(parent::preload() ?? []),
            'route' => cp_route('embed.info'),
            'info' => app(EmbedService::class)->info($url, load: false),
        ];
    }

    public function augment($value)
    {
        if ($value && $this->config('augment_to_embed_data', true)) {
            return app(EmbedService::class)->info($value);
        } else {
            return $value;
        }
    }
}
