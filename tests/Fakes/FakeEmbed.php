<?php

namespace Tests\Fakes;

use Embed\Embed;
use Embed\Extractor;
use RuntimeException;
use Throwable;

class FakeEmbed extends Embed
{
    /** @var array<int, Extractor|Throwable> */
    public array $results = [];

    public int $calls = 0;

    public int $multiCalls = 0;

    public bool $failMulti = false;

    public function __construct(Extractor|Throwable ...$results)
    {
        $this->results = $results;
    }

    public function get(string $url): Extractor
    {
        $result = $this->results[$this->calls++];

        return $result instanceof Throwable ? throw $result : $result;
    }

    public function getMulti(string ...$urls): array
    {
        $this->multiCalls++;

        if ($this->failMulti) {
            throw new RuntimeException('Network error');
        }

        return array_map(fn () => $this->get(''), $urls);
    }
}
