<?php

namespace App\Search\Configurations;

use App\Search\Filters\CustomFilter;
use App\Search\Filters\EqualsFilter;
use App\Search\Filters\LikeFilter;
use App\Search\HasSite;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class ImageSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    use HasSite;

    public function configure(): void
    {
        // Filters
        $this->addFilter(new LikeFilter('query', 'filename'))
            ->addFilter(new EqualsFilter('mime_type', 'mime_type'))
            ->addFilter(new EqualsFilter('category_id', 'category_id'))
            ->addFilter(new CustomFilter('tags', function($query, $value) {
                // Value can be comma-separated tag IDs
                $tagIds = is_array($value) ? $value : explode(',', $value);
                $tagIds = array_filter(array_map('intval', $tagIds));

                if (!empty($tagIds)) {
                    $query->whereHas('tags', function($q) use ($tagIds) {
                        $q->whereIn('tag_id', $tagIds);
                    });
                }
                return $query;
            }));;

        self::applySiteFilter();

        // Sorts
        $this->addSort(new SortSpecification('created_at', 'created_at'))
            ->addSort(new SortSpecification('original_name', 'original_name'))
            ->addSort(new SortSpecification('file_size', 'file_size'))
            ->addSort(new SortSpecification('updated_at', 'updated_at'));

        // Searchable columns
        $this->addSearchableColumn('filename')
            ->addSearchableColumn('alt_text')
            ->addSearchableColumn('caption')
            ->addSearchableColumn('name')
            ->addSearchableColumn('credit');

        // Default sort
        $this->setDefaultSort('created_at', 'desc');
    }
}