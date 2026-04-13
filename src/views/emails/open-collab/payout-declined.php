# Your payout request could not be processed

Hi {{ $contributor->name }},

Unfortunately we were unable to process your payout request for **{{ $amount }}**.

@if(!empty($reason))
**Reason:** {{ $reason }}
@endif

This is often caused by missing or incorrect payment details. Please review your payout settings and submit a new request once the issue is resolved.

@button(Update payment settings, {{ $settingsUrl }})

@buttonSecondary(View your payouts, {{ $payoutsUrl }})

If you believe this is an error, please contact your account manager.