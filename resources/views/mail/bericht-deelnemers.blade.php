<x-mail::message>
@if ($copy)
<x-mail::panel>
Dit is je kopie van het bericht aan de deelnemers van {{ $activity->title }}.
</x-mail::panel>

@endif
# {{ $activity->title }}

@foreach ($paragraphs as $paragraph)
{!! collect(preg_split('/\R/u', $paragraph))->map(fn ($line) => e(trim($line)))->implode("<br>\n") !!}

@endforeach
<x-mail::panel>
**Wanneer:** {{ $activity->dateLabel() }}<br>
**Waar:** {{ $activity->location }}
</x-mail::panel>

<x-mail::button :url="route('activiteiten.show', $activity)">
Bekijk de activiteit
</x-mail::button>

Groeten,<br>
{{ $sender->name }}<br>
{{ config('app.name') }}

<x-mail::subcopy>
Je krijgt deze e-mail omdat je ingeschreven bent voor {{ $activity->title }}. Vragen? Antwoord gewoon op deze e-mail.
</x-mail::subcopy>
</x-mail::message>
