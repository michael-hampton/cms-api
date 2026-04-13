# {{ $isResend ? 'Your new invitation link' : "You've been invited to contribute" }}

@if($isResend)
We've generated a fresh invitation link for you. Your previous link is no longer valid.
@else
Someone has invited you to become a contributor. Click the button below to create your account and get started.
@endif

@button(Accept invitation, {{ $acceptUrl }})

**This link expires on {{ $expiresAt instanceof \DateTimeInterface ? $expiresAt->format('d M Y \a\t H:i') : $expiresAt }}.**

If you weren't expecting this invitation, you can safely ignore this email.

@divider

@subcopy(If the button above doesn't work, copy and paste this link into your browser: {{ $acceptUrl }})