<?php

use Daun\StatamicEmbed\Services\EmbedService;
use Embed\Embed;
use Embed\Extractor;
use Embed\OEmbed;
use Illuminate\Support\Facades\Cache;

class RefreshFakeOEmbed extends OEmbed
{
    public function __construct() {}

    public function all(): array
    {
        return [];
    }
}

class RefreshFakeExtractor extends Extractor
{
    public function __construct(private string $fakeTitle) {}

    public function __get(string $name)
    {
        return $name === 'title' ? $this->fakeTitle : null;
    }

    public function getOEmbed(): OEmbed
    {
        return new RefreshFakeOEmbed;
    }
}

class RefreshFakeEmbed extends Embed
{
    public array $extractors = [];

    private int $calls = 0;

    public function get(string $url): Extractor
    {
        return $this->extractors[$this->calls++];
    }
}

it('refreshes cached embed data', function () {
    Cache::flush();

    $embed = new RefreshFakeEmbed;
    $embed->extractors = [
        new RefreshFakeExtractor('First title'),
        new RefreshFakeExtractor('Second title'),
    ];

    $service = new EmbedService($embed);

    expect($service->info('https://youtube.com/watch?v=1')['title'])->toBe('First title');
    expect($service->info('https://youtube.com/watch?v=1')['title'])->toBe('First title');
    expect($service->info('https://youtube.com/watch?v=1', refresh: true)['title'])->toBe('Second title');
});
