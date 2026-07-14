<?php

use Daun\StatamicEmbed\Http\Resources\EmbedResource;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Http\Request;

it('casts uri objects to strings so the payload is json- and cache-safe', function () {
    // The Embed library exposes url/providerUrl/authorUrl as PSR-7 UriInterface
    // objects. This stand-in mirrors that shape.
    $resource = new class
    {
        public Uri $url;

        public ?string $title = 'Test video';

        public ?string $description = null;

        public ?string $language = null;

        public $code = null;

        public $image = null;

        public ?string $providerName = 'YouTube';

        public Uri $providerUrl;

        public ?string $authorName = 'Some Author';

        public Uri $authorUrl;

        public function __construct()
        {
            $this->url = new Uri('https://www.youtube.com/watch?v=abc');
            $this->providerUrl = new Uri('https://www.youtube.com');
            $this->authorUrl = new Uri('https://www.youtube.com/@author');
        }

        public function getOEmbed()
        {
            return new class
            {
                public function all(): array
                {
                    return [];
                }
            };
        }
    };

    $data = (new EmbedResource($resource))->resolve(new Request);

    expect($data['url'])->toBe('https://www.youtube.com/watch?v=abc');
    expect($data['provider']['url'])->toBe('https://www.youtube.com');
    expect($data['author']['url'])->toBe('https://www.youtube.com/@author');
});
