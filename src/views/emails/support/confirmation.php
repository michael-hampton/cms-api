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

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }

        .content {
            background: #f9f9f9;
            padding: 30px;
            border-radius: 0 0 8px 8px;
        }

        .ticket-box {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #667eea;
            border-radius: 4px;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Support Request Received</h1>
    </div>
    <div class="content">
        <p>Hi <?= htmlspecialchars($memberName) ?>,</p>

        <p>Thank you for contacting us. We've received your support request and will get back to you as soon as
            possible.</p>

        <div class="ticket-box">
            <p><strong>Ticket Number:</strong> #<?= $ticket->id ?></p>
            <p><strong>Subject:</strong> <?= htmlspecialchars($ticket->reason) ?></p>
            <p><strong>Your Message:</strong></p>
            <p><?= nl2br(htmlspecialchars($ticket->message)) ?></p>
        </div>

        <p>We typically respond within 24-48 hours during business days.</p>

        <p>If you need to add any additional information, please reply to this email with your ticket number.</p>

        <p>Best regards,<br>Support Team</p>
    </div>
    <div class="footer">
        <p>This is an automated message. Please do not reply directly to this email.</p>
    </div>
</div>
</body>
</html>