# Confirm Your Newsletter Subscription 📬

Hello **<?= $name ?>**,

Thanks for signing up for our newsletter! We're excited to keep you in the loop.

@promotion(📧 Just one click to complete your subscription!)

## Confirm Your Email Address

To make sure we have the right email and that you really want to hear from us, please confirm your subscription:

@button(Yes\, Subscribe Me!, <?= $confirmationUrl ?>)

@panel(⏰ This confirmation link will expire in 48 hours)

@divider

## What You'll Get

Once confirmed, you'll receive:

@table(Content|Frequency)
@row(📰 Latest Updates|Weekly digest of our best content)
@row(🎁 Exclusive Deals|Subscriber-only discounts and offers)
@row(🚀 Early Access|Be first to know about new releases)
@row(💡 Expert Tips|Industry insights and how-to guides)
@endtable

@divider

## Privacy Promise

We respect your inbox and your privacy:

✓ **No spam** - only quality content
✓ **Unsubscribe anytime** - one-click in every email
✓ **Never sell your data** - your email stays with us
✓ **Weekly frequency** - not daily bombardment

@divider

## Didn't Sign Up?

If you didn't request this, simply ignore this email. You won't be subscribed unless you confirm.

@buttonSecondary(View Newsletter Archive, <?= config('app.url') ?>/<?= \App\Framework\Support\SiteContext::slug() ?>/newsletters)

@subcopy(Having trouble? Copy this link: <?= $confirmationUrl ?>)

@subcopy(Want to manage your preferences? Visit your subscription settings after confirming.)