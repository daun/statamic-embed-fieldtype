# Statamic Embed Fieldtype

**Fieldtype for embedding and previewing external content.**

## Installation

Run the below command from your project root. Alternatively, you can search for this addon in the
`Tools > Addons` section of the Statamic control panel and install it from there.

```sh
composer require daun/statamic-embed-fieldtype
```

## Add an Embed Field

The addon ships with an `embed` fieldtype that accepts a url and shows a preview in the editor.

```yaml
fields:
  -
    handle: embed
    field:
      type: embed
      display: Embed
```

## Display Embeds in Your Frontend

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
Editors can force a refresh using the refresh button in the field.

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

# List embed providers used on the site
php please embed:providers
php please embed:providers --rebuild
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

## Consent

Embeds from third-party providers usually set cookies and might require consent. The embed data
contains its provider at `embed:provider`, and the `embed_providers` tag lists all providers used
across the site. Combine them to build a cookie banner that gates embeds per provider, here using
[@grrr/cookie-consent](https://github.com/grrr-amsterdam/cookie-consent).

```html
{{ if embed:code }}
  <template data-embed-provider="{{ embed:provider:slug }}">{{ embed:code:html }}</template>
  <p data-cookie-consent-rejected="{{ embed:provider:slug }}">Allow {{ embed:provider:name }} to view this content.</p>
{{ /if }}

<cookie-consent data-cookies='[{{ embed_providers }}{"id": "{{ slug }}", "label": "{{ name }}"}{{ unless last }},{{ /unless }}{{ /embed_providers }}]'></cookie-consent>
```

```js
import CookieConsent from '@grrr/cookie-consent';

customElements.define('cookie-consent', CookieConsent);

const consent = document.querySelector('cookie-consent');
const loadEmbeds = (preferences) => preferences
  .filter(({ accepted }) => accepted)
  .forEach(({ id }) => document.querySelectorAll(`template[data-embed-provider="${id}"]`)
    .forEach((template) => template.replaceWith(template.content)));

loadEmbeds(consent.preferences.getAll());
consent.on('update', loadEmbeds);
```

On statically cached sites, load the list from `/!/statamic-embed-fieldtype/providers` instead.
