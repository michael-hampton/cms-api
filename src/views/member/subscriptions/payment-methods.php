<?php
/**
 * View: member/subscriptions/payment-methods.php
 *
 * Site-scoped (member area) wrapper around the SAME payment-methods panel
 * used by the PressStack account area (see
 * src/views/subscriptions/account/billing.php). All markup lives in
 * shared/billing/_payment_methods_panel.php and all behaviour lives in
 * public/js/saved-payment-methods.js - this file only supplies the member
 * header/layout and the site-scoped endpoint configuration.
 *
 * @var \App\Models\Member $member
 * @var \App\Models\Site $site
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Methods - <?= htmlspecialchars($site->name) ?></title>
    <script src="https://js.stripe.com/v3/"></script>
    <meta name="csrf-token" content="<?= csrf_token() ?>">
</head>
<body>

@include('member/_header')

<main class="container" style="max-width: 1200px; margin: 0 auto; padding: 2rem;">
    @include('shared/billing/_payment_methods_panel', ['pmHeadingId' => 'member-payment-methods-heading'])
</main>

<script>
    window.SavedPaymentMethodsConfig = {
        stripePublicKey: '<?= addslashes((string) ($_ENV['STRIPE_PUBLIC_KEY'] ?? config('payment.stripe.public_key') ?? '')) ?>',
        memberName: '<?= addslashes((string) ($member->displayName ?? $member->full_name ?? '')) ?>',
        memberEmail: '<?= addslashes((string) ($member->email ?? '')) ?>',
        endpoints: (function () {
            const site = '<?= addslashes($site->slug ?? '') ?>';
            return {
                list: `/api/${site}/member/payment-methods`,
                setupIntent: `/${site}/member/payment-methods/setup-intent`,
                store: `/${site}/member/payment-methods`,
                setDefault: id => `/${site}/member/payment-methods/${id}/set-default`,
                remove: id => `/${site}/member/payment-methods/${id}`,
                removeMethod: 'DELETE',
                replace: id => `/${site}/member/payment-methods/${id}/update`,
            };
        })(),
    };
</script>
<script src="/public/js/saved-payment-methods.js" defer></script>

</body>
</html>
