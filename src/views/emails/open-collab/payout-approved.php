# Your payout has been approved

Hi {{ $contributor->name }},

Your payout of **{{ $amount }}** has been approved and will be processed shortly. You should receive your funds within 1–3 business days depending on your bank.

@table(Detail|Value)
@row(Amount|{{ $amount }})
@row(Method|{{ $method }})
@row(Status|Approved)
@endtable

@button(View your payouts, {{ $payoutsUrl }})