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

Here's what you can look forward to:

### 📰 Weekly Digest
Every **Monday at 9 AM**, get a curated roundup of:
- Latest blog posts and articles
- Product spotlights and recommendations
- Industry news and trends
- Community highlights

### 🎁 Exclusive Subscriber Perks
- Early access to sales and new products
- Subscriber-only discount codes
- VIP invitations to special events
- Free shipping offers

### 💡 Educational Content
- How-to guides and tutorials
- Expert tips and best practices
- Case studies and success stories
- Video content and webinars

@divider

## Get Social With Us

Stay connected on social media for even more content:

📘 **Facebook:** facebook.com/yourstore
📷 **Instagram:** @yourstore
🐦 **Twitter:** @yourstore
💼 **LinkedIn:** linkedin.com/company/yourstore

@buttonSecondary(Follow Us, <?= config('app.url') ?>/social)

@divider

## Customize Your Experience

Want to adjust what you receive or how often? You're in control!

@table(Preference|Action)
@row(Email Frequency|Update how often we email you)
@row(Content Types|Choose topics you're interested in)
@row(Promotions|Opt in/out of special offers)
@row(Unsubscribe|Leave anytime, no hard feelings)
@endtable

@buttonSecondary(Manage Preferences, <?= config('app.url') ?>/newsletter/preferences?email=<?= urlencode($email) ?>)

@divider

## Quick Links

🛍️ [Shop Now](<?= config('app.url') ?>/shop)
📖 [Read Our Blog](<?= config('app.url') ?>/blog)
🎓 [Learning Center](<?= config('app.url') ?>/learn)
💬 [Contact Us](<?= config('app.url') ?>/contact)

@divider

## We're Here to Help

Have questions? Want to share feedback? We'd love to hear from you!

**Email:** <?= config('mail.newsletter_email', 'newsletter@example.com') ?>
**Reply** to this email and a real human will respond

Thanks again for subscribing. We promise to make it worth your while!

@subcopy(You're receiving this because you confirmed your subscription to our newsletter. To unsubscribe or manage your preferences, click here.)