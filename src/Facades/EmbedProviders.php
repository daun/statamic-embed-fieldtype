<?php

namespace Daun\StatamicEmbed\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Support\Collection all()
 * @method static string[] slugs()
 * @method static array build(bool $fetch = true)
 * @method static void invalidate()
 * @method static void forget()
 *
 * @see \Daun\StatamicEmbed\Support\EmbedProviders
 */
class EmbedProviders extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Daun\StatamicEmbed\Support\EmbedProviders::class;
    }
}
