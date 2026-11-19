<?php

use Daun\StatamicEmbed\Jobs\WarmEmbedData;
use Daun\StatamicEmbed\Services\EmbedService;
use Daun\StatamicEmbed\Services\EmbedUrlFinder;
use Embed\Embed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;
use Tests\Fakes\FakeEmbed;
use Tests\Fakes\FakeEmbedExtractor as FakeExtractor;

function fakeContent(array $data): void
{
    $blueprint = Blueprint::makeFromFields([
        'video' => ['type' => 'embed'],
        'link' => ['type' => 'text'],
        'grid' => ['type' => 'grid', 'fields' => [
            ['handle' => 'video', 'field' => ['type' => 'embed']],
        ]],
        'group' => ['type' => 'group', 'fields' => [
            ['handle' => 'video', 'field' => ['type' => 'embed']],
        ]],
        'replicator' => ['type' => 'replicator', 'sets' => ['main' => ['sets' => [
            'media' => ['fields' => [['handle' => 'video', 'field' => ['type' => 'embed']]]],
        ]]]],
        'bard' => ['type' => 'bard', 'sets' => ['main' => ['sets' => [
            'media' => ['fields' => [['handle' => 'video', 'field' => ['type' => 'embed']]]],
        ]]]],
    ]);

    $item = new class($blueprint, $data)
    {
        public function __construct(private $blueprint, private array $data) {}

        public function blueprint()
        {
            return $this->blueprint;
        }

        public function data()
        {
            return collect($this->data);
        }
    };

    Entry::shouldReceive('all')->andReturn(EntryCollection::make([$item]));
}

beforeEach(function () {
    config(['statamic-embed-fieldtype.cache_store' => 'array']);
    Cache::store('array')->flush();
    // In order of the urls found in the content below
    $providers = ['YouTube', 'YouTube', 'Vimeo', 'Vimeo', 'YouTube'];
    app()->instance(Embed::class, new FakeEmbed(...array_map(
        fn ($provider) => new FakeExtractor($provider, fakeProviderName: $provider),
        [...$providers, ...$providers],
    )));
    app()->forgetInstance(EmbedService::class);

    fakeContent([
        'video' => 'https://youtube.com/watch?v=1',
        'link' => 'https://example.com',
        'grid' => [['video' => 'https://youtu.be/2']],
        'group' => ['video' => 'https://vimeo.com/3'],
        'replicator' => [['type' => 'media', 'video' => 'https://vimeo.com/4']],
        'bard' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'https://example.com']]],
            ['type' => 'set', 'attrs' => ['values' => ['type' => 'media', 'video' => 'https://youtube.com/watch?v=1']]],
            ['type' => 'set', 'attrs' => ['values' => ['type' => 'media', 'video' => 'https://youtube.com/watch?v=5']]],
        ],
    ]);
});

it('finds embed urls in nested fields', function () {
    expect(app(EmbedUrlFinder::class)->all()->all())->toBe([
        'https://youtube.com/watch?v=1',
        'https://youtu.be/2',
        'https://vimeo.com/3',
        'https://vimeo.com/4',
        'https://youtube.com/watch?v=5',
    ]);
});

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
