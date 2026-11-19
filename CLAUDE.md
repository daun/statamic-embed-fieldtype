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
- **EmbedUrlFinder** (`src/Services/EmbedUrlFinder.php`) - Collects URLs of embed fields across entries, terms and globals by walking blueprints, including Bard, Replicator, Grid and Group fields
- **Commands** (`src/Commands/`) - `statamic:embed:warm` (optionally queued via `src/Jobs/WarmEmbedData.php`) and `statamic:embed:clear` (all, single URL or provider)
- **EmbedResource** (`src/Http/Resources/EmbedResource.php`) - Transforms Embed\Extractor to a normalized array with url, title, description, code (iframe), image, provider, and author data
- **EmbedController** (`src/Http/Controllers/EmbedController.php`) - Single endpoint for fetching embed info via POST to `cp/embed/info`, with optional `refresh`. Accessed by the fieldtype Vue component for live preview

### Caching

- Config in `config/statamic-embed-fieldtype.php`: `cache_store` and `cache_ttl` (30 days, `null` = never stale)
- Default store is a dedicated file store in `storage/statamic/embed` that survives `cache:clear`
- Keys are `statamic-embed:v{CACHE_VERSION}:{md5(url)}`; bump `EmbedService::CACHE_VERSION` when the shape of `EmbedResource` output changes

### Frontend (Vue/JS)

- **EmbedFieldtype.vue** (`resources/js/components/EmbedFieldtype.vue`) - Control panel fieldtype component. Shows URL input with refresh button and live preview of embedded content (iframe or thumbnail)
- Assets built with Vite + Tailwind CSS to `resources/dist/`

### Testing

- Pest framework with Orchestra Testbench for Laravel/Statamic integration
- Feature tests use `Tests\TestCase` which sets up Statamic environment
- Unit tests use base PHPUnit TestCase
- Test fixtures in `tests/__fixtures__/`
- Fake Embed library classes in `tests/Fakes/` (queue extractors or exceptions per call); use `Cache::store('array')` for service tests and `withoutDefer()` to run background refreshes inline
- Don't run `vendor/bin/testbench` commands: they create `vendor/orchestra/testbench-core/laravel/.env` with `CACHE_STORE=database`, which breaks the tests
