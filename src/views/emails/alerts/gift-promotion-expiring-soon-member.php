# ⏰ Special offer ending soon — don't miss out!

Hello **<?= $member->first_name ?? 'there' ?>**,

A special promotion is ending in **<?= $hoursRemaining ?> hour<?= $hoursRemaining !== 1 ? 's' : '' ?>**. Shop now to make sure you qualify before it disappears.

@promotion(🎁 <?= $hoursRemaining ?> hour<?= $hoursRemaining !== 1 ? 's' : '' ?> left to claim your <?= strtolower($giftType) ?> gift)

@panel(This promotion expires on <?= $expiresAt->format('j F Y \a\t H:i') ?> UTC. Once it's gone, it's gone.)

@button(Shop Now, <?= $shopUrl ?>)

@divider

@subcopy(You're receiving this as an active customer. If you no longer wish to receive promotional emails, you can update your preferences in your account settings.)