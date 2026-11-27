<x-mail::message>
# Je deelnameattest

Dag {{ $registration->name }},

Je deelnameattest voor **{{ $activity->title }}** ({{ $activity->dateLabel() }}) zit in bijlage.
Je kunt het gebruiken als bewijs van deelname, bijvoorbeeld bij je gemeente.

Groeten,<br>
{{ config('gouw.organisation.name') }}

<x-mail::subcopy>
Je krijgt deze e-mail omdat je aanwezig was op {{ $activity->title }}. Vragen? Antwoord gewoon op deze e-mail.
</x-mail::subcopy>
</x-mail::message>
