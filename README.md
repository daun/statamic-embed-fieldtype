# Statamic Embed Fieldtype

Fieldtype for embedding and previewing external content.

![Example embed field](art/embed-field.png)

## Installation

Install the addon via Composer:

```bash
composer require daun/statamic-embed-fieldtype
```

## Fieldtype

The addon ships with a fieldtype that accepts a url and shows a preview in the editor.

```yaml
embed:
  display: 'External content'
  type: embed
```

## License

[MIT](https://opensource.org/licenses/MIT)
