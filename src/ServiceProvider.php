<?php

namespace Daun\StatamicEmbed;

use Daun\StatamicEmbed\Services\EmbedService;
use Embed\Http\FactoryDiscovery;
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

    public function register()
    {
        parent::register();

        $this->app->singleton(EmbedService::class);

        $this->allowUnserializingCachedUris();
    }

    protected function allowUnserializingCachedUris(): void
    {
        if (! method_exists($this, 'registerSerializableClasses')) {
            return;
        }

        try {
            $uri = FactoryDiscovery::getUriFactory()->createUri('');
        } catch (\Throwable) {
            return;
        }

        $this->registerSerializableClasses([$uri::class]);
    }
}
