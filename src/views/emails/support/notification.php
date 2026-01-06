<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .ticket-info {
            background: #f9f9f9;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .label {
            font-weight: bold;
            color: #667eea;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>New Support Ticket</h2>

    <div class="ticket-info">
        <p><span class="label">Ticket #:</span> <?= $ticket->id ?></p>
        <p><span class="label">From:</span> <?= htmlspecialchars($member->first_name . ' ' . $member->last_name) ?>
            (<?= htmlspecialchars($member->email) ?>)</p>
        <p><span class="label">Reason:</span> <?= htmlspecialchars($ticket->reason) ?></p>

        <?php if ($ticket->subscription_id): ?>
            <p><span class="label">Subscription ID:</span> <?= $ticket->subscription_id ?></p>
        <?php endif; ?>

        <?php if ($ticket->brand): ?>
            <p><span class="label">Brand:</span> <?= htmlspecialchars($ticket->brand) ?></p>
        <?php endif; ?>

        <p><span class="label">Message:</span></p>
        <p><?= nl2br(htmlspecialchars($ticket->message)) ?></p>

        <p><span class="label">Contact:</span> <?= htmlspecialchars($ticket->contact_email) ?>
            <?php if ($ticket->contact_phone): ?>
                | <?= htmlspecialchars($ticket->contact_phone) ?>
            <?php endif; ?>
        </p>
    </div>
</div>
</body>
</html>