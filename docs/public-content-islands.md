# Public Content Islands

Public content components are rendered server-side and may opt into client-side hydration only when they need browser behaviour. This keeps normal CMS content static while allowing interactive widgets such as comments, page actions, newsletter prompts, voucher UI, category carousels, deals carousels and contributor carousels to behave like islands.

## Component contract

Every public content component still returns backend-rendered `html`, `assets`, `endpoints`, `stateful` and `hydration` metadata. Stateful components are treated as islands.

`component.html` remains the raw server-rendered component markup. The public V2 renderer already creates one `.public-content-component` wrapper for each component, and island metadata is applied to that existing wrapper rather than adding a second wrapper inside the API HTML payload.

```html
<div
    class="public-content-component public-content-component--page-actions"
    data-component="page-actions"
    data-component-id="page-actions"
    data-component-type="page-actions"
    data-stateful="true"
    data-hydration="load"
    data-island="page-actions"
    data-props='{"endpoints":{"like":"/api/like"}}'
>
    ...server-rendered component HTML...
</div>
```

Non-stateful components are still mounted through the same wrapper and receive component metadata, but they do not receive `data-island` and are not hydrated by the frontend registry.

## Hydration strategies

Supported hydration values are:

| Value | Behaviour |
|---|---|
| `none` | Never hydrate automatically. |
| `visible` | Hydrate when the island approaches the viewport. |
| `idle` | Hydrate during browser idle time, with a timer fallback. |
| `interaction` | Hydrate on first click, focus or pointer enter. |
| `load` | Hydrate as soon as the registry scans the page. |

`PublicContentComponentDefinition` defaults stateful widgets to `load` and non-stateful widgets to `none` unless a specific hydration strategy is supplied.

The public V2 renderer also applies runtime defaults for built-in stateful widgets so hydration behaviour can be tuned without changing rendered component HTML:

| Widget type | Strategy | Reason |
|---|---|---|
| `page-actions` | `load` | Like buttons need to be interactive as soon as the header is visible. |
| `categories-widget` | `load` | Category carousel controls and selection redirects should behave immediately. |
| `comments` | `visible` | Comment lists and forms can wait until the comments section approaches the viewport. |
| `newsletter-signup-widget` | `interaction` | Newsletter prompts only need behaviour after a click/focus/hover. |
| `voucher-carousel` | `load` | Voucher carousel controls and modal triggers need handlers as soon as the section renders. |
| `deals-carousel` | `load` | Carousel controls, search, wishlist and cart handlers should match the previous immediate script behaviour. |
| `guest-contributors` | `load` | Carousel controls and autoplay should behave like the previous immediate hydrator path. |

## Frontend registry

`src/public/js/public-islands.js` exposes a tiny registry:

```js
window.PublicIslands.register('page-actions', {
    hydrate(root, props) {
        // Only query inside root. Do not globally scan the document.
    }
});
```

The registry scans for `[data-island]`, reads `data-props`, and schedules hydration using the component's `data-hydration` value. It supports both initial document scans and dynamic scans of newly mounted component roots.

The registry guards against duplicate work with weak sets for scheduled and hydrated roots. Widget code should still keep a local guard such as `data-api-hydrated` where duplicate event binding would be harmful.

## Migrated widgets

The following built-in stateful widgets are migrated to the island registry:

| Widget | Location | Notes |
|---|---|---|
| `page-actions` | `src/public/js/public-content-v2-hydrators.js` | Replaces the old inline template hydrator and preserves like class, `aria-pressed`, SVG fill, text and count updates. |
| `categories-widget` | `src/public/js/categories-widget.js` | Replaces inline `onclick`, `onchange` and template script handlers with scoped carousel scroll and category selection behaviour. |
| `comments` | `src/public/js/public-content-v2-hydrators.js` | Loads comments and binds submit/pagination only when hydrated. |
| `newsletter-signup-widget` | `src/public/js/public-content-v2-hydrators.js` | Dispatches the existing `newsletter:open` event from the island root. |
| `voucher-carousel` | `src/public/js/public-voucher-carousel.js` | Carousel controls are scoped to the voucher island root; shared modal copy/close/Escape handling is bound once globally with a guard. |
| `deals-carousel` | `src/public/js/deals-carousel.js` | Uses the existing deals carousel class, now registered through `PublicIslands` instead of self-hydrating from the component-mounted event. |
| `guest-contributors` | `src/public/js/public-content-v2-hydrators.js` | Uses the existing carousel class, now registered through `PublicIslands` instead of the legacy hydrator map. |

## Asset loading

This migration defers hydration and event binding. Component asset scripts are still loaded by the public V2 renderer when components are mounted, because the scripts need to be available to register islands. Fully lazy-loading asset scripts at hydration time is a separate enhancement.

## Testing checklist

Before merging island changes, verify at least one public V2 page containing the relevant widgets:

- Logged-in page actions toggle like state once.
- Like count, `aria-pressed`, SVG fill and Like/Liked text update correctly.
- Category carousel previous/next controls scroll the pills.
- Category selection redirects after showing selected state.
- Comments load when the comments section approaches the viewport.
- Comment submission posts once and reloads the thread once.
- Comment pagination works after hydration.
- Newsletter widget opens the newsletter flow on interaction.
- Voucher carousel previous/next controls work.
- Voucher open buttons populate and show the voucher modal.
- Voucher copy works.
- Escape and close controls hide the voucher modal.
- Deals carousel controls, dots, search, wishlist and add-to-cart still work.
- Guest contributors carousel controls, dots, count/progress and autoplay still work on landing pages.

Run the focused resource test after backend metadata changes:

```bash
vendor/bin/phpunit --filter PublicContentResourceTest
```

## Rules of thumb

- Use `stateful: true` only for components that need browser behaviour.
- Keep server-rendered HTML useful before hydration.
- Query inside the island root instead of using global selectors.
- Use `endpoints` as the island's API props.
- Do not add extra layout wrappers just to mark islands; use the existing `.public-content-component` wrapper.
- Do not keep widget hydration scripts inside component templates once the widget is registered as an island.
