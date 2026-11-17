<x-mail::message>
@if ($activityCancelled)
# Deze activiteit gaat niet door

Dag {{ $registration->name }},

Helaas gaat **{{ $activity->title }}** ({{ $activity->dateLabel() }}) niet door. Je inschrijving is daarom geannuleerd.
@else
# Je inschrijving is geannuleerd

Dag {{ $registration->name }},

Je inschrijving voor **{{ $activity->title }}** ({{ $activity->dateLabel() }}) is geannuleerd.
@endif

@if ($refundedCents > 0)
<x-mail::panel>
We betalen **{{ $refunded }}** terug{{ $adminCostCents > 0 ? " ({$adminCost} administratiekosten ingehouden)" : '' }}.
Het bedrag staat binnen enkele werkdagen terug op je rekening.
</x-mail::panel>
@endif

Vragen? Antwoord op deze e-mail of stuur een mail naar [{{ config('mail.from.address') }}](mailto:{{ config('mail.from.address') }}).

Groeten,<br>
{{ config('app.name') }}
</x-mail::message>
