# Article not approved

Hi {{ $user->name }},

Unfortunately, your article **"{{ $article->title }}"** was not approved.

@if($reason)
**Reason:**
{{ $reason }}
@endif

You can review and resubmit once updated.

Thanks,
— The OpenCollab Team