<?php

namespace Tests\Fakes;

use Embed\OEmbed;

class FakeEmbedOEmbed extends OEmbed
{
    public function __construct() {}

    public function all(): array
    {
        return [];
    }
}
