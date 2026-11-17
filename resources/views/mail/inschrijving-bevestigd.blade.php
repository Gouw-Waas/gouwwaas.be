<x-mail::message>
# Je bent ingeschreven!

Dag {{ $registration->name }},

Bedankt voor je inschrijving voor **{{ $activity->title }}**. We kijken ernaar uit!

<x-mail::panel>
**Wanneer:** {{ $activity->dateLabel() }}<br>
**Waar:** {{ $activity->location }}
@if ($registration->isPaid())
<br>**Betaald:** {{ $registration->amountLabel() }}
@elseif (! $registration->isFree())
<br>**Nog te betalen:** {{ $registration->amountLabel() }} (zoals afgesproken met de organisatie)
@endif
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

**Zet in je agenda:** [Google Agenda]({{ $calendar->googleUrl() }}) · [Outlook.com]({{ $calendar->outlookUrl() }}) · [Apple of andere agenda (.ics)]({{ $calendar->icsUrl() }}). Het agendabestand zit ook als bijlage bij deze e-mail.

**Toch verhinderd?** Je kan je inschrijving niet zelf annuleren. Wil je annuleren of heb je een vraag, antwoord dan op deze e-mail of stuur een mail naar [{{ config('mail.from.address') }}](mailto:{{ config('mail.from.address') }}).

Tot dan!<br>
{{ config('app.name') }}
</x-mail::message>
