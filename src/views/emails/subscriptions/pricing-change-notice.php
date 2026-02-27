@component('mail::message')

# Your subscription price is changing

Hi {{ $member->first_name ?? 'there' }},

We're writing to let you know that the price of your **{{ $planName }}** subscription will change on **{{ $effectiveDate }}**.

---

@component('mail::panel')
**Current price:** {{ $currency }} {{ number_format($oldPrice, 2) }}

**New price from {{ $effectiveDate }}:** {{ $currency }} {{ number_format($newPrice, 2) }}
@endcomponent

---

## What this means for you

Your next payment on or after **{{ $effectiveDate }}** will be charged at the new price of **{{ $currency }} {{ number_format($newPrice, 2) }}**.

Payments before that date are unaffected.

## Your right to cancel

If you don't wish to continue at the new price, you can cancel at any time before **{{ $effectiveDate }}** and you won't be charged the new rate.

@component('mail::button', ['url' => $cancellationUrl, 'color' => 'white'])
Cancel my subscription
@endcomponent

Or, to manage your subscription (including updating payment details or changing your plan):

@component('mail::button', ['url' => $managementUrl])
Manage my subscription
@endcomponent

If you have any questions, please contact our support team.

Thanks,
{{ config('app.name') }}

---
<small>You're receiving this email because you have an active {{ $planName }} subscription. Our registered details are
    available in our [legal notices](/legal/company-details).</small>

@endcomponent