# Almost There! Confirm Your Subscription 📬

Hello **<?= $name ?>**,

Thanks for subscribing to our newsletter! We're excited to keep you updated with our latest news, offers, and exclusive content.

@promotion(✉️ Just one more step to complete your subscription!)

## Confirm Your Email

To make sure we have the right email address and that you really want to hear from us, please confirm your subscription:

@button(Yes\, Subscribe Me!, <?= $confirmationUrl ?>)

@panel(⏰ This confirmation link will expire in 48 hours)

@divider

## What You'll Receive

Once confirmed, here's what you can expect from us:

@table(Content|Frequency)
@row(📰 Weekly Newsletter|Industry news\, tips\, and insights)
@row(🎁 Exclusive Offers|Special discounts for subscribers only)
@row(🚀 Product Updates|Be the first to know about new releases)
@row(💡 Tips & Guides|Expert advice and how-to content)
@row(🎉 Special Announcements|VIP access to events and sales)
@endtable

<?php if (!empty($preferences)): ?>
    @divider

    ## Your Preferences

    You've selected to receive:

    <?php foreach ($preferences as $preference): ?>
        ✓ <?= htmlspecialchars($preference) ?>
    <?php endforeach; ?>
<?php endif; ?>

@divider

## How Often Will You Hear From Us?

We respect your inbox! Here's our promise:

@panel(📅 Weekly digest every Monday morning
🎯 Special offers 2-3 times per month
📢 Important announcements as needed
🚫 No spam - ever!)

You can update your preferences or unsubscribe at any time.

@divider

## Didn't Sign Up?

If you didn't request this subscription, simply ignore this email. You won't be added to our mailing list unless you confirm.

@buttonSecondary(Manage Preferences, <?= config('app.url') ?>/newsletter/preferences?email=<?= urlencode($email) ?>)

@subcopy(If you're having trouble clicking the confirmation button\, copy and paste this URL into your browser: <?= $confirmationUrl ?>)

@subcopy(Your email: <?= $email ?> | You can unsubscribe at any time using the link at the bottom of any newsletter.)