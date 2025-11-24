# Forgot Your Password?

Hello,

We received a request to reset the password for the account associated with **<?= $email ?>**.

No worries! It happens to the best of us.

@panel(Click the button below to reset your password and regain access to your account)

@button(Reset My Password, <?= $resetUrl ?>)

@divider

## What Happens Next?

1. **Click the button above** to go to the password reset page
2. **Enter a new password** (make it strong and secure!)
3. **Confirm your new password**
4. **Log in** with your new credentials

@promotion(⏰ This link expires in <?= $expiresInMinutes ?> minutes)

@divider

## Didn't Request This?

If you didn't request a password reset, you can safely ignore this email. Someone may have entered your email address by mistake.

@panel(🔒 Your account is secure - no changes have been made)

However, if you're concerned about your account security:

✓ Make sure your password is strong and unique
✓ Never share your password with anyone
✓ Be cautious of phishing emails
✓ Contact us if you notice any suspicious activity

@buttonSecondary(Contact Support, <?= config('app.url') ?>/support)

@divider

## Security Tips

🔐 **Use a password manager** to generate and store strong passwords
🔐 **Enable two-factor authentication** for extra security
🔐 **Don't reuse passwords** across different websites
🔐 **Update your password regularly** every 3-6 months

@subcopy(If the button doesn't work\, copy and paste this URL into your browser: <?= $resetUrl ?>)

@subcopy(This password reset link will expire on <?= date('F j\, Y \a\t g:i A', strtotime('+' . $expiresInMinutes . ' minutes')) ?> for your security.)