<?php

use Daun\StatamicEmbed\Jobs\WarmEmbedData;
use Daun\StatamicEmbed\Services\EmbedService;
use Embed\Embed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesEmbedContent;
use Tests\Fakes\FakeEmbed;
use Tests\Fakes\FakeEmbedExtractor as FakeExtractor;

uses(CreatesEmbedContent::class);

function fakeProvider(string $url): FakeExtractor
{
    $provider = str_contains($url, 'vimeo') ? 'Vimeo' : 'YouTube';

    return new FakeExtractor($provider, fakeProviderName: $provider);
}

beforeEach(function () {
    config(['statamic-embed-fieldtype.cache_store' => 'array']);
    Cache::store('array')->flush();
    app()->instance(Embed::class, FakeEmbed::resolving(fakeProvider(...)));
    app()->forgetInstance(EmbedService::class);

    $this->setUpEmbedContent();
    $this->createPage([
        'video' => 'https://youtube.com/watch?v=1',
        'grid' => [['video' => 'https://youtu.be/2']],
        'group' => ['video' => 'https://vimeo.com/3'],
        'blocks' => [['type' => 'media', 'video' => 'https://vimeo.com/4']],
        'body' => [
            ['type' => 'set', 'attrs' => ['values' => ['type' => 'media', 'video' => 'https://youtube.com/watch?v=1']]],
            ['type' => 'set', 'attrs' => ['values' => ['type' => 'media', 'video' => 'https://youtube.com/watch?v=5']]],
        ],
    ]);
});

afterEach(fn () => $this->tearDownEmbedContent());

it('warms embed data', function () {
    $service = app(EmbedService::class);
    $service->info('https://youtube.com/watch?v=1');

    $this->artisan('statamic:embed:warm')->assertSuccessful();

    expect(app(Embed::class)->calls)->toBe(5);
    expect($service->isFresh('https://vimeo.com/4'))->toBeTrue();

    $this->artisan('statamic:embed:warm')->expectsOutputToContain('already warm');
    $this->artisan('statamic:embed:warm --force');
    expect(app(Embed::class)->calls)->toBe(10);
});

it('warms embed data in queued jobs', function () {
    Queue::fake();

    $this->artisan('statamic:embed:warm --queue --chunk=2')->assertSuccessful();

    Queue::assertPushed(WarmEmbedData::class, 3);
    Queue::assertPushed(WarmEmbedData::class, fn ($job) => $job->urls === ['https://youtube.com/watch?v=1', 'https://youtu.be/2']);
});

it('clears a single url', function () {
    $this->artisan('statamic:embed:warm');

    $this->artisan('statamic:embed:clear https://vimeo.com/3')->assertSuccessful();

    expect(app(EmbedService::class)->isFresh('https://vimeo.com/3'))->toBeFalse();
    expect(app(EmbedService::class)->isFresh('https://vimeo.com/4'))->toBeTrue();
});

it('clears urls of a provider', function () {
    $this->artisan('statamic:embed:warm');

    $this->artisan('statamic:embed:clear --provider=YouTube')->assertSuccessful();

    $service = app(EmbedService::class);
    expect($service->isFresh('https://youtube.com/watch?v=1'))->toBeFalse();
    expect($service->isFresh('https://youtu.be/2'))->toBeFalse();
    expect($service->isFresh('https://youtube.com/watch?v=5'))->toBeFalse();
    expect($service->isFresh('https://vimeo.com/3'))->toBeTrue();
});

it('clears urls of a provider hostname', function () {
    $this->artisan('statamic:embed:warm');

    $this->artisan('statamic:embed:clear --provider=www.youtube.com')->assertSuccessful();

    $service = app(EmbedService::class);
    expect($service->isFresh('https://youtube.com/watch?v=1'))->toBeFalse();
    expect($service->isFresh('https://youtube.com/watch?v=5'))->toBeFalse();
    expect($service->isFresh('https://youtu.be/2'))->toBeTrue();
    expect($service->isFresh('https://vimeo.com/3'))->toBeTrue();
});

it('clears all urls without flushing a shared store', function () {
    Cache::store('array')->put('unrelated', 'value');
    $this->artisan('statamic:embed:warm');

    $this->artisan('statamic:embed:clear')->assertSuccessful();

    expect(app(EmbedService::class)->isFresh('https://vimeo.com/3'))->toBeFalse();
    expect(Cache::store('array')->get('unrelated'))->toBe('value');
});

it('flushes the dedicated store when clearing all urls', function () {
    config(['statamic-embed-fieldtype.cache_store' => null]);
    app()->forgetInstance(EmbedService::class);
    $this->artisan('statamic:embed:warm');
    expect(app(EmbedService::class)->isFresh('https://vimeo.com/3'))->toBeTrue();
    expect(storage_path('statamic/embed'))->toBeDirectory();

    $this->artisan('statamic:embed:clear')->expectsOutputToContain('Cleared all embed data');

    expect(app(EmbedService::class)->isFresh('https://vimeo.com/3'))->toBeFalse();
});
