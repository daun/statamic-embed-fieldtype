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

- **ServiceProvider** (`src/ServiceProvider.php`) - Registers the addon with Statamic, configures Vite assets, binds EmbedService as singleton
- **Embed Fieldtype** (`src/Fieldtypes/Embed.php`) - Fieldtype that holds the URL of the content. Handles augmentation (transforming URL to embed data array) and preloads cached embed info for the control panel
- **EmbedService** (`src/Services/EmbedService.php`) - Core service that fetches and caches embed data (7-day TTL). Uses the Embed library to extract metadata from URLs
- **EmbedResource** (`src/Http/Resources/EmbedResource.php`) - Transforms Embed\Extractor to a normalized array with url, title, description, code (iframe), image, provider, and author data
- **EmbedController** (`src/Http/Controllers/EmbedController.php`) - Single endpoint for fetching embed info via POST to `cp/embed/info`. Accessed by the fieldtype Vue component for live preview

### Frontend (Vue/JS)

- **EmbedFieldtype.vue** (`resources/js/components/EmbedFieldtype.vue`) - Control panel fieldtype component. Shows URL input with live preview of embedded content (iframe or thumbnail)
- Assets built with Vite + Tailwind CSS to `resources/dist/`

### Testing

- Pest framework with Orchestra Testbench for Laravel/Statamic integration
- Feature tests use `Tests\TestCase` which sets up Statamic environment
- Unit tests use base PHPUnit TestCase
- Test fixtures in `tests/__fixtures__/`
