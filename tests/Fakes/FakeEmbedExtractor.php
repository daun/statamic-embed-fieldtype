<?php

namespace Tests\Fakes;

use Embed\Extractor;
use Embed\Http\FactoryDiscovery;
use Embed\OEmbed;
use Psr\Http\Message\ResponseInterface;

class FakeEmbedExtractor extends Extractor
{
    public function __construct(
        private string $fakeTitle,
        private int $status = 200,
        private ?string $fakeProviderName = null,
    ) {}

    public function __get(string $name)
    {
        return match ($name) {
            'title' => $this->fakeTitle,
            'providerName' => $this->fakeProviderName,
            default => null,
        };
    }

    public function getOEmbed(): OEmbed
    {
        return new FakeEmbedOEmbed;
    }

    public function getResponse(): ResponseInterface
    {
        return FactoryDiscovery::getResponseFactory()->createResponse($this->status);
    }
}
