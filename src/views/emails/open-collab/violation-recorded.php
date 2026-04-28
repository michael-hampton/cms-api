# Policy violation recorded

Hi {{ $user->name }},

A violation has been recorded on your account.

**Type:** {{ $violation->type ?? 'N/A' }}
**Details:** {{ $violation->description ?? 'N/A' }}

Please review your activity to avoid further action.

— The OpenCollab Team