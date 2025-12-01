<?php

namespace Daun\StatamicEmbed\Http\Resources;

use Embed\Extractor;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class EmbedResource extends JsonResource
{
    /**
     * The resource instance.
     *
     * @var Extractor
     */
    public $resource;

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
            ...$this->composeEmbed(),
            ...$this->composeThumbnail(),
            ...$this->composeProvider(),
            ...$this->composeAuthor(),
        ];
    }

    protected function composeProvider(): array
    {
        $name = $this->resource->providerName ?? null;
        $url = $this->resource->providerUrl ?? null;

        return [
            'provider' => [
                'slug' => $name ? Str::slug($name) : $name,
                'name' => $name,
                'url' => $url,
            ],
        ];
    }

    protected function composeAuthor(): array
    {
        $name = $this->resource->authorName ?? null;
        $url = $this->resource->authorUrl ?? null;

        return [
            'author' => $name ? [
                'name' => $name,
                'url' => $url,
            ] : null,
        ];
    }

    protected function composeEmbed(): array
    {
        $oembed = $this->resource->getOEmbed()->all();

        $type = $oembed['type'] ?? null;
        $html = $this->resource->code?->html ?? null;
        $width = $this->resource->code?->width ?? null;
        $height = $this->resource->code?->height ?? null;
        $ratio = $width && $height ? $width / $height : 0;
        $orientation = $ratio ? ($ratio >= 1 ? 'landscape' : 'portrait') : null;

        return [
            'embed' => $html ? [
                'type' => $type,
                'html' => $html,
                'width' => $width,
                'height' => $height,
                'ratio' => $ratio,
                'orientation' => $orientation,
            ] : null,
        ];
    }

    protected function composeThumbnail(): array
    {
        $oembed = $this->resource->getOEmbed()->all();

        $image = $this->resource->image
            ? ((string) $this->resource->image)
            : ($oembed['thumbnail_url'] ?? null);

        $width = $oembed['thumbnail_width'] ?? null;
        $height = $oembed['thumbnail_height'] ?? null;
        $ratio = $width && $height ? $width / $height : 0;
        $orientation = $ratio ? ($ratio >= 1 ? 'landscape' : 'portrait') : null;

        return [
            'thumbnail' => $image ? [
                'url' => $image,
                'width' => $width,
                'height' => $height,
                'ratio' => $ratio,
                'orientation' => $orientation,
            ] : null,
        ];
    }
}
