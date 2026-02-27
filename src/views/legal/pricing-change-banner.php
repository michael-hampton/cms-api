@if (isset($pricingChange) && $pricingChange && !$pricingChange->isCancelled() && !$pricingChange->isApplied())
@php
$effectiveDate   = $pricingChange->effective_date->format('j F Y');
$newPrice        = number_format($pricingChange->new_price, 2);
$currency        = $pricingChange->currency;
$daysRemaining   = max(0, (int) (new DateTime())->diff($pricingChange->effective_date)->days);
$managementUrl   = url('/account/subscriptions/' . $subscription->id);
$cancellationUrl = url('/account/subscriptions/' . $subscription->id . '/cancel');
$urgentThreshold = 7; // switch banner to amber when ≤ 7 days remain
@endphp

<div
        role="alert"
        aria-live="polite"
        class="pricing-change-banner {{ $daysRemaining <= $urgentThreshold ? 'pricing-change-banner--urgent' : '' }}"
        data-pricing-change-id="{{ $pricingChange->id }}"
>
    <div class="pricing-change-banner__icon" aria-hidden="true">
        @if ($daysRemaining <= $urgentThreshold)
        {{-- Clock icon for urgency --}}
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <polyline points="12 6 12 12 16 14"/>
        </svg>
        @else
        {{-- Info icon --}}
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        @endif
    </div>

    <div class="pricing-change-banner__body">
        <p class="pricing-change-banner__message">
            <strong>Price change notice:</strong>
            Your subscription price will change to
            <strong>{{ $currency }} {{ $newPrice }}</strong>
            on <strong>{{ $effectiveDate }}</strong>
            @if ($daysRemaining <= $urgentThreshold)
            — that's in {{ $daysRemaining }} day{{ $daysRemaining !== 1 ? 's' : '' }}.
            @endif
        </p>
        <div class="pricing-change-banner__actions">
            <a href="{{ $cancellationUrl }}" class="pricing-change-banner__link">
                Cancel before {{ $effectiveDate }}
            </a>
            <a href="{{ $managementUrl }}" class="pricing-change-banner__link pricing-change-banner__link--muted">
                Manage subscription
            </a>
        </div>
    </div>

    <button
            type="button"
            class="pricing-change-banner__dismiss"
            aria-label="Dismiss price change notice"
            onclick="this.closest('.pricing-change-banner').setAttribute('hidden', '')"
    >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
             stroke-linecap="round">
            <line x1="18" y1="6" x2="6" y2="18"/>
            <line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
    </button>
</div>

<style>
    .pricing-change-banner {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 14px 16px;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-left: 4px solid #3b82f6;
        border-radius: 8px;
        margin-bottom: 16px;
        font-size: 14px;
        line-height: 1.5;
    }

    .pricing-change-banner--urgent {
        background: #fffbeb;
        border-color: #fde68a;
        border-left-color: #f59e0b;
    }

    .pricing-change-banner__icon {
        flex-shrink: 0;
        margin-top: 1px;
        color: #3b82f6;
    }

    .pricing-change-banner--urgent .pricing-change-banner__icon {
        color: #f59e0b;
    }

    .pricing-change-banner__body {
        flex: 1;
        min-width: 0;
    }

    .pricing-change-banner__message {
        margin: 0 0 8px;
        color: #1e3a5f;
    }

    .pricing-change-banner--urgent .pricing-change-banner__message {
        color: #78350f;
    }

    .pricing-change-banner__actions {
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
    }

    .pricing-change-banner__link {
        font-size: 13px;
        font-weight: 600;
        color: #2563eb;
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    .pricing-change-banner--urgent .pricing-change-banner__link {
        color: #b45309;
    }

    .pricing-change-banner__link--muted {
        color: #6b7280;
        font-weight: 400;
    }

    .pricing-change-banner__dismiss {
        flex-shrink: 0;
        background: none;
        border: none;
        cursor: pointer;
        color: #9ca3af;
        padding: 2px;
        line-height: 1;
        transition: color 0.15s;
    }

    .pricing-change-banner__dismiss:hover {
        color: #374151;
    }
</style>
@endif