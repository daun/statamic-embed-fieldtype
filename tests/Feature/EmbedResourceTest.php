<?php

use Daun\StatamicEmbed\Http\Resources\EmbedResource;
use GuzzleHttp\Psr7\Uri;

class FakeCode
{
    public $html = '<iframe width="560" height="315" src="https://player.example/1"></iframe>';

    public $width = 560;

    public $height = 315;
}

class FakeOEmbed
{
    public function all(): array
    {
        return [
            'type' => 'video',
            'thumbnail_url' => new Uri('https://img.example/thumb.jpg'),
            'thumbnail_width' => 480,
            'thumbnail_height' => 360,
        ];
    }
}

class FakeExtractor
{
    public $url;

    public $providerUrl;

    public $authorUrl;

    public $image;

    public $code;

    public $title = 'Title';

    public $description = 'Description';

    public $language = 'en';

    public $providerName = 'YouTube';

    public $authorName = 'Someone';

    public function __construct(bool $withImage = true)
    {
        $this->url = new Uri('https://youtube.com/watch?v=1');
        $this->providerUrl = new Uri('https://youtube.com');
        $this->authorUrl = new Uri('https://youtube.com/@someone');
        $this->image = $withImage ? new Uri('https://img.example/image.jpg') : null;
        $this->code = new FakeCode;
    }

    public function getOEmbed()
    {
        return new FakeOEmbed;
    }
}

function objectsIn(array $data): array
{
    $objects = [];
    array_walk_recursive($data, function ($value, $key) use (&$objects) {
        if (is_object($value)) {
            $objects[] = "{$key}: ".get_class($value);
        }
    });

    return $objects;
}

it('casts uris to strings', function () {
    $data = (new EmbedResource(new FakeExtractor))->resolve();

    expect($data['url'])->toBe('https://youtube.com/watch?v=1');
    expect($data['provider']['url'])->toBe('https://youtube.com');
    expect($data['author']['url'])->toBe('https://youtube.com/@someone');
    expect($data['image']['url'])->toBe('https://img.example/image.jpg');
});

it('falls back to the oembed thumbnail url', function () {
    $data = (new EmbedResource(new FakeExtractor(withImage: false)))->resolve();

    expect($data['image']['url'])->toBe('https://img.example/thumb.jpg');
});

// Laravel 13 refuses to unserialize cached payloads containing unlisted classes
it('resolves to a payload without objects', function () {
    $data = (new EmbedResource(new FakeExtractor))->resolve();

    expect(objectsIn($data))->toBe([]);
    expect(unserialize(serialize($data), ['allowed_classes' => false]))->toBe($data);
});
