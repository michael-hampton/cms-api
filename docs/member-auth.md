# Member authentication

This project has two member-facing account surfaces that share the same member identity but do not always enter through the same route flow:

- Site member areas, for example `/guitar-world/member/dashboard` and `/api/guitar-world/member/*`.
- PressStack account pages, for example `/press-stack/account/subscriptions`.

Both ultimately use `MemberAuth` for the authenticated member and `member_access_token` for member API requests.

## Normal site member area

Normal site member pages are backed by the member session. The member API routes under:

```txt
/api/{site}/member/*
```

use `AuthenticateMemberWithToken`.

Those APIs normally validate the `member_access_token` cookie against the current site id:

```php
$this->authService->validateAccessToken($token, $siteId);
```

If the PHP member session is valid but the cookie is missing, invalid, expired, or belongs to the wrong site context, the middleware should repair the browser state by issuing a fresh `member_access_token` for the current site and allow the request through.

This keeps the dashboard page and its API calls in sync. Without this fallback the page can look logged in because `MemberAuth::check()` is true, while the API calls fail with `401` because the stale token is checked first.

## PressStack account pages

PressStack account pages are intentionally accessible when logged out so the guest shell can show the email lookup modal:

```txt
/press-stack/account
/press-stack/account/subscriptions
/press-stack/account/orders
/press-stack/account/billing
```

A leftover `member_access_token` alone must not be enough to display private account data after the normal member session has ended.

For logged-out PressStack account page GET requests:

1. If `MemberAuth::check()` is false, the request is treated as a guest account request.
2. Any leftover `member_access_token` is cleared.
3. The controller is allowed to render the guest account shell and email modal.

This avoids a token-only state where PressStack rehydrates `MemberAuth` and exposes account information even though the user has logged out of the normal member session.

## PressStack email modal login

The PressStack email modal can log the member into the shared member session:

```php
MemberAuth::login($member);
```

It can also set `member_access_token`, but that token must remain compatible with normal site member APIs. If the member later opens a normal site dashboard, such as `/guitar-world/member/dashboard`, the API middleware can refresh the token for the current site if required.

## Rules to preserve

- Do not let a leftover token alone expose PressStack account data after logout.
- Do not let a stale or wrong-site `member_access_token` break normal member API calls when the member session is valid.
- Keep `MemberAuth` as the shared logged-in member identity.
- Use the existing `AuthenticationService` token system; this is a custom framework and does not use Sanctum.
- PressStack account pages may use cross-site token lookup internally, but normal `/api/{site}/member/*` routes should validate against the current site context or repair from a valid session.

## Relevant files

- `src/Framework/Middleware/AuthenticateMemberWithToken.php`
- `src/Controllers/Subscription/ShopAccountController.php`
- `src/Framework/Authorization/AuthenticationService.php`
- `src/routes/api.php`
- `src/routes/web.php`
