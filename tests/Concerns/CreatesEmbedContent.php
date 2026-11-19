<?php

namespace Tests\Concerns;

use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Fieldset;
use Statamic\Facades\Site;

trait CreatesEmbedContent
{
    protected function setUpEmbedContent(): void
    {
        config(['statamic.system.multisite' => true]);
        Site::setSites([
            'en' => ['name' => 'English', 'url' => '/', 'locale' => 'en_US'],
            'de' => ['name' => 'German', 'url' => '/de/', 'locale' => 'de_DE'],
        ]);

        Blueprint::setDirectory(fixtures_path('blueprints'));
        Fieldset::setDirectory(fixtures_path('fieldsets'));

        $this->preventSavingStacheItemsToDisk();

        Collection::make('pages')->sites(['en', 'de'])->save();
    }

    protected function tearDownEmbedContent(): void
    {
        $this->deleteFakeStacheDirectory();
    }

    protected function createPage(array $data, bool $published = true, ?string $site = 'en', mixed $origin = null, bool $quietly = false)
    {
        $entry = Entry::make()
            ->collection('pages')
            ->locale($site)
            ->published($published)
            ->data($data);

        if ($origin) {
            $entry->origin($origin);
        }

        $quietly ? $entry->saveQuietly() : $entry->save();

        return $entry;
    }
}
