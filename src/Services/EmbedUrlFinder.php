<?php

namespace Daun\StatamicEmbed\Services;

use Daun\StatamicEmbed\Fieldtypes\Embed;
use Illuminate\Support\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalVariables;
use Statamic\Facades\Term;
use Statamic\Fields\Fields;
use Statamic\Fieldtypes\Bard;
use Statamic\Fieldtypes\Grid;
use Statamic\Fieldtypes\Group;
use Statamic\Fieldtypes\Replicator;

/**
 * Collects the URLs of all embed fields across entries, terms and globals.
 */
class EmbedUrlFinder
{
    /**
     * @return Collection<int, string>
     */
    public function all(): Collection
    {
        return collect()
            ->concat(Entry::all())
            ->concat(Term::all())
            ->concat(GlobalVariables::all())
            ->flatMap(fn ($item) => iterator_to_array($this->walk(
                $item->blueprint()?->fields(),
                collect($item->data())->all(),
            ), false))
            ->unique()
            ->values();
    }

    protected function walk(?Fields $fields, mixed $data): \Generator
    {
        if (! $fields || ! is_array($data)) {
            return;
        }

        foreach ($fields->all() as $handle => $field) {
            $value = $data[$handle] ?? null;
            $fieldtype = $field->fieldtype();

            if (! $value) {
                continue;
            }

            if ($fieldtype instanceof Embed) {
                if (is_string($value)) {
                    yield $value;
                }
            } elseif ($fieldtype instanceof Bard) {
                foreach ((array) $value as $node) {
                    $values = $node['attrs']['values'] ?? null;
                    if (($node['type'] ?? null) === 'set' && isset($values['type'])) {
                        yield from $this->walk($fieldtype->fields($values['type']), $values);
                    }
                }
            } elseif ($fieldtype instanceof Replicator) {
                foreach ((array) $value as $set) {
                    if (isset($set['type'])) {
                        yield from $this->walk($fieldtype->fields($set['type']), $set);
                    }
                }
            } elseif ($fieldtype instanceof Grid) {
                foreach ((array) $value as $row) {
                    yield from $this->walk($fieldtype->fields(), $row);
                }
            } elseif ($fieldtype instanceof Group) {
                yield from $this->walk($fieldtype->fields(), $value);
            }
        }
    }
}
