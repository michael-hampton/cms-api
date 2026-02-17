<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Exceptions\Members\AccountAlreadyActivatedException;
use App\Exceptions\Members\InvalidActivationTokenException;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Services\Members\MemberActivationService;

/**
 * Handles the guest-checkout account activation flow.
 *
 * Routes:
 *   GET  /account/activate/{token}  → showActivationForm()
 *   POST /account/activate/{token}  → activate()
 *
 * Controller responsibilities:
 *   - Validate the token and resolve member context
 *   - Enforce rate limiting on submission
 *   - Delegate all business logic to MemberActivationService
 *   - Render the appropriate view or redirect
 *
 * This controller MUST NOT:
 *   - Hash passwords
 *   - Touch the token directly
 *   - Send emails
 *   - Know about the order beyond the redirect URL
 */
class AccountActivationController extends Controller
{
    /**
     * Submission rate limit: max attempts per token per window.
     * Prevents brute-force against the password-set endpoint.
     */
    private const RATE_LIMIT_MAX = 5;
    private const RATE_LIMIT_WINDOW = 300; // seconds (5 minutes)

    public function __construct(
        private readonly MemberActivationService $activationService
    )
    {
        parent::__construct();
    }

    /**
     * Render the "Create your password" page.
     *
     * On invalid/expired token → friendly error view (no redirect loop).
     * On already-activated account → redirect to login with message.
     */
    public function showActivationForm(Request $request, string $token): mixed
    {
        try {
            $member = $this->activationService->resolveActivationToken(
                $token,
                SiteContext::getId()
            );
        } catch (AccountAlreadyActivatedException) {
            return $this->redirect('/member/login')
                ->with('message', 'This account is already active. Please sign in.');
        } catch (InvalidActivationTokenException $e) {
            return $this->view('member/auth/activation-invalid', [
                'site' => SiteContext::get(),
            ]);
        }

        return $this->view('member/auth/set-password', [
            'token' => $token,
            'email' => $member->email,
            'site' => SiteContext::get(),
        ]);
    }

    /**
     * Handle the password-set form submission.
     *
     * Validates input, enforces rate limiting, delegates to the service.
     * On success: authenticates the member and redirects based on context.
     */
    public function activate(Request $request, string $token): mixed
    {
        // Rate limit before doing anything else.
        if ($this->isRateLimited($request, $token)) {
            return $this->respondWithRateLimitError($request);
        }

        $errors = $this->validatePasswordInput($request);
        if (!empty($errors)) {
            return $this->respondWithValidationErrors($request, $errors);
        }

        $password = $request->input('password');

        try {
            $member = $this->activationService->activate(
                $token,
                $password,
                SiteContext::getId()
            );
        } catch (AccountAlreadyActivatedException) {
            return $this->redirect('/member/login')
                ->with('message', 'This account is already active. Please sign in.');
        } catch (InvalidActivationTokenException) {
            return $this->view('member/auth/activation-invalid', [
                'site' => SiteContext::get(),
            ]);
        }

        $this->clearRateLimit($request, $token);

        return $this->respondWithSuccess($request, $member, $request->input('order_number'));
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Increment and check the per-token submission counter stored in session.
     * Token-based rate limiting is appropriate here because the token is
     * already a secret — we're protecting against automated retries, not
     * enumeration.
     */
    private function isRateLimited(Request $request, string $token): bool
    {
        $key = $this->rateLimitKey($token);
        $attempts = $request->session()->get($key . '_count', 0);
        $windowStart = $request->session()->get($key . '_since', time());

        // Reset window if expired.
        if ((time() - $windowStart) > self::RATE_LIMIT_WINDOW) {
            $request->session()->put($key . '_count', 0);
            $request->session()->put($key . '_since', time());
            $attempts = 0;
        }

        $request->session()->put($key . '_count', $attempts + 1);

        return $attempts >= self::RATE_LIMIT_MAX;
    }

    private function rateLimitKey(string $token): string
    {
        // Hash the token so it's not stored in plain text in the session.
        return 'activation_rl_' . hash('sha256', $token);
    }

    private function respondWithRateLimitError(Request $request): mixed
    {
        if ($this->isJsonRequest($request)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Too many attempts. Please wait a few minutes and try again.',
            ], 429);
        }

        return $this->back()->withErrors([
            'form' => 'Too many attempts. Please wait a few minutes and try again.',
        ]);
    }

    private function isJsonRequest(Request $request): bool
    {
        return $request->getHeader('X-Requested-With') === 'XMLHttpRequest'
            || $request->getHeader('Content-Type') === 'application/json';
    }

    /**
     * Validate password fields.
     * Returns an associative array of field → message, empty on success.
     */
    private function validatePasswordInput(Request $request): array
    {
        $errors = [];
        $password = $request->input('password', '');
        $confirm = $request->input('password_confirmation', '');

        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $errors['password'] = 'Password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $errors['password'] = 'Password must contain at least one number.';
        }

        if ($password !== $confirm) {
            $errors['password_confirmation'] = 'Passwords do not match.';
        }

        return $errors;
    }

    private function respondWithValidationErrors(Request $request, array $errors): mixed
    {
        if ($this->isJsonRequest($request)) {
            return $this->jsonResponse(['success' => false, 'errors' => $errors], 422);
        }

        return $this->back()->withErrors($errors);
    }

    private function clearRateLimit(Request $request, string $token): void
    {
        $key = $this->rateLimitKey($token);
        $request->session()->forget([$key . '_count', $key . '_since']);
    }

    /**
     * Determine redirect target after successful activation.
     *
     * Priority:
     *   1. Order detail page (if order_number was threaded through the form)
     *   2. My Orders
     *   3. Dashboard
     */
    private function respondWithSuccess(Request $request, $member, ?string $orderNumber): mixed
    {
        $redirectUrl = $this->resolveSuccessRedirect($orderNumber);

        if ($this->isJsonRequest($request)) {
            return $this->jsonResponse([
                'success' => true,
                'message' => 'Password set successfully. Redirecting…',
                'redirect_url' => $redirectUrl,
            ]);
        }

        return $this->redirect($redirectUrl)
            ->with('message', 'Password set successfully. Welcome!');
    }

    private function resolveSuccessRedirect(?string $orderNumber): string
    {
        $site = SiteContext::slug();

        if ($orderNumber) {
            return "/{$site}/orders/{$orderNumber}";
        }

        return "/{$site}/member/orders";
    }
}