<?php

namespace Daun\StatamicEmbed\Http\Controllers;

use Daun\StatamicEmbed\Support\EmbedProviders;
use Illuminate\Http\JsonResponse;

class ProvidersController
{
    public function __invoke(EmbedProviders $providers): JsonResponse
    {
        abort_unless(config('statamic-embed-fieldtype.providers_route'), 404);

        $all = $providers->all();

        return response()
            ->json(['providers' => $all, 'slugs' => $all->pluck('slug')])
            ->setPublic()
            ->setMaxAge(1800);
    }
}
