# Statamic Embed Fieldtype

Fieldtype for embedding and previewing external content.

<img src="art/field-podcast.png" alt="Example embed field" width="600">

## Features

- Augment URLs with embed data: iframe, thumbnail, title, description
- Live preview of embedded content in the control panel
- Powered by the [Embed](https://github.com/php-embed/Embed) library and extensible with custom adapters
- Supports any provider with oEmbed endpoint or Open Graph meta tags

## Vs. the Built-In Video Fieldtype

If you need to embed videos from YouTube and Vimeo, you should probably stick with Statamic's
built-in [video fieldtype](https://statamic.dev/fieldtypes/video). It comes with less complexity
as it doesn't need to fetch the metadata or embed codes.

The fieldtype of this addon becomes useful if you need:

- Other providers and content types like audio players, social media posts, etc
- Ready-to-render html for embedding the content in an iframe
- Additional metadata like title, description, thumbnail, etc

## Installation

Install the addon via Composer:

```bash
composer require daun/statamic-embed-fieldtype
```

## Fieldtype

The addon ships with an `embed` fieldtype that accepts a url and shows a preview in the editor.

```yaml
fields:
  -
    handle: embed
    field:
      type: embed
      display: Embed
```

## Frontend

The fieldtype augments the url to an array of embed data. The following example
will render a simple embed card with either an iframe (if available) or a preview
image.

```html
{{ if embed:url }}
  <article class="border rounded-lg overflow-hidden">
    {{ if embed:code }}
      <div
        class="relative aspect-(--embed-ratio) overflow-hidden [&_iframe]:w-full [&_iframe]:h-full [&_iframe]:border-0"
        style="--embed-ratio: {{ embed:code:ratio }}"
      >
        {{ embed:code:html }}
      </div>
    {{ elseif embed:image }}
      <img
        src="{{ embed:image:url }}"
        class="w-full h-auto"
      />
    {{ /if }}
    {{ if embed:title }}
      <div class="p-4">
        <p class="line-clamp-1 font-semibold">
            <a href="{{ embed:url }}">{{ embed:title }}</a>
        </p>
        {{ if embed:description || embed:author:name }}
          <p class="line-clamp-1">{{ embed:description ?? embed:author:name }}</p>
        {{ /if }}
        {{ if embed:provider:url }}
          <p class="text-gray-500">{{ embed:provider:url | replace('https://', '') }}</p>
        {{ /if }}
      </div>
    {{ /if }}
  </article>
{{ /if }}
```

### Unaugmented URL

If you want to use the raw url without augmentation, you can access it via `embed:url`. To turn off
augmentation completely, you can set the field config `augment_to_embed_data` to `false`. This will
return the url as-is in all frontend contexts.

```diff
fields:
  -
    handle: embed
    field:
      type: embed
      display: Embed
+     augment_to_embed_data: false
```

## Configuration

Optionally publish the config file to customize the addon:

```bash
php artisan vendor:publish --tag=statamic-embed-fieldtype-config
```

| Option        | Default   | Description                                                    |
| ------------- | --------- | -------------------------------------------------------------- |
| `cache_store` | `null`    | Cache store name, or `null` for a file store in `storage/statamic/embed` |
| `cache_ttl`   | `2592000` | Seconds until embed data is refreshed (30 days), `null` for never |

## Caching

Embed data is cached in a dedicated store that survives `php artisan cache:clear`, so deployments
don't trigger refetching all embeds. If your storage directory isn't persisted between deployments,
set `cache_store` to a store from `config/cache.php`, e.g. `redis` or `database`.

Once the TTL is over, the cached data is still served and refreshed in the background after the
response. If the refresh fails, e.g. because a video was taken down, the previous data is kept.

## Commands

```bash
# Fetch embed data for all embed fields in entries, terms and globals
php please embed:warm
php please embed:warm --queue   # dispatch queued jobs
php please embed:warm --force   # refetch fresh data as well

# Clear cached embed data
php please embed:clear
php please embed:clear https://youtube.com/watch?v=xyz
php please embed:clear --provider=youtube   # provider name or hostname
```

## Extending the Embed Library

The addon uses the [Embed](https://github.com/php-embed/Embed) library under the hood, which supports
a variety of providers out of the box: YouTube, Vimeo, Instagram, Flickr, etc. You can customize the
library instance and settings and also create custom adapters for unsupported providers by registering
a resolving callback in your service provider. See [Extending Embed](https://github.com/php-embed/Embed?tab=readme-ov-file#extending-embed)
for details and examples.

```php
namespace App\Providers;

use Embed\Embed;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->afterResolving(Embed::class, function (Embed $embed) {
            $embed->getExtractorFactory()->addAdapter('mysite.com', MySite::class);
            $embed->setSettings([
                'instagram:token' => '12345678',
                'twitter:token' => 'abcdefgh',
            ]);
        });
    }
}
```

## License

[MIT](https://opensource.org/licenses/MIT)
