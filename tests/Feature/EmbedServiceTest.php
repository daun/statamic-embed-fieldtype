<?php

use Daun\StatamicEmbed\Services\EmbedService;
use Embed\Embed;
use Embed\Extractor;
use Illuminate\Support\Facades\Cache;
use Tests\Fakes\FakeEmbed;
use Tests\Fakes\FakeEmbedExtractor as FakeExtractor;

function service(Embed $embed, ?int $ttl = 60): EmbedService
{
    return new EmbedService($embed, Cache::store('array'), $ttl);
}

beforeEach(function () {
    Cache::store('array')->flush();
});

it('caches embed data', function () {
    $embed = new FakeEmbed(new FakeExtractor('First'));
    $service = service($embed);

    expect($service->info('https://youtube.com/watch?v=1')['title'])->toBe('First');
    expect($service->info('https://youtube.com/watch?v=1')['title'])->toBe('First');
    expect($embed->calls)->toBe(1);
});

it('does not fetch when asked not to', function () {
    $embed = new FakeEmbed(new FakeExtractor('First'));

    expect(service($embed)->info('https://youtube.com/watch?v=1', fetch: false))->toBeNull();
    expect($embed->calls)->toBe(0);
});

it('refreshes cached embed data', function () {
    $service = service(new FakeEmbed(new FakeExtractor('First'), new FakeExtractor('Second')));

    expect($service->info('https://youtube.com/watch?v=1')['title'])->toBe('First');
    expect($service->info('https://youtube.com/watch?v=1')['title'])->toBe('First');
    expect($service->info('https://youtube.com/watch?v=1', refresh: true)['title'])->toBe('Second');
});

it('serves stale data and refreshes it after the response', function () {
    $this->withoutDefer();
    $service = service(new FakeEmbed(new FakeExtractor('First'), new FakeExtractor('Second')));

    expect($service->info('https://youtube.com/watch?v=1')['title'])->toBe('First');
    $this->travel(59)->seconds();
    expect($service->info('https://youtube.com/watch?v=1')['title'])->toBe('First');
    $this->travel(2)->seconds();
    expect($service->info('https://youtube.com/watch?v=1')['title'])->toBe('First');
    expect($service->info('https://youtube.com/watch?v=1')['title'])->toBe('Second');
});

it('keeps stale data if refreshing fails', function (Extractor|Throwable $failure) {
    $this->withoutDefer();
    $embed = new FakeEmbed(new FakeExtractor('First'), $failure, new FakeExtractor('Second'));
    $service = service($embed);

    expect($service->info('https://youtube.com/watch?v=1')['title'])->toBe('First');
    $this->travel(61)->seconds();
    expect($service->info('https://youtube.com/watch?v=1')['title'])->toBe('First');
    expect($embed->calls)->toBe(2);

    // Failed refresh counts as fetched, so it's not retried on every request
    expect($service->info('https://youtube.com/watch?v=1')['title'])->toBe('First');
    expect($embed->calls)->toBe(2);

    $this->travel(61)->seconds();
    $service->info('https://youtube.com/watch?v=1');
    expect($service->info('https://youtube.com/watch?v=1')['title'])->toBe('Second');
})->with([
    'exception' => fn () => new RuntimeException('Network error'),
    'http error' => fn () => new FakeExtractor('Not found', 404),
]);

it('keeps previous data if a manual refresh fails', function () {
    $service = service(new FakeEmbed(new FakeExtractor('First'), new RuntimeException('Network error')));

    expect($service->info('https://youtube.com/watch?v=1')['title'])->toBe('First');
    expect($service->info('https://youtube.com/watch?v=1', refresh: true)['title'])->toBe('First');
});

it('retries failed initial fetches sooner', function () {
    $service = service(new FakeEmbed(new RuntimeException('Network error'), new FakeExtractor('First')), ttl: null);

    expect($service->info('https://youtube.com/watch?v=1'))->toBe([]);
    expect($service->info('https://youtube.com/watch?v=1'))->toBe([]);
    $this->travel(EmbedService::FAILURE_TTL + 1)->seconds();
    expect($service->info('https://youtube.com/watch?v=1')['title'])->toBe('First');
});

it('never considers data stale without ttl', function () {
    $this->withoutDefer();
    $embed = new FakeEmbed(new FakeExtractor('First'));
    $service = service($embed, ttl: null);

    $service->info('https://youtube.com/watch?v=1');
    $this->travel(10)->years();
    expect($service->info('https://youtube.com/watch?v=1')['title'])->toBe('First');
    expect($service->isFresh('https://youtube.com/watch?v=1'))->toBeTrue();
    expect($embed->calls)->toBe(1);
});

it('warms multiple urls in parallel', function () {
    $embed = new FakeEmbed(new FakeExtractor('First'), new FakeExtractor('Second'));
    $service = service($embed);

    $service->warm(['https://a.com', 'https://b.com', 'https://a.com', 'invalid']);

    expect($embed->multiCalls)->toBe(1);
    expect($service->info('https://a.com', fetch: false)['title'])->toBe('First');
    expect($service->info('https://b.com', fetch: false)['title'])->toBe('Second');
});

it('warms urls one by one if a parallel request fails', function () {
    $embed = new FakeEmbed(new FakeExtractor('First'), new RuntimeException('Network error'));
    $embed->failMulti = true;
    $service = service($embed);

    $service->warm(['https://a.com', 'https://b.com']);

    expect($service->info('https://a.com', fetch: false)['title'])->toBe('First');
    expect($service->info('https://b.com', fetch: false))->toBe([]);
});

it('forgets and flushes cached data', function () {
    $service = service(new FakeEmbed(new FakeExtractor('First'), new FakeExtractor('Second')));
    $service->warm(['https://a.com', 'https://b.com']);

    $service->forget('https://a.com');
    expect($service->info('https://a.com', fetch: false))->toBeNull();
    expect($service->info('https://b.com', fetch: false))->not->toBeNull();

    $service->flush();
    expect($service->info('https://b.com', fetch: false))->toBeNull();
});

it('reads cache config', function () {
    config([
        'statamic-embed-fieldtype.cache_store' => 'array',
        'statamic-embed-fieldtype.cache_ttl' => 60,
    ]);
    app()->instance(Embed::class, new FakeEmbed(new FakeExtractor('First')));
    app()->forgetInstance(EmbedService::class);

    $service = app(EmbedService::class);
    $service->info('https://youtube.com/watch?v=1');

    expect(Cache::store('array')->get('statamic-embed:v1:'.md5('https://youtube.com/watch?v=1')))->not->toBeNull();
    expect($service->isFresh('https://youtube.com/watch?v=1'))->toBeTrue();
    $this->travel(61)->seconds();
    expect($service->isFresh('https://youtube.com/watch?v=1'))->toBeFalse();
});
