<?php

namespace Daun\StatamicEmbed\Http\Resources;

use Embed\Extractor;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class EmbedResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     */
    public function toArray($request): array
    {
        return [
            'url' => $this->resource->url ?? null,
            'title' => $this->resource->title ?? null,
            'description' => $this->resource->description ?? null,
            'language' => $this->resource->language ?? null,
            ...$this->composeProvider(),
            ...$this->composeAuthor(),
            ...$this->composeDimensions(),
            ...$this->composePreview(),
            ...$this->composeEmbed(),
        ];
    }

    protected function composeProvider(): ?array
    {
        $name = $this->resource->providerName ?? null;
        $url = $this->resource->providerUrl ?? null;

        return [
            'provider' => $name ? Str::slug($name) : $name,
            'provider_name' => $name,
            'provider_url' => $url,
        ];
    }

    protected function composeAuthor(): ?array
    {
        return [
            'author_name' => $this->resource->authorName ?? null,
            'author_url' => $this->resource->authorUrl ?? null,
        ];
    }

    protected function composeDimensions(): ?array
    {
        $width = $this->resource->code?->width;
        $height = $this->resource->code?->height;
        $ratio = $width && $height ? $width / $height : 0;
        $orientation = $ratio ? ($ratio >= 1 ? 'landscape' : 'portrait') : null;

        return [
            'width' => $width,
            'height' => $height,
            'ratio' => $ratio,
            'orientation' => $orientation,
        ];
    }

    protected function composePreview(): ?array
    {
        $image = $this->resource->image
            ? ((string) $this->resource->image)
            : null;

        return [
            'image' => $image,
        ];
    }

    protected function composeEmbed(): ?array
    {
        $html = $this->resource->code?->html ?? null;

        return [
            'html' => $html,
        ];
    }
}
