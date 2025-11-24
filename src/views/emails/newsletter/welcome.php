# Welcome to the Community! 🎉

Hello **<?= $name ?>**,

Your subscription is confirmed! Welcome aboard – we're thrilled to have you as part of our community.

@promotion(🎊 You're officially on the list!)

<?php if ($welcomeOffer): ?>
    ## Your Welcome Gift 🎁

    As a thank you for subscribing, here's an exclusive offer just for you:

    @panel(**<?= $welcomeOffer['title'] ?>** - <?= $welcomeOffer['description'] ?>)

    **Discount Code:** `<?= $welcomeOffer['code'] ?>`
    **Discount:** <?= $welcomeOffer['discount'] ?>% OFF
    **Valid Until:** <?= date('F j, Y', strtotime($welcomeOffer['expires_at'])) ?>

    @button(Shop Now & Save, <?= config('app.url') ?>/shop?code=<?= $welcomeOffer['code'] ?>)

    @divider
<?php endif; ?>

## What's Coming Your Way

@panel(📰 **Weekly Digest** - Every Monday at 9 AM, get a curated roundup of latest blog posts, product spotlights, and industry news)

@panel(🎁 **Exclusive Perks** - Early access to sales, subscriber-only discount codes, and VIP invitations)

@panel(💡 **Educational Content** - How-to guides, expert tips, case studies, and video content)

@divider

## Get Social With Us

Stay connected on social media for even more content:

📘 Facebook | 📷 Instagram | 🐦 Twitter | 💼 LinkedIn

@buttonSecondary(Follow Us, <?= config('app.url') ?>/social)

@divider

## Customize Your Experience

@table(Preference|Action)
@row(Email Frequency|Update how often we email you)
@row(Content Types|Choose topics you're interested in)
@row(Promotions|Opt in/out of special offers)
@row(Unsubscribe|Leave anytime\, no hard feelings)
@endtable

@buttonSecondary(Manage Preferences, <?= config('app.url') ?>/newsletter/preferences?email=<?= urlencode($email) ?>)

@divider

## We're Here to Help

Have questions? Want to share feedback? We'd love to hear from you!

**Email:** <?= config('mail.newsletter_email', 'newsletter@example.com') ?>
**Reply** to this email and a real human will respond

Thanks again for subscribing. We promise to make it worth your while!

@subcopy(You're receiving this because you confirmed your subscription to our newsletter. To unsubscribe or manage your preferences\, click the link above.)