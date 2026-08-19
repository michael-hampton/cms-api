<?php

namespace App\DTO\PublicContent;

use App\Models\Member;
use App\Models\Page;

final readonly class PublicContentContext
{
    /**
     * @param list<string> $pageTypeOverrideKeys
     */
    public function __construct(
        public Page $page,
        public int $siteId,
        public string $siteSlug,
        public ?Member $member = null,
        public array $viewData = [],
        public array $pageTypeOverrideKeys = [],
    ) {
    }

    public function with(array $data): array
    {
        return array_merge($this->viewData, $data, [
            'page' => $this->page,
            'member' => $this->member,
            'site' => $this->siteSlug,
        ]);
    }

    public function overridesArticleTypeFor(string $widgetKey): bool
    {
        return in_array($widgetKey, $this->pageTypeOverrideKeys, true);
    }

    /**
     * @param list<string> $keys
     */
    public function withPageTypeOverrideKeys(array $keys): self
    {
        return new self(
            $this->page,
            $this->siteId,
            $this->siteSlug,
            $this->member,
            $this->viewData,
            array_values($keys),
        );
    }
}
