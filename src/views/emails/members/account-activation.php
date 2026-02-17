// src/views/emails/members/account-activation.php

# Finish setting up your account

Hello **{{ $member->first_name }}**,

You recently placed an order with us as a guest. We've created an account for
you so you can track your order and access your purchase history.

@panel(🔒 Set a password to activate your account and get full access)

## Your order

**Order number:** {{ $orderNumber }}

To view your order status and details at any time, activate your account below.

@divider

## Create your password

Click the button below to set your password. This link is valid for
**{{ $expiryHours }} hours** and can only be used once.

@button(Set your password, {{ $activationUrl }})

@divider

@subcopy(If you didn't place this order or don't want to create an account, you can safely ignore this email. The link will expire automatically.)

@subcopy(Having trouble with the button? Copy and paste this URL into your browser:)

@subcopy({{ $activationUrl }})