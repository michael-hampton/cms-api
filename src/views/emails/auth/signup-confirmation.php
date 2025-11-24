# Welcome to <?= config('app.name', 'Our Store') ?>! 🎉

Hello **<?= $member->first_name ?>**,

Thank you for joining us! We're excited to have you as a member of our community.

@promotion(🎁 Your account has been created successfully!)

## Verify Your Email Address

To get started and unlock all features, please verify your email address by clicking the button below:

@button(Verify Email Address, <?= $verificationUrl ?>)

@panel(⏰ This verification link will expire in 24 hours for security purposes.)

@divider

## What's Next?

Once you verify your email, you'll be able to:

@table(Feature|Description)
@row(🛍️ Shop|Browse our full catalog and make purchases)
@row(📦 Track Orders|Monitor your orders in real-time)
@row(❤️ Save Favorites|Create wishlists and save items)
@row(🎁 Get Deals|Receive exclusive member-only discounts)
@row(⭐ Earn Rewards|Join our loyalty program)
@row(⚡ Quick Checkout|Save addresses and payment methods)
@endtable

@divider

## Your Account Details

**Email:** <?= $member->email ?>
**Member Since:** <?= $member->created_at->format('F j, Y') ?>
**Account ID:** #<?= $member->id ?>

@buttonSecondary(Visit Your Account, <?= config('app.url') ?>/account)

@divider

## Need Help?

If you didn't create this account, please ignore this email or contact our support team.

@panel(📧 **Customer Support**
Email: <?= config('mail.support_email', 'support@example.com') ?>
Phone: <?= config('app.support_phone', '1-800-123-4567') ?>
Live Chat: Available 24/7)

@buttonSecondary(Contact Support, <?= config('app.url') ?>/support)

@subcopy(If you're having trouble clicking the verification button\, copy and paste this URL into your browser: <?= $verificationUrl ?>)