# Payout request received

Hi {{ $contributor->name }},

We've received your payout request for **{{ $amount }}** via {{ $method }}. Our team will review it and process payment within 2–5 business days.

@panel(You'll receive another email once your payout has been approved and sent.)

@table(Detail|Value)
@row(Amount|{{ $amount }})
@row(Method|{{ $method }})
@row(Status|Pending review)
@endtable

@button(View your payouts, {{ $payoutsUrl }})

If you have any questions about this payout, please contact your account manager.