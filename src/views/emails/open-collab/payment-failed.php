# @if($isFailed)Payment unsuccessful@else Your retry is ready@endif

@if($isFailed)
We were unable to process your payment of **{{ $amount }}** for **{{ $page->title }}**.

This can happen if your card was declined or if there was a temporary issue with your bank. You can try again using a different card.
@else
Your previous payment attempt failed, but you can try again now. Click below to complete your purchase of **{{ $page->title }}**.
@endif

@button(Try again, {{ $retryUrl }})

@table(Detail|Value)
@row(Article|{{ $page->title }})
@row(Amount|{{ $amount }})
@endtable

If you continue to experience issues, please contact your card provider or try a different payment method.