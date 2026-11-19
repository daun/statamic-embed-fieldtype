# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A Statamic CMS addon that provides an `embed` fieldtype for embedding and previewing external content. Uses the [Embed](https://github.com/php-embed/Embed) library to extract oEmbed and Open Graph data from URLs.

## Commands

```bash
# PHP
composer test              # Run tests (Pest)
composer test:coverage     # Run tests with coverage
composer lint              # Check code style (Pint)
composer format            # Fix code style (Pint)
composer analyse           # Static analysis (PHPStan)

# Frontend assets
pnpm install               # Install dependencies
pnpm dev                   # Vite dev server
pnpm build                 # Build production assets
```

## Architecture

### Backend (PHP)

- **ServiceProvider** (`src/ServiceProvider.php`) - Registers the addon with Statamic, configures Vite assets and commands, binds EmbedService as singleton with the configured cache store
- **Embed Fieldtype** (`src/Fieldtypes/Embed.php`) - Fieldtype that holds the URL of the content. Handles augmentation (transforming URL to embed data array) and preloads cached embed info for the control panel
- **EmbedService** (`src/Services/EmbedService.php`) - Core service that fetches and caches embed data using the Embed library. Entries are stored forever as `{data, fetched_at}`; after the TTL they're served stale and refreshed after the response via `defer()`. Failed refreshes keep the previous data. `warm()` fetches pages in parallel via `getMulti()`
- **EmbedUrlFinder** (`src/Services/EmbedUrlFinder.php`) - Collects URLs of embed fields across entries, terms and globals by walking blueprints, including Bard, Replicator, Grid and Group fields. Reads `values()` of every localization so inherited values count; optionally published only
- **EmbedProviders** (`src/Support/EmbedProviders.php`, facade in `src/Facades/`) - Site-wide list of providers in published content with embed counts. Derived from scanning content + cached embed data, never fetches during a request; cached and rebuilt after the response every 5 minutes
- **Commands** (`src/Commands/`) - `statamic:embed:warm` (optionally queued via `src/Jobs/WarmEmbedData.php`), `statamic:embed:clear` (all, single URL or provider) and `statamic:embed:providers`
- **Tags** (`src/Tags/EmbedProviders.php`) - `{{ embed_providers }}` and `{{ embed_providers:slugs }}`. Avoid keys like `count` in tag output; Antlers loop variables override them
- **EmbedResource** (`src/Http/Resources/EmbedResource.php`) - Transforms Embed\Extractor to a normalized array with url, title, description, code (iframe), image, provider, and author data
- **EmbedController** (`src/Http/Controllers/EmbedController.php`) - Single endpoint for fetching embed info via POST to `cp/embed/info`, with optional `refresh`. Accessed by the fieldtype Vue component for live preview
- **ProvidersController** (`src/Http/Controllers/ProvidersController.php`) - Public JSON endpoint `GET /!/statamic-embed-fieldtype/providers` (action route in `routes/actions.php`, prefixed with the addon slug) for cookie banners on statically cached sites

### Caching

- Config in `config/statamic-embed-fieldtype.php`: `cache_store` and `cache_ttl` (30 days, `null` = never stale)
- Default store is a dedicated file store in `storage/statamic/embed` that survives `cache:clear`
- Keys are `statamic-embed:v{CACHE_VERSION}:{md5(url)}` for embed data and `statamic-embed:v{CACHE_VERSION}:providers` for the provider list; bump `EmbedService::CACHE_VERSION` when the shape of `EmbedResource` output changes

### Frontend (Vue/JS)

- **EmbedFieldtype.vue** (`resources/js/components/EmbedFieldtype.vue`) - Control panel fieldtype component. Shows URL input with refresh button and live preview of embedded content (iframe or thumbnail)
- Assets built with Vite + Tailwind CSS to `resources/dist/`

### Testing

- Pest framework with Orchestra Testbench for Laravel/Statamic integration
- Feature tests use `Tests\TestCase` which sets up Statamic environment
- Unit tests use base PHPUnit TestCase
- Test fixtures in `tests/__fixtures__/`, including a `pages` blueprint covering all nested field types and an imported fieldset
- `Tests\Concerns\CreatesEmbedContent` sets up a multisite `pages` collection with real entries saved to a throwaway Stache directory
- Fake Embed library classes in `tests/Fakes/` (queue extractors or exceptions per call, or `FakeEmbed::resolving()` per URL); use `Cache::store('array')` for service tests and `withoutDefer()` to run background refreshes inline
- Don't run `vendor/bin/testbench` commands: they create `vendor/orchestra/testbench-core/laravel/.env` with `CACHE_STORE=database`, which breaks the tests
