# Good news 🎉

Hi {{ $user->name }},

Your article **"{{ $article->title }}"** has been approved and is now live.

@component('mail::button', ['url' => $url])
View Article
@endcomponent

Thanks for contributing — keep them coming.

— The OpenCollab Team
