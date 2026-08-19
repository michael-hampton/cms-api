<?php

namespace App\Services\PublicContent\Widgets;

use App\DTO\PublicContent\PublicContentComponent;
use App\DTO\PublicContent\PublicContentContext;
use App\Framework\View\ViewRenderer;
use App\Services\PublicContent\Paywall\PublicContentPaywallModeResolver;

final class PaywallOverlayWidget implements PublicContentWidgetDefinition
{
    public function __construct(
        private readonly ViewRenderer $views,
        private readonly PublicContentPaywallModeResolver $paywallMode,
        private readonly WidgetThemeViewData $themeView,
    ) {
    }

    public function key(): string
    {
        return 'paywall-overlay';
    }

    public function defaultPlacement(): WidgetPlacement
    {
        return new WidgetPlacement(
            widgetKey: $this->key(),
            region: 'modals',
            priority: 1,
        );
    }

    public function supports(PublicContentContext $context): bool
    {
        return ($context->viewData['access']['can_view'] ?? true) === false;
    }

    public function build(PublicContentContext $context, WidgetPlacement $placement): PublicContentComponent
    {
        return new PublicContentComponent(
            id: $this->key(),
            type: $this->key(),
            region: $placement->regionName(),
            priority: $placement->priority,
            html: $this->views->partial('components/paywall-overlay', $context->with($this->themeView->merge($context, [
                'reason' => $context->viewData['access']['reason'] ?? 'subscription_required',
                'paywallMode' => $context->viewData['paywallMode']
                    ?? $this->paywallMode->resolve($context->page),
                'widgetConfiguration' => $placement->configuration,
            ]))),
            styles: [asset('paywall-overlay.css', 'css')],
            scripts: [asset('paywall-overlay.js', 'js')],
            endpoints: [],
            stateful: true,
        );
    }
}
