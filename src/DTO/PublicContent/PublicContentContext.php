<?php

namespace App\DTO\PublicContent;

use App\Models\Member;
use App\Models\Page;

final readonly class PublicContentContext
{
    public function __construct(
        public Page $page,
        public int $siteId,
        public string $siteSlug,
        public ?Member $member,
        public array $viewData,
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
}
