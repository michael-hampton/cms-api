# New contract available

Hi {{ $user->name }},

A new contract is ready for your review.

@component('mail::button', ['url' => $url])
View Contract
@endcomponent

Please review it as soon as possible.

— The OpenCollab Team