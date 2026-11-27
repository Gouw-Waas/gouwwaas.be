<x-mail::message>
# Je deelnameattest

Dag {{ $registration->name }},

{{-- An Extern attest activity isn't stored and has dates without times: show just the dates. --}}
Je deelnameattest voor **{{ $activity->title }}** ({{ $activity->exists ? $activity->dateLabel() : app(\App\Certificates\CertificateRenderer::class)->dateText($activity) }}) zit in bijlage.
Je kunt het gebruiken als bewijs van deelname, bijvoorbeeld bij je gemeente.

Groeten,<br>
{{ \App\Models\Organisation::current()->name }}

<x-mail::subcopy>
Je krijgt deze e-mail omdat je aanwezig was op {{ $activity->title }}. Vragen? Antwoord gewoon op deze e-mail.
</x-mail::subcopy>
</x-mail::message>
