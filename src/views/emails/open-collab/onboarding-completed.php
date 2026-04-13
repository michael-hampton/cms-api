# You're all set!

Hi {{ $contributor->name }},

Congratulations! Your account setup is complete. You can now access your dashboard and start creating articles.

@table(Status|Action)
@row(Identity|Verified)
@row(Payout Method|Configured)
@row(Account Status|Active)
@endtable

@button(Go to Dashboard, {{ $dashboardUrl }})

@subcopy(Ready to write? [Create your first article]({{ $createUrl }}))