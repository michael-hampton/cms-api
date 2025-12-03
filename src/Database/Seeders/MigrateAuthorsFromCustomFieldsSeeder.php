<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\Author;
use App\Models\CustomFieldDefinition;
use App\Models\Page;
use App\Models\PageAuthor;
use App\Models\PageCustomField;
use App\Models\Site;

class MigrateAuthorsFromCustomFieldsSeeder extends Seeder
{
    private array $authorCache = [];

    public function run(): void
    {
        echo "Starting author migration from custom fields...\n";

        // Get all sites
        $sites = Site::all();

        foreach ($sites as $site) {
            echo "\nProcessing site: {$site->name} (ID: {$site->id})\n";
            $this->processSite($site);
        }

        echo "\n✓ Migration complete!\n";
        echo "Total authors created: " . count($this->authorCache) . "\n";
    }

    private function processSite(Site $site): void
    {
        // Get custom field definitions for this site
        $authorNameField = CustomFieldDefinition::where('site_id', $site->id)
            ->where('key', 'author_name')
            ->first();

        $authorBioField = CustomFieldDefinition::where('site_id', $site->id)
            ->where('key', 'author_bio')
            ->first();

        $authorImageField = CustomFieldDefinition::where('site_id', $site->id)
            ->where('key', 'author_image')
            ->first();

        if (!$authorNameField) {
            echo "  No author_name field found for this site, skipping...\n";
            return;
        }

        // Get all pages for this site that have author custom fields
        $pages = Page::where('site_id', $site->id)
            ->whereHas('customFields', function ($query) use ($authorNameField) {
                $query->where('custom_field_definition_id', $authorNameField->id);
            })
            ->get();

        echo "  Found {$pages->count()} pages with author data\n";

        foreach ($pages as $page) {
            $this->processPage($page, $authorNameField, $authorBioField, $authorImageField, $site);
        }
    }

    private function processPage(
        Page                   $page,
        CustomFieldDefinition  $authorNameField,
        ?CustomFieldDefinition $authorBioField,
        ?CustomFieldDefinition $authorImageField,
        Site                   $site
    ): void
    {
        // Get author custom field values
        $authorName = PageCustomField::where('page_id', $page->id)
            ->where('custom_field_definition_id', $authorNameField->id)
            ->value('field_value');

        if (!$authorName) {
            return;
        }

        $authorBio = null;
        if ($authorBioField) {
            $authorBio = PageCustomField::where('page_id', $page->id)
                ->where('custom_field_definition_id', $authorBioField->id)
                ->value('field_value');
        }

        $authorImage = null;
        if ($authorImageField) {
            $authorImage = PageCustomField::where('page_id', $page->id)
                ->where('custom_field_definition_id', $authorImageField->id)
                ->value('field_value');
        }

        // Create or get author
        $author = $this->getOrCreateAuthor([
            'name' => $authorName,
            'bio' => $authorBio,
            'image' => $authorImage,
            'site_id' => $site->id
        ]);

        // Check if PageAuthor relationship already exists
        $existingRelation = PageAuthor::where('page_id', $page->id)
            ->where('author_id', $author->id)
            ->first();

        if (!$existingRelation) {
            PageAuthor::create([
                'page_id' => $page->id,
                'author_id' => $author->id
            ]);
            echo "    ✓ Linked author '{$authorName}' to page '{$page->title}'\n";
        } else {
            echo "    - Author '{$authorName}' already linked to page '{$page->title}'\n";
        }
    }

    private function getOrCreateAuthor(array $data): Author
    {
        // Create cache key based on name and site
        $cacheKey = $data['site_id'] . '_' . $this->slugify($data['name']);

        // Check cache first
        if (isset($this->authorCache[$cacheKey])) {
            return $this->authorCache[$cacheKey];
        }

        // Check if author exists in database
        $author = Author::where('site_id', $data['site_id'])
            ->where('slug', $this->slugify($data['name']))
            ->first();

        if ($author) {
            // Update bio and image if they're empty and we have data
            $updated = false;

            if (empty($author->bio) && !empty($data['bio'])) {
                $author->bio = $data['bio'];
                $updated = true;
            }

            if (empty($author->image) && !empty($data['image'])) {
                $author->image = $data['image'];
                $updated = true;
            }

            if ($updated) {
                $author->save();
                echo "    ↻ Updated author '{$data['name']}'\n";
            }

            $this->authorCache[$cacheKey] = $author;
            return $author;
        }

        // Create new author
        $author = Author::create([
            'name' => $data['name'],
            'slug' => $this->slugify($data['name']),
            'bio' => $data['bio'],
            'avatar' => $data['image'],
            'site_id' => $data['site_id']
        ]);

        echo "    + Created new author '{$data['name']}'\n";

        $this->authorCache[$cacheKey] = $author;
        return $author;
    }

    private function slugify(string $text): string
    {
        // Convert to lowercase
        $text = strtolower($text);

        // Replace non-alphanumeric characters with hyphens
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);

        // Remove leading/trailing hyphens
        $text = trim($text, '-');

        return $text;
    }

    public function rollback(): void
    {
        echo "Rolling back author migration...\n";

        // Get all sites
        $sites = Site::all();

        foreach ($sites as $site) {
            echo "Processing site: {$site->name}\n";

            // Delete all PageAuthor relationships for this site
            $deletedRelations = PageAuthor::whereIn('page_id', function ($query) use ($site) {
                $query->select('id')
                    ->from('pages')
                    ->where('site_id', $site->id);
            })
                ->delete();

            echo "  Deleted {$deletedRelations} page-author relationships\n";

            // Delete all authors for this site
            $deletedAuthors = Author::where('site_id', $site->id)->delete();
            echo "  Deleted {$deletedAuthors} authors\n";
        }

        echo "✓ Rollback complete!\n";
    }
}