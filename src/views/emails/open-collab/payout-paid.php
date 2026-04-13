# Your payout has been sent

Hi {{ $contributor->name }},

Your payout of **{{ $amount }}** has been sent. Depending on your bank it may take 1–2 business days to appear in your account.

@table(Detail|Value)
@row(Amount|{{ $amount }})
@row(Method|{{ $method }})
@row(Processed|{{ $processedDate }})
@if(!empty($reference))
@row(Reference|{{ $reference }})
@endif
@endtable

@button(View your payouts, {{ $payoutsUrl }})

@subcopy(Keep this reference number for your records. If funds have not arrived after 3 business days, please contact your account manager.)