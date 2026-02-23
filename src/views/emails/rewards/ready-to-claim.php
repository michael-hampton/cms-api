# 🎁 Your Reward is Ready to Claim!

Hello **{{ $memberReward->member->first_name }}**,

Your recent purchase on order **#{{ $order->order_number }}** has unlocked a reward for you.

@panel(🎉 {{ $rewardName }} — ready to claim now)

@button(Claim Your Reward, {{ $claimUrl }})

@divider

@if($expiresAt)
@promotion(⏰ This reward expires on {{ $expiresAt->format('F j, Y') }} — don't miss out!)
@endif

## How to Claim

@table(Step|Action)
@row(1|Click the "Claim Your Reward" button above)
@row(2|Review your reward details)
@row(3|Follow the redemption instructions)
@endtable

@divider

## Questions?

If you have any trouble claiming your reward, our support team is ready to help.

@buttonSecondary(Contact Support, {{ config('app.url') }}/support)

@subcopy(You received this because a purchase on order #{{ $order->order_number }} qualified you for this reward. Visit your rewards dashboard to see all your available rewards.)