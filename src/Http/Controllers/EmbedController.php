<?php

namespace Daun\StatamicEmbed\Http\Controllers;

use Daun\StatamicEmbed\Services\EmbedService;
use Illuminate\Http\Request;

class EmbedController
{
    public function __construct(
        protected EmbedService $service
    ) {}

    public function info(Request $request)
    {
        $url = $request->input('url');

        return $this->service->info($url);
    }
}
