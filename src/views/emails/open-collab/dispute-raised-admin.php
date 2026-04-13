# New earnings dispute raised

An earnings dispute has been raised by **{{ $contributor->name }}**.

@panel(
**Reason for dispute:**
{{ $reason }}
)

@table(Detail|Value)
@row(Contributor|{{ $contributor->name }})
@row(Email|{{ $contributor->email }})
@row(Dispute ID|#{{ $dispute->id }})
@row(Raised At|{{ $dispute->created_at->format('d M Y H:i') }})
@endtable

@button(Review Dispute, {{ $adminUrl }})