<?php

namespace App\Services\PublicContent;

use App\DTO\PublicContent\InitialPublicContentHero;
use App\Models\Page;
use App\Parsers\BlockFactory;
use App\Parsers\Dtos\HeroBlockDto;
use App\Parsers\Renderers\HeroBlockRenderer;
use App\Repositories\Cms\BlockRepository;
use Throwable;

final readonly class InitialPublicContentHeroResolver
{
    public function __construct(
        private BlockRepository $blocks,
        private BlockFactory $blockFactory,
        private HeroBlockRenderer $renderer,
    ) {
    }

    public function resolve(Page $page): ?InitialPublicContentHero
    {
        foreach ($this->blocks->getPageBlocks((int) $page->id) as $block) {
            $data = is_array($block->data)
                ? $block->data
                : (json_decode((string) $block->data, true) ?: []);

            if (($data['context'] ?? 'default') === 'sidebar') {
                continue;
            }

            if ((string) $block->type !== 'hero') {
                return null;
            }

            try {
                $dto = $this->blockFactory->make(array_merge($data, ['type' => $block->type]));
            } catch (Throwable) {
                return null;
            }

            if (!$dto instanceof HeroBlockDto) {
                return null;
            }

            return new InitialPublicContentHero(
                blockId: (int) $block->id,
                html: $this->renderer->render($dto, (int) $page->id),
                preloadUrl: $this->preloadUrl($dto),
            );
        }

        return null;
    }

    private function preloadUrl(HeroBlockDto $dto): ?string
    {
        $backgroundImage = trim((string) $dto->backgroundImage);

        return $backgroundImage !== '' ? $backgroundImage : null;
    }
}
