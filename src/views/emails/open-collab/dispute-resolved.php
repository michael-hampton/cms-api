# {{ $wasApproved ? 'Dispute resolved in your favour' : 'Update on your earnings dispute' }}

Hi {{ $contributor->name }},

@if($wasApproved)
Your dispute ({{ $dispute->id }}) has been reviewed and resolved in your favour. Any necessary adjustments have been applied to your ledger.
@else
Our team has finished reviewing your dispute ({{ $dispute->id }}). Unfortunately, we are unable to approve an adjustment at this time.
@endif

@if(!empty($adminNotes))
@panel(
**Admin Notes:**
{{ $adminNotes }}
)
@endif

@table(Detail|Value)
@row(Dispute ID|#{{ $dispute->id }})
@row(Resolution|{{ $wasApproved ? 'Approved' : 'Rejected' }})
@endtable

@button(View Earnings, {{ $earningsUrl }})

If you have further questions regarding this resolution, please contact your account manager.