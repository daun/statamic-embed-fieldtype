<?php

use Daun\StatamicEmbed\Facades\EmbedProviders;
use Daun\StatamicEmbed\Services\EmbedService;
use Daun\StatamicEmbed\Support\EmbedProviders as Providers;
use Embed\Embed;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Cache;
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

    expect(EmbedProviders::all()->map(fn ($p) => [$p['slug'], $p['name'], $p['count']])->all())->toBe([
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

    expect(EmbedProviders::all()->first()['count'])->toBe(2);
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
    $this->createPage(['video' => 'https://open.spotify.com/track/1']);

    $this->travel(Providers::TTL - 1)->seconds();
    expect(EmbedProviders::slugs())->toBe(['youtube']);
    runDeferred();
    expect(EmbedProviders::slugs())->toBe(['youtube']);

    $this->travel(2)->seconds();
    expect(EmbedProviders::slugs())->toBe(['youtube']);
    runDeferred();
    expect(EmbedProviders::slugs())->toBe(['spotify', 'youtube']);
});

it('removes providers no longer in use', function () {
    $entry = $this->createPage(['video' => 'https://open.spotify.com/track/1']);
    EmbedProviders::build();
    expect(EmbedProviders::slugs())->toBe(['spotify']);

    $entry->delete();
    EmbedProviders::build();
    expect(EmbedProviders::slugs())->toBe([]);
});
