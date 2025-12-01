# Statamic Embed Fieldtype

Fieldtype for embedding and previewing external content.

Fetches oEmbed data and
shows rich previews in the control panel.

![Example embed field](art/field-soundcloud.png)

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

The fieldtype augments the url to an array of embed data that can be used in templates.

```html
{{ if embed:url }}
<article class="border rounded-lg overflow-hidden">
  {{ if embed:embed }}
    <div
      class="relative aspect-(--embed-ratio) overflow-hidden [&_iframe]:w-full [&_iframe]:h-full [&_iframe]:border-0"
      style="--embed-ratio: {{ embed:embed:ratio }}"
    >
      {{ embed:embed:html }}
    </div>
  {{ elseif embed:thumbnail }}
    <img
      src="{{ embed:thumbnail:url }}"
      class="w-full h-auto"
    />
  {{ /if }}
  {{ if embed:title }}
    <div class="px-4">
      <p class="line-clamp-1 font-semibold">
          <a href="{{ embed:url }}">{{ embed:title }}</a>
      </p>
      {{ if embed:description || embed:author:name }}
        <p class="line-clamp-1">{{ embed:description ?? embed:author:name }}</p>
      {{ /if }}
      {{ if embed:provider:url }}
        <p class="text-gray-500">{{ embed:provider:url | replace('https://', '') }}</p>
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

## License

[MIT](https://opensource.org/licenses/MIT)
