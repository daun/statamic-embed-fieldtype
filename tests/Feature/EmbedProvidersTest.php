<?php

use Daun\StatamicEmbed\Facades\EmbedProviders;
use Daun\StatamicEmbed\Services\EmbedService;
use Daun\StatamicEmbed\Support\EmbedProviders as Providers;
use Embed\Embed;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Cache;
use Statamic\Facades\Antlers;
use Tests\Concerns\CreatesEmbedContent;
use Tests\Fakes\FakeEmbed;
use Tests\Fakes\FakeEmbedExtractor as FakeExtractor;

uses(CreatesEmbedContent::class);

beforeEach(function () {
    config(['statamic-embed-fieldtype.cache_store' => 'array']);
    Cache::store('array')->flush();

    $this->embed = FakeEmbed::resolving(fn ($url) => match (true) {
        str_contains($url, 'broken') => new FakeExtractor('Not found', 404),
        str_contains($url, 'spotify') => new FakeExtractor('Song', fakeProviderName: 'Spotify'),
        default => new FakeExtractor('Video', fakeProviderName: 'YouTube'),
    });
    app()->instance(Embed::class, $this->embed);

    $this->setUpEmbedContent();
});

afterEach(fn () => $this->tearDownEmbedContent());

function runDeferred(): void
{
    app(DeferredCallbackCollection::class)->invoke();
}

it('groups and counts providers', function () {
    $this->createPage([
        'video' => 'https://youtube.com/watch?v=1',
        'blocks' => [
            ['type' => 'media', 'video' => 'https://youtube.com/watch?v=1'],
            ['type' => 'media', 'video' => 'https://open.spotify.com/track/1'],
        ],
    ]);
    $this->createPage(['video' => 'https://youtube.com/watch?v=2']);
    EmbedProviders::build();

    expect(EmbedProviders::all()->map(fn ($p) => [$p['slug'], $p['name'], $p['embeds']])->all())->toBe([
        ['youtube', 'YouTube', 3],
        ['spotify', 'Spotify', 1],
    ]);
    expect(EmbedProviders::slugs())->toBe(['youtube', 'spotify']);
});

it('skips drafts', function () {
    $this->createPage(['video' => 'https://youtube.com/watch?v=1']);
    $this->createPage(['video' => 'https://open.spotify.com/track/1'], published: false);
    EmbedProviders::build();

    expect(EmbedProviders::slugs())->toBe(['youtube']);
});

it('counts urls per localization', function () {
    $origin = $this->createPage(['video' => 'https://youtube.com/watch?v=1']);
    $this->createPage([], site: 'de', origin: $origin);
    EmbedProviders::build();

    expect(EmbedProviders::all()->first()['embeds'])->toBe(2);
});

it('leaves out urls that fail to resolve', function () {
    $this->createPage(['video' => 'https://youtube.com/watch?v=1']);
    $this->createPage(['video' => 'https://broken.example/1']);
    EmbedProviders::build();

    expect(EmbedProviders::slugs())->toBe(['youtube']);
});

it('uses cached embed data without fetching', function () {
    $this->createPage(['video' => 'https://youtube.com/watch?v=1']);
    app(EmbedService::class)->info('https://youtube.com/watch?v=1');

    expect(EmbedProviders::slugs())->toBe(['youtube']);
    expect($this->embed->calls)->toBe(1);
});

it('fetches uncached urls after the response', function () {
    $this->createPage(['video' => 'https://youtube.com/watch?v=1']);

    expect(EmbedProviders::slugs())->toBe([]);
    expect($this->embed->calls)->toBe(0);

    runDeferred();

    expect($this->embed->calls)->toBe(1);
    expect(EmbedProviders::slugs())->toBe(['youtube']);
});

it('rebuilds stale lists after the response', function () {
    $this->createPage(['video' => 'https://youtube.com/watch?v=1']);
    EmbedProviders::build();
    $this->createPage(['video' => 'https://open.spotify.com/track/1'], quietly: true);

    $this->travel(Providers::TTL - 1)->seconds();
    expect(EmbedProviders::slugs())->toBe(['youtube']);
    runDeferred();
    expect(EmbedProviders::slugs())->toBe(['youtube']);

    $this->travel(2)->seconds();
    expect(EmbedProviders::slugs())->toBe(['youtube']);
    runDeferred();
    expect(EmbedProviders::slugs())->toBe(['spotify', 'youtube']);
});

it('rebuilds after content is saved', function () {
    $this->createPage(['video' => 'https://youtube.com/watch?v=1']);
    EmbedProviders::build();

    $this->createPage(['video' => 'https://open.spotify.com/track/1']);
    expect(EmbedProviders::slugs())->toBe(['youtube']);

    runDeferred();
    expect(EmbedProviders::slugs())->toBe(['spotify', 'youtube']);
});

it('schedules a single rebuild across concurrent requests', function () {
    $this->createPage(['video' => 'https://youtube.com/watch?v=1']);
    EmbedProviders::build();
    EmbedProviders::invalidate();

    EmbedProviders::all();
    expect(app(DeferredCallbackCollection::class)->count())->toBe(1);

    app()->instance(DeferredCallbackCollection::class, new DeferredCallbackCollection);
    EmbedProviders::all();
    expect(app(DeferredCallbackCollection::class)->count())->toBe(0);
});

it('removes providers no longer in use', function () {
    $entry = $this->createPage(['video' => 'https://open.spotify.com/track/1']);
    EmbedProviders::build();
    expect(EmbedProviders::slugs())->toBe(['spotify']);

    $entry->delete();
    EmbedProviders::build();
    expect(EmbedProviders::slugs())->toBe([]);
});

it('returns providers as json', function () {
    config(['statamic-embed-fieldtype.providers_route' => true]);
    $this->createPage(['video' => 'https://youtube.com/watch?v=1']);
    EmbedProviders::build();

    $this->get('/!/embed/providers')
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=1800, public')
        ->assertExactJson([
            'providers' => [['slug' => 'youtube', 'name' => 'YouTube', 'url' => null, 'embeds' => 1]],
            'slugs' => ['youtube'],
        ]);
});

it('provides an antlers tag', function () {
    $this->createPage([
        'video' => 'https://youtube.com/watch?v=1',
        'blocks' => [['type' => 'media', 'video' => 'https://open.spotify.com/track/1']],
    ]);
    EmbedProviders::build();

    expect((string) Antlers::parse('{{ embed_providers }}{{ name }}:{{ embeds }} {{ /embed_providers }}'))->toBe('Spotify:1 YouTube:1 ');
    expect((string) Antlers::parse('{{ embed_providers:slugs }}{{ value }} {{ /embed_providers:slugs }}'))->toBe('spotify youtube ');
});

it('rebuilds and prints providers', function () {
    $this->createPage(['video' => 'https://youtube.com/watch?v=1']);

    $this->artisan('statamic:embed:providers')->expectsOutputToContain('No embed providers found');

    $this->artisan('statamic:embed:providers --rebuild')
        ->expectsTable(['Provider', 'Slug', 'Embeds', 'URL'], [['YouTube', 'youtube', 1, null]])
        ->assertSuccessful();
});

it('disables the json route by default', function () {
    $this->get('/!/embed/providers')->assertNotFound();
});
