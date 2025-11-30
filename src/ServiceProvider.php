<?php

namespace Daun\StatamicEmbed;

use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $vite = [
        'input' => [
            'resources/css/addon.css',
            'resources/js/addon.js',
        ],
        'publicDirectory' => 'resources/dist',
    ];
}
