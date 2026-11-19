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

    /** @var (\Closure(string): Extractor)|null */
    public ?\Closure $resolver = null;

    public static function resolving(\Closure $resolver): static
    {
        $embed = new static;
        $embed->resolver = $resolver;

        return $embed;
    }

    public function __construct(Extractor|Throwable ...$results)
    {
        $this->results = $results;
    }

    public function get(string $url): Extractor
    {
        $this->calls++;
        $result = $this->resolver ? ($this->resolver)($url) : $this->results[$this->calls - 1];

        return $result instanceof Throwable ? throw $result : $result;
    }

    public function getMulti(string ...$urls): array
    {
        $this->multiCalls++;

        if ($this->failMulti) {
            throw new RuntimeException('Network error');
        }

        return array_map(fn ($url) => $this->get($url), $urls);
    }
}
