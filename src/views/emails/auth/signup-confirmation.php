# Welcome to <?= config('app.name', 'Our Store') ?>! 🎉

Hello **<?= $member->first_name ?>**,

Thank you for joining us! We're excited to have you as a member of our community.

@promotion(🎁 Your account has been created successfully!)

## Verify Your Email Address

To get started and unlock all features, please verify your email address by clicking the button below:

@button(Verify Email Address, <?= $verificationUrl ?>)

@panel(This verification link will expire in 24 hours for security purposes.)

@divider

## What's Next?

Once you verify your email, you'll be able to:

✅ **Shop** - Browse our full catalog and make purchases
✅ **Track Orders** - Monitor your orders in real-time
✅ **Save Favorites** - Create wishlists and save items
✅ **Get Deals** - Receive exclusive member-only discounts
✅ **Earn Rewards** - Join our loyalty program
✅ **Quick Checkout** - Save addresses and payment methods

@divider

## Your Account Details

**Email:** <?= $member->email ?>
**Member Since:** <?= date('F j, Y', strtotime($member->created_at)) ?>
**Account ID:** #<?= $member->id ?>

@buttonSecondary(Visit Your Account, <?= config('app.url') ?>/account)

@divider

## Need Help?

If you didn't create this account, please ignore this email or contact our support team.

**Customer Support:**
📧 Email: <?= config('mail.support_email', 'support@example.com') ?>
📞 Phone: <?= config('app.support_phone', '1-800-123-4567') ?>
💬 Live Chat: <?= config('app.url') ?>/support

@subcopy(If you're having trouble clicking the verification button, copy and paste this URL into your browser: <?= $verificationUrl ?>)