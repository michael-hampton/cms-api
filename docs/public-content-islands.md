# Public Content Islands

Public content components are rendered server-side and may opt into client-side hydration only when they need browser behaviour. This keeps normal CMS content static while allowing interactive widgets such as comments, page actions, badge modals and subscription UI to behave like islands.

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
    data-hydration="visible"
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

## Frontend registry

`src/public/js/public-islands.js` exposes a tiny registry:

```js
window.PublicIslands.register('page-actions', {
    hydrate(root, props) {
        // Only query inside root. Do not globally scan the document.
    }
});
```

The registry scans for `[data-island]`, reads `data-props`, and schedules hydration using the component's `data-hydration` value. Existing widget scripts can migrate incrementally by registering one island at a time.

## Rules of thumb

- Use `stateful: true` only for components that need browser behaviour.
- Keep server-rendered HTML useful before hydration.
- Query inside the island root instead of using global selectors.
- Use `endpoints` as the island's API props.
- Do not add extra layout wrappers just to mark islands; use the existing `.public-content-component` wrapper.
- Do not make `custom_handler` return separate public API response shapes; it should contribute to the normal public content document when that architecture is introduced.
