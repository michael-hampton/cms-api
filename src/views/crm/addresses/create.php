<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Address &mdash; <?= htmlspecialchars($member->first_name . ' ' . $member->last_name) ?> &mdash;
        CRM</title>
    @css('crm/styles.css')
</head>
<body>
<div class="crm-shell">

    <aside class="sidebar">
        <div class="sidebar-logo">&#9635; CRM</div>
        <nav class="sidebar-nav">
            <a href="/crm/members" class="active">&#128100; Members</a>
        </nav>
    </aside>

    <div class="main">
        <div class="topbar">
            <a href="/crm/members/<?= (int)$member->id ?>" class="topbar-back">
                &larr; <?= htmlspecialchars($member->first_name . ' ' . $member->last_name) ?>
            </a>
            <h1>Add Address</h1>
        </div>

        <div class="content">
            <?php
            $submitLabel = 'Create Address';
            $formAction = '/crm/members/' . (int)$member->id . '/addresses';
            $cancelUrl = '/crm/members/' . (int)$member->id;
            $address = null;
            ?>
            @include('crm/addresses/_form', ['submitLabel' => $submitLabel, 'formAction' => $formAction, 'cancelUrl' =>
            $cancelUrl, 'address' => $address])
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>
</body>
</html>