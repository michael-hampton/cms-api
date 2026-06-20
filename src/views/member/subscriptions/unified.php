<?php
/** @var \App\Models\Member $member */
/** @var \App\Models\Site $site */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <title>My Subscriptions - <?= htmlspecialchars($site->name) ?></title>
    <link rel="stylesheet" href="/public/css/subscription-account.css">
    <link rel="stylesheet" href="/public/css/subscription-account-drawer.css">
    <link rel="stylesheet" href="/public/css/subscription-account-delivery.css">
    <link rel="stylesheet" href="/public/css/subscription-account-upgrade.css">
    <link rel="stylesheet" href="/public/css/member-subscription-account.css">
</head>
<body class="member-subscription-account">
<main class="member-subscription-account__page">
    <a class="member-subscription-account__back" href="/<?= htmlspecialchars($site->slug) ?>/member">← Back to member area</a>
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
</body>
</html>
