# Earnings adjustment applied

Hi {{ $contributor->name }},

An adjustment has been applied to your account following the resolution of dispute **#{{ $dispute->id }}**.

@panel(
**Adjustment Amount:** {{ $sign }}{{ $amount }} {{ $currency }}
)

The updated balance should be reflected in your contributor dashboard immediately.

@table(Detail|Value)
@row(Type|{{ $isCredit ? 'Credit' : 'Debit' }})
@row(Dispute Reference|#{{ $dispute->id }})
@row(Adjustment|{{ $sign }}{{ $amount }})
@endtable

@button(View Earnings, {{ $earningsUrl }})

@subcopy(If this adjustment does not match your expectations, please reach out to support.)