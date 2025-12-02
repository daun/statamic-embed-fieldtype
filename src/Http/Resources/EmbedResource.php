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
     * @param  \Illuminate\Http\Request  $request
     */
    public function toArray($request): array
    {
        return [
            'url' => $this->resource->url ?? null,
            'title' => $this->resource->title ?? null,
            'description' => $this->resource->description ?? null,
            'language' => $this->resource->language ?? null,
            'code' => $this->code(),
            'image' => $this->image(),
            'provider' => $this->provider(),
            'author' => $this->author(),
        ];
    }

    protected function provider(): ?array
    {
        $name = $this->resource->providerName ?? null;
        $slug = $name ? Str::slug($name) : null;
        $url = $this->resource->providerUrl ?? null;

        return ($name || $url) ? [
            'name' => $name,
            'slug' => $slug,
            'url' => $this->resource->providerUrl ?? null,
        ] : null;
    }

    protected function author(): ?array
    {
        $name = $this->resource->authorName ?? null;
        $url = $this->resource->authorUrl ?? null;

        return $name ? [
            'name' => $name,
            'url' => $url,
        ] : null;
    }

    protected function code(): ?array
    {
        $oembed = $this->resource->getOEmbed()->all();

        $type = $oembed['type'] ?? null;
        $html = $this->resource->code?->html ?? null;

        if (! $html) {
            return null;
        }

        $width = $this->resource->code?->width ?? null;
        $height = $this->resource->code?->height ?? null;

        $iframeWidth = preg_match('/width=["\']?(\d+)["\']?/i', $html, $matches) ? (int) $matches[1] : null;
        $iframeHeight = preg_match('/height=["\']?(\d+)["\']?/i', $html, $matches) ? (int) $matches[1] : null;
        $maxWidth = preg_match('/style=["\'][^"\']*\bmax-width:\s*(\d+)px\b[^"\']*["\']/i', $html, $matches) ? (int) $matches[1] : null;
        $borderRadius = preg_match('/style=["\'][^"\']*\bborder-radius:\s*(\d+)px\b[^"\']*["\']/i', $html, $matches) ? (int) $matches[1] : null;

        // Special case: some providers return a height from oEmbed, but the iframe doesn't have it set
        if ($iframeHeight) {
            $height = $iframeHeight;
            if (! $iframeWidth) {
                $width = null;
            }
        }

        $ratio = $width && $height ? $width / $height : 0;
        $orientation = $ratio ? ($ratio >= 1 ? 'landscape' : 'portrait') : null;

        return [
            'type' => $type,
            'html' => $html,
            'width' => $width,
            'maxWidth' => $maxWidth,
            'height' => $height,
            'borderRadius' => $borderRadius,
            'ratio' => $ratio,
            'orientation' => $orientation,
        ];
    }

    protected function image(): ?array
    {
        $oembed = $this->resource->getOEmbed()->all();

        $url = $this->resource->image
            ? ((string) $this->resource->image)
            : ($oembed['thumbnail_url'] ?? null);

        if (! $url) {
            return null;
        }

        $width = $oembed['thumbnail_width'] ?? null;
        $height = $oembed['thumbnail_height'] ?? null;
        $ratio = $width && $height ? $width / $height : 0;
        $orientation = $ratio ? ($ratio >= 1 ? 'landscape' : 'portrait') : null;

        return [
            'url' => $url,
            'width' => $width,
            'height' => $height,
            'ratio' => $ratio,
            'orientation' => $orientation,
        ];
    }
}
