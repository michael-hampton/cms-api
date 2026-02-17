<?php

namespace App\Listeners\Members;

use App\Events\Orders\OrderCreatedEvent;
use App\Exceptions\Members\AccountAlreadyActivatedException;
use App\Framework\Mail\MailManager;
use App\Framework\Support\Logger;
use App\Models\Member;
use App\Services\Members\MemberActivationService;

/**
 * Sends an account activation email to members created via guest checkout.
 *
 * Fires on: OrderCompleted
 *
 * Guard conditions — email is sent ONLY when ALL of the following are true:
 *   1. The order has an associated member (member_id is not null)
 *   2. The member has no password set (guest checkout path)
 *   3. The member was not authenticated via OTP during checkout
 *
 * This listener is failure-safe: an email failure must never affect the order.
 * The order is already committed before this listener runs. Any exception here
 * is caught, logged, and swallowed.
 *
 * Queued: yes — this must not block the checkout response.
 */
class SendAccountActivationEmailListener
{
    public function __construct(
        private readonly MemberActivationService $activationService,
        private readonly MailManager             $mailer
    )
    {
    }

    public function handle(OrderCreatedEvent $event): void
    {
        $order = $event->order;

        // Guard: order must be associated with a member.
        if (!$order->user_id) {
            return;
        }

        $member = Member::find($order->user_id);

        if (!$member) {
            Logger::warning('SendAccountActivationEmailListener: member not found', [
                'member_id' => $order->member_id,
                'order_id' => $order->id,
            ]);
            return;
        }

        // Guard: only unactivated accounts need this email.
        if ($this->activationService->isActivated($member)) {
            return;
        }

        // Guard: OTP-authenticated members have already verified their identity.
        // They follow a different activation path (TBD, out of scope here).
        if ($this->memberIsOtpAuthenticated($member)) {
            return;
        }

        // Email failure must never propagate. The order is done. Log and move on.
        try {
            $token = $this->activationService->generateActivationToken($member);

            echo $token;

            $this->mailer->to($member->email)->send(
                new AccountActivationMail($member, $token, $order)
            );

            Logger::info('Account activation email sent', [
                'member_id' => $member->id,
                'order_id' => $order->id,
            ]);
        } catch (AccountAlreadyActivatedException $e) {
            // Race condition: member activated between the guard above and
            // token generation. Safe to ignore.
            Logger::info('Activation email skipped: account already activated', [
                'member_id' => $member->id,
                'order_id' => $order->id,
            ]);
        } catch (\Throwable $e) {
            Logger::error('Failed to send account activation email', [
                'member_id' => $member->id,
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Determine whether this member authenticated via OTP during checkout.
     *
     * OTP-authenticated members have a verified identity but still no password.
     * Their activation path is handled separately (out of scope for this task).
     *
     * Adjust the column/flag name to match your actual schema.
     */
    private function memberIsOtpAuthenticated(Member $member): bool
    {
        return !empty($member->otp_verified_at);
    }
}