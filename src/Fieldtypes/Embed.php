<?php

namespace Daun\StatamicEmbed\Fieldtypes;

use Daun\StatamicEmbed\Services\EmbedService;
use Statamic\Fields\Fieldtype;

class Embed extends Fieldtype
{
    protected $icon = 'globe-world-wide-web';

    protected $categories = ['media'];

    protected function configFieldItems(): array
    {
        return [
            [
                'display' => __('Appearance'),
                'fields' => [
                    'border' => [
                        'display' => __('Border'),
                        'instructions' => __('statamic::fieldtypes.grid.config.border'),
                        'type' => 'toggle',
                        'default' => true,
                    ],
                    'placeholder' => [
                        'display' => __('Placeholder'),
                        'instructions' => __('statamic::fieldtypes.text.config.placeholder'),
                        'type' => 'text',
                    ],
                    'prepend' => [
                        'display' => __('Prepend'),
                        'instructions' => __('statamic::fieldtypes.text.config.prepend'),
                        'type' => 'text',
                    ],
                    'preview_type' => [
                        'display' => __('Preview'),
                        'type' => 'button_group',
                        'options' => [
                            'embed' => __('Embed'),
                            'thumbnail' => __('Thumbnail'),
                            'text' => __('Text'),
                            'none' => __('None'),
                        ],
                        'default' => 'embed',
                    ],
                ],
            ],
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
            'info' => app(EmbedService::class)->info($url, fetch: false),
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
