<?php

namespace App\Services\Newsletter;

use App\DTO\Newsletters\NewsletterContentDTO;
use App\Enums\Newsletters\ContentSourceType;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\Newsletter;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Services\Newsletter\Validation\BlockPayloadValidator;

/**
 * Owns all content persistence rules for newsletters.
 *
 * Enforces the invariant: a newsletter may have block content OR legacy content,
 * never both, never neither (unless automated).
 */
class NewsletterContentService
{
    public function __construct(
        private readonly NewsletterRepository  $newsletterRepository,
        private readonly BlockPayloadValidator $blockPayloadValidator,
        private readonly Logger                $logger,
        private readonly Database              $database,
    )
    {
    }

    /**
     * Persist content for a newsletter from a validated DTO.
     * Wraps all writes in a transaction.
     *
     * @throws \InvalidArgumentException on invalid block payload or mixed state
     * @throws \RuntimeException on newsletter not found
     */
    public function saveContent(int $newsletterId, NewsletterContentDTO $dto): Newsletter
    {
        return $this->database->transaction(function () use ($newsletterId, $dto) {
            $newsletter = $this->newsletterRepository->find($newsletterId);

            if (!$newsletter) {
                throw new \RuntimeException("Newsletter {$newsletterId} not found.");
            }

            $this->guardMixedState($dto);

            if ($dto->isCustomBlocks()) {
                $this->blockPayloadValidator->validate($dto->blocks ?? []);
                $newsletter->content_type = ContentSourceType::CustomBlocks->value;
                $newsletter->content_blocks = $dto->blocks;
                $newsletter->legacy_content = null;
                $newsletter->content = null;
            } elseif ($dto->isLegacy()) {
                $newsletter->content_type = ContentSourceType::Manual->value;
                $newsletter->content_blocks = null;
                $newsletter->legacy_content = $dto->legacyContent;
                $newsletter->content = $dto->legacyContent; // keep old column populated
            } elseif ($dto->isAutomated()) {
                $newsletter->content_type = ContentSourceType::AutoPages->value;
                $newsletter->content_blocks = null;
                $newsletter->legacy_content = null;
            }

            $newsletter->save();

            $this->logger->info('Newsletter content saved', [
                'newsletter_id' => $newsletterId,
                'content_type' => $dto->contentType->value,
            ]);

            return $newsletter->fresh();
        });
    }

    private function guardMixedState(NewsletterContentDTO $dto): void
    {
        if ($dto->blocks !== null && $dto->legacyContent !== null) {
            throw new \InvalidArgumentException(
                'Invalid newsletter content state: blocks and legacy content cannot coexist.'
            );
        }
    }

    /**
     * Migrate a legacy newsletter's content to block format.
     * Does NOT auto-save — returns the converted block array for the UI to confirm.
     */
    public function convertLegacyToBlocks(Newsletter $newsletter): array
    {
        if (!$newsletter->isLegacyContent()) {
            throw new \LogicException("Newsletter {$newsletter->id} is not legacy content.");
        }

        $text = $newsletter->legacy_content ?? $newsletter->content ?? '';

        if (empty(trim($text))) {
            return [];
        }

        // Wrap as a single text block for the builder to render
        return [
            [
                'type' => 'text',
                'data' => ['paragraphs' => [strip_tags($text)]],
            ]
        ];
    }
}