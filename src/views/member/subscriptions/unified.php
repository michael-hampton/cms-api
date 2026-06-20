<?php
/** @var \App\Models\Member $member */
/** @var \App\Models\Site $site */

$pageTitle = 'My Subscriptions';
?>

@include('member/_header')

<meta name="csrf-token" content="<?= htmlspecialchars(\App\Framework\Security\Csrf::getToken()) ?>">
<link rel="stylesheet" href="/public/css/subscription-account.css">
<link rel="stylesheet" href="/public/css/subscription-account-drawer.css">
<link rel="stylesheet" href="/public/css/subscription-account-delivery.css">
<link rel="stylesheet" href="/public/css/subscription-account-upgrade.css">
<link rel="stylesheet" href="/public/css/member-subscription-account.css">

<main class="member-subscription-account member-subscription-account__page">
    @include('subscriptions/shared/_subscription_account')
</main>

<script src="/public/js/subscription-account.js" defer></script>
<script src="/public/js/subscription-account-runtime.js" defer></script>
<script src="/public/js/subscription-account-drawer-bootstrap.js" defer></script>
<script src="/public/js/subscription-account-management.js" defer></script>
<script src="/public/js/subscription-account-history-delivery.js" defer></script>
<script src="/public/js/subscription-account-upgrade.js" defer></script>
<script src="/public/js/subscription-account-preferences.js" defer></script>
<script src="/public/js/subscription-account-delivery-address.js" defer></script>
<script src="/public/js/subscription-account-digital-access.js" defer></script>
<script src="/public/js/subscription-account-issue-deliveries.js" defer></script>
<script src="/public/js/subscription-account-acquisition.js" defer></script>
</body>
</html>
