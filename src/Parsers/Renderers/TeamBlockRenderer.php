<?php

namespace App\Parsers\Renderers;

use App\Parsers\BlockFactory;
use App\Parsers\BlockRendererManager;
use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\PersonBlockDto;
use App\Parsers\Dtos\TeamBlockDto;

class TeamBlockRenderer extends BaseBlockRenderer
{
    private BlockFactory $blockFactory;
    private BlockRendererManager $blockRendererManager;

    public function __construct(
        ?BlockFactory         $blockFactory = null,
        ?BlockRendererManager $blockRendererManager = null
    )
    {
        $this->blockFactory = $blockFactory ?? new BlockFactory();
        $this->blockRendererManager = $blockRendererManager ?? new BlockRendererManager();
    }
    protected function getSupportedType(): string
    {
        return 'team';
    }

    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof TeamBlockDto) {
            return '';
        }

        $memberCount = count($dto->members);
        $showCarousel = $memberCount > 3;

        $html = "<section class=\"team-block team-layout-{$dto->layout}\">";
        $html .= "<div class=\"container\">";

        $html .= "<div class=\"team-header\">";
        $html .= "<h2>{$this->escape($dto->title)}</h2>";
        if (!empty($dto->subtitle)) {
            $html .= "<p>{$this->escape($dto->subtitle)}</p>";
        }
        $html .= "</div>";

        if ($showCarousel) {
            $html .= $this->renderCarousel($dto);
        } else {
            $html .= $this->renderGrid($dto);
        }

        $html .= "</div>";
        $html .= "</section>";

        return $html;
    }

    private function renderGrid(TeamBlockDto $dto): string
    {
        $html = "<div class=\"team-grid\">";

        foreach ($dto->members as $member) {
            $html .= $this->renderMember($member);
        }

        $html .= "</div>";

        return $html;
    }

    private function renderCarousel(TeamBlockDto $dto): string
    {
        $html = "<div class=\"team-carousel-wrapper\">";

        $html .= "<button class=\"team-nav team-nav-prev\" onclick=\"scrollTeamCarousel(this, 'prev')\" aria-label=\"Previous\">";
        $html .= "<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"15 18 9 12 15 6\"></polyline></svg>";
        $html .= "</button>";

        $html .= "<button class=\"team-nav team-nav-next\" onclick=\"scrollTeamCarousel(this, 'next')\" aria-label=\"Next\">";
        $html .= "<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"9 18 15 12 9 6\"></polyline></svg>";
        $html .= "</button>";

        $html .= "<div class=\"team-carousel\" data-team-carousel>";

        foreach ($dto->members as $member) {
            $html .= $this->renderMember($member);
        }

        $html .= "</div>";

        if (count($dto->members) > 1) {
            $html .= "<div class=\"team-indicators\">";
            for ($i = 0; $i < count($dto->members); $i++) {
                $activeClass = $i === 0 ? ' active' : '';
                $html .= "<button class=\"team-indicator{$activeClass}\" onclick=\"scrollTeamToIndex(this, {$i})\" aria-label=\"Go to member " . ($i + 1) . "\"></button>";
            }
            $html .= "</div>";
        }

        $html .= "</div>";

        return $html;
    }

    private function renderMember(array $member): string
    {
        $memberData = array_merge($member, [
            'type' => 'person',
            'displayType' => 'profile'
        ]);
        try {
            if ($this->blockFactory->supports('person')) {
                $dto = $this->blockFactory->make($memberData);
                if ($dto instanceof PersonBlockDto && $this->blockRendererManager->supports($dto)) {
                    return $this->blockRendererManager->render($dto);
                }
            }
        } catch (\Throwable $e) {
            // Fallback to parser when factory/manager unavailable
        }
        $personParser = new \App\Parsers\PersonBlockParser();
        $parsedMember = $personParser->parse($memberData);
        return $personParser->generateHtml($parsedMember);
    }
}