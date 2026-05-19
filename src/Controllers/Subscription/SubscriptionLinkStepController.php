<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Exceptions\Subscriptions\SubscriptionAlreadyLinkedException;
use App\Exceptions\Subscriptions\SubscriptionNotFoundException;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Services\Subscriptions\SubscriptionLinkingService;

/**
 * Handles Step 3 of member onboarding: subscription linking.
 *
 * Route suggestions
 * ─────────────────
 *   GET  /member/onboarding/link-subscription          → showLinkStep()
 *   POST /member/onboarding/link-subscription          → linkSubscription()
 */
class SubscriptionLinkStepController extends Controller
{
    /** URL of the next onboarding step (Step 4 – Preferences) */
    private const NEXT_STEP      = '/member/onboarding/preferences';
    private const PREFS_SAVE_URL = '/member/onboarding/preferences/save';

    public function __construct(
        private readonly SubscriptionLinkingService $linkingService,
    ) {
        parent::__construct();
    }

    // ── Step 3 view ───────────────────────────────────────────────────

    public function showLinkStep(): mixed
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        // Skip Step 3 entirely when the member already has a linked subscription.
        if ($this->linkingService->memberHasLinkedSubscription($member->id, $siteId)) {
            return $this->redirect(self::NEXT_STEP);
        }

        return $this->view('subscriptions/link-subscription', [
            'member'     => $member,
            'site'       => SiteContext::get(),
            'nextStepUrl' => self::NEXT_STEP,
            'prefsSaveUrl' => self::PREFS_SAVE_URL,
            'linkUrl'    => '/member/onboarding/link-subscription',
            'csrfToken'  => csrf_token(),
        ]);
    }

    // ── POST handler ──────────────────────────────────────────────────

    public function linkSubscription(Request $request): mixed
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        $accountNumber = trim((string) $request->input('account_number', ''));
        $postcode      = trim((string) $request->input('postcode', ''));

        if ($accountNumber === '' || $postcode === '') {
            return $this->jsonResponse([
                'success'    => false,
                'error_code' => 'validation_error',
                'message'    => 'Account number and postcode are required.',
            ], 422);
        }

        try {
            $this->linkingService->linkToMember(
                $member->id,
                $accountNumber,
                $postcode,
                $siteId,
            );

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Subscription linked successfully.',
                'redirect' => self::NEXT_STEP,
            ]);

        } catch (SubscriptionNotFoundException) {
            return $this->jsonResponse([
                'success'    => false,
                'error_code' => 'mismatch',
                'message'    => 'We couldn\'t find a subscription matching those details. '
                    . 'Please check your mailing label and try again.',
            ], 422);

        } catch (SubscriptionAlreadyLinkedException $e) {
            return $this->jsonResponse([
                'success'    => false,
                'error_code' => 'already_linked',
                'message'    => $this->maskedAlreadyLinkedMessage($e->getLinkedEmail()),
            ], 409);

        } catch (\Throwable $e) {
            Logger::error('Subscription linking failed', [
                'member_id' => $member->id,
                'error'     => $e->getMessage(),
            ]);

            return $this->jsonResponse([
                'success'    => false,
                'error_code' => 'server_error',
                'message'    => 'Something went wrong. Please try again.',
            ], 500);
        }
    }

    // ── Private ───────────────────────────────────────────────────────

    /**
     * Produce the masking format from the spec:
     *   ts*****@y****.com  (first 2 chars of local, then stars; first char of domain, then stars)
     */
    private function maskedAlreadyLinkedMessage(string $email): string
    {
        $masked = $this->maskEmail($email);

        return "This subscription is already linked to an account: {$masked}. "
            . 'Please log in or create an account with that email to access your benefits.';
    }

    private function maskEmail(string $email): string
    {
        if (!str_contains($email, '@')) {
            return '****';
        }

        [$local, $domain] = explode('@', $email, 2);

        $maskedLocal = substr($local, 0, 2) . str_repeat('*', max(5, strlen($local) - 2));

        $dotPos     = strrpos($domain, '.');
        $domainName = $dotPos !== false ? substr($domain, 0, $dotPos) : $domain;
        $tld        = $dotPos !== false ? substr($domain, $dotPos)    : '';
        $maskedDomain = substr($domainName, 0, 1) . str_repeat('*', max(4, strlen($domainName) - 1));

        return "{$maskedLocal}@{$maskedDomain}{$tld}";
    }
}