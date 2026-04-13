# Your access is confirmed

Thank you for your purchase. You now have full access to the article below.

@panel(**{{ $page->title }}**)

@table(Detail|Value)
@row(Amount paid|{{ $amount }})
@row(Status|Confirmed)
@endtable

@button(Read the article, {{ $articleUrl }})

Your access is permanent — you can return to this article any time using the link above or by visiting our site.

@subcopy(If you did not make this purchase, please contact us immediately.)