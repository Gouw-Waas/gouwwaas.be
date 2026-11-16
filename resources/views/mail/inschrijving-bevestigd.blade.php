<x-mail::message>
# Je bent ingeschreven!

Dag {{ $registration->name }},

Bedankt voor je inschrijving voor **{{ $activity->title }}**. We kijken ernaar uit!

<x-mail::panel>
**Wanneer:** {{ $activity->dateLabel() }}<br>
**Waar:** {{ $activity->location }}
@unless ($registration->isFree())
<br>**Betaald:** {{ $registration->amountLabel() }}
@endunless
</x-mail::panel>

@if (count($registration->answers))
**Wat je hebt ingevuld**

@foreach ($registration->answers as $answer)
- {{ $answer['label'] }}: {{ filled($answer['value']) ? $answer['value'] : '–' }}
@endforeach
@endif

<x-mail::button :url="route('activiteiten.show', $activity)">
Bekijk de activiteit
</x-mail::button>

**Toch verhinderd?** Je kan je inschrijving niet zelf annuleren. Wil je annuleren of heb je een vraag, antwoord dan op deze e-mail of stuur een mail naar [{{ config('mail.from.address') }}](mailto:{{ config('mail.from.address') }}).

Tot dan!<br>
{{ config('app.name') }}
</x-mail::message>
