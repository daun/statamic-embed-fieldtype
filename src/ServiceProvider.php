<?php

namespace Daun\StatamicEmbed;

use Daun\StatamicEmbed\Commands\ClearCommand;
use Daun\StatamicEmbed\Commands\ProvidersCommand;
use Daun\StatamicEmbed\Commands\WarmCommand;
use Daun\StatamicEmbed\Listeners\InvalidateEmbedProviders;
use Daun\StatamicEmbed\Services\EmbedService;
use Daun\StatamicEmbed\Services\EmbedUrlFinder;
use Daun\StatamicEmbed\Support\EmbedProviders;
use Embed\Embed;
use Embed\Http\FactoryDiscovery;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Statamic\Events;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $commands = [
        ClearCommand::class,
        ProvidersCommand::class,
        WarmCommand::class,
    ];

    protected $listen = [
        Events\EntrySaved::class => [InvalidateEmbedProviders::class],
        Events\EntryDeleted::class => [InvalidateEmbedProviders::class],
        Events\EntryScheduleReached::class => [InvalidateEmbedProviders::class],
        Events\TermSaved::class => [InvalidateEmbedProviders::class],
        Events\TermDeleted::class => [InvalidateEmbedProviders::class],
        Events\LocalizedTermSaved::class => [InvalidateEmbedProviders::class],
        Events\LocalizedTermDeleted::class => [InvalidateEmbedProviders::class],
        Events\GlobalVariablesSaved::class => [InvalidateEmbedProviders::class],
        Events\GlobalVariablesDeleted::class => [InvalidateEmbedProviders::class],
        Events\StacheCleared::class => [InvalidateEmbedProviders::class],
        Events\StacheWarmed::class => [InvalidateEmbedProviders::class],
    ];

    protected $tags = [
        Tags\EmbedProviders::class,
    ];

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

        $this->app->singleton(EmbedService::class, fn ($app) => new EmbedService(
            $app->make(Embed::class),
            $this->cacheStore(),
            config('statamic-embed-fieldtype.cache_ttl', 60 * 60 * 24 * 30),
        ));

        $this->app->singleton(EmbedProviders::class, fn ($app) => new EmbedProviders(
            $app->make(EmbedService::class),
            $app->make(EmbedUrlFinder::class),
            $this->cacheStore(),
        ));

        $this->allowUnserializingCachedUris();
    }

    protected function cacheStore(): Repository
    {
        $store = config('statamic-embed-fieldtype.cache_store');

        return $store
            ? Cache::store($store)
            : Cache::build(['driver' => 'file', 'path' => storage_path('statamic/embed')]);
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
