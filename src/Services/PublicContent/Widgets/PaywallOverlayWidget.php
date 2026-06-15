<?php

namespace App\Services\PublicContent\Widgets;

use App\DTO\PublicContent\PublicContentComponent;
use App\DTO\PublicContent\PublicContentContext;
use App\Framework\View\ViewRenderer;

final class PaywallOverlayWidget implements PublicContentWidgetDefinition
{
    public function __construct(private readonly ViewRenderer $views)
    {
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
            region: $placement->region,
            priority: $placement->priority,
            html: $this->views->partial('components/paywall-overlay', $context->with([
                'reason' => $context->viewData['access']['reason'] ?? 'subscription_required',
                'widgetConfiguration' => $placement->configuration,
            ])),
            styles: [asset('paywall-overlay.css', 'css')],
            scripts: [asset('paywall-overlay.js', 'js')],
            endpoints: [],
            stateful: true,
        );
    }
}
