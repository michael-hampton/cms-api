# Reset Your Password

Hello **<?= $member->first_name ?>**,

We received a request to reset the password for your account.

@panel(🔒 Click the button below to create a new password)

@button(Reset Password, <?= $resetUrl ?>)

@divider

## Security Information

@promotion(⏰ This link expires in <?= $expiresInMinutes ?> minutes)

If you did not request a password reset, please ignore this email. Your password will remain unchanged.

@divider

## Tips for a Strong Password

When creating your new password, make sure it:

✅ Is at least 8 characters long
✅ Contains uppercase and lowercase letters
✅ Includes numbers
✅ Has special characters (!@#$%^&*)
✅ Is unique and not used on other sites

@divider

## Didn't Request This?

If you didn't request a password reset, your account may be at risk. Please:

@panel(1. Ignore this email
2. Change your password immediately by logging in
3. Enable two-factor authentication if available
4. Contact our security team if you notice suspicious activity)

@buttonSecondary(Contact Support, <?= config('app.url') ?>/support)

@subcopy(If you're having trouble clicking the reset button\, copy and paste this URL into your browser: <?= $resetUrl ?>)

@subcopy(For security reasons\, this link will expire on <?= date('F j\, Y \a\t g:i A', strtotime('+' . $expiresInMinutes . ' minutes')) ?>)