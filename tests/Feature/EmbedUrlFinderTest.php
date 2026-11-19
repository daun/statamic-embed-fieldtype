<?php

use Daun\StatamicEmbed\Services\EmbedUrlFinder;
use Tests\Concerns\CreatesEmbedContent;

uses(CreatesEmbedContent::class);

beforeEach(fn () => $this->setUpEmbedContent());
afterEach(fn () => $this->tearDownEmbedContent());

it('finds embed urls in nested and imported fields', function () {
    $this->createPage([
        'video' => 'https://youtube.com/watch?v=1',
        'link' => 'https://example.com',
        'imported_video' => 'https://youtube.com/watch?v=2',
        'grid' => [['video' => 'https://youtube.com/watch?v=3']],
        'group' => ['video' => 'https://youtube.com/watch?v=4'],
        'blocks' => [['type' => 'media', 'video' => 'https://youtube.com/watch?v=5']],
        'body' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'https://example.com']]],
            ['type' => 'set', 'attrs' => ['values' => ['type' => 'media', 'video' => 'https://youtube.com/watch?v=6']]],
        ],
    ]);

    expect(app(EmbedUrlFinder::class)->all()->sort()->values()->all())->toBe([
        'https://youtube.com/watch?v=1',
        'https://youtube.com/watch?v=2',
        'https://youtube.com/watch?v=3',
        'https://youtube.com/watch?v=4',
        'https://youtube.com/watch?v=5',
        'https://youtube.com/watch?v=6',
    ]);
});

it('counts every occurrence per item', function () {
    $this->createPage([
        'video' => 'https://youtube.com/watch?v=1',
        'blocks' => [['type' => 'media', 'video' => 'https://youtube.com/watch?v=1']],
    ]);

    expect(app(EmbedUrlFinder::class)->items()->all())->toBe([
        ['site' => 'en', 'urls' => ['https://youtube.com/watch?v=1', 'https://youtube.com/watch?v=1']],
    ]);
});

it('includes values inherited by localizations', function () {
    $origin = $this->createPage(['video' => 'https://youtube.com/watch?v=1']);
    $this->createPage([], site: 'de', origin: $origin);

    expect(app(EmbedUrlFinder::class)->items()->all())->toBe([
        ['site' => 'en', 'urls' => ['https://youtube.com/watch?v=1']],
        ['site' => 'de', 'urls' => ['https://youtube.com/watch?v=1']],
    ]);
});

it('skips drafts if requested', function () {
    $this->createPage(['video' => 'https://youtube.com/watch?v=1']);
    $this->createPage(['video' => 'https://youtube.com/watch?v=2'], published: false);

    expect(app(EmbedUrlFinder::class)->all()->all())->toBe([
        'https://youtube.com/watch?v=1',
        'https://youtube.com/watch?v=2',
    ]);
    expect(app(EmbedUrlFinder::class)->all(published: true)->all())->toBe([
        'https://youtube.com/watch?v=1',
    ]);
});
