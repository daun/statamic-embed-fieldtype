# Statamic Embed Fieldtype

Fieldtype for embedding and previewing external content.

<img src="art/field-podcast.png" alt="Example embed field" width="600">

## Features

- Augment URLs with embed data: iframe, thumbnail, title, description
- Live preview of embedded content in the control panel
- Powered by the [Embed](https://github.com/php-embed/Embed) library and extensible with custom adapters
- Supports any provider with oEmbed endpoint or Open Graph meta tags
- Site-wide list of embed providers for cookie banners and consent gates
- Persistent cache with background refresh and console commands for warming

## Vs. the Built-In Video Fieldtype

If you need to embed videos from YouTube and Vimeo, you should probably stick with Statamic's
built-in [video fieldtype](https://statamic.dev/fieldtypes/video). It comes with less complexity
as it doesn't need to fetch the metadata or embed codes.

The fieldtype of this addon becomes useful if you need:

- Other providers and content types like audio players, social media posts, etc
- Ready-to-render html for embedding the content in an iframe
- Additional metadata like title, description, thumbnail, etc

## Quick Start

1. Install using `composer require daun/statamic-embed-fieldtype`
2. Add an `embed` field to your blueprint
3. Render the embed code using `{{ embed:code:html }}` in your views

## Documentation

Read the [documentation](./DOCUMENTATION.md) for a detailed guide to rendering embeds, gating them
behind consent, caching and extending the addon.

## License

[MIT](https://opensource.org/licenses/MIT)
