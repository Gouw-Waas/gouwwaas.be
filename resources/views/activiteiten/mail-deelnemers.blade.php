@extends('layouts.app')

@section('title', 'Deelnemers mailen · '.$activity->title)

{{-- Organisers e-mailing the participants they selected on the registrations overview. --}}
@section('content')
    <section class="page-header">
        <div class="container">
            <p><a href="{{ route('activiteiten.inschrijvingen', $activity) }}" class="back-link">Inschrijvingen</a></p>
            <h1>Deelnemers mailen</h1>
            <p class="lead">{{ $activity->title }} · {{ $activity->dateLabel() }}</p>
        </div>
    </section>

    <section class="container section">
        <form method="POST" action="{{ route('activiteiten.mail.send', $activity) }}" class="form activity-form" id="mail-deelnemers"
              data-confirm="Bericht versturen?" data-confirm-details="mail-deelnemers-details"
              data-confirm-button="Versturen" data-confirm-cancel="Terug" data-confirm-tone="neutral">
            @csrf

            @if ($errors->any())
                <p class="flash flash-error" role="alert">{{ $errors->has('message') && ! $errors->has('subject') ? $errors->first('message') : 'Niet alles is correct ingevuld. Bekijk de velden hieronder.' }}</p>
            @endif

            <div class="card">
                <h2>Bericht</h2>

                <div class="field">
                    <label for="subject">Onderwerp</label>
                    <input id="subject" type="text" name="subject" value="{{ old('subject', $activity->title.': praktische info') }}" required maxlength="150" @error('subject') aria-invalid="true" aria-describedby="subject-error" @enderror>
                    @error('subject')
                        <p class="error" id="subject-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="message">Bericht</label>
                    <textarea id="message" name="message" rows="12" required maxlength="10000" aria-describedby="message-hint @error('message') message-error @enderror" @error('message') aria-invalid="true" @enderror>{{ old('message', "Dag allemaal,\n\n") }}</textarea>
                    <p class="hint" id="message-hint">
                        Laat een lege regel tussen alinea's. Wanneer en waar de activiteit is, en een link ernaar,
                        komen er automatisch onder. Antwoorden komen bij jou terecht ({{ auth()->user()->email }}).
                    </p>
                    @error('message')
                        <p class="error" id="message-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="card">
                <h2>Aan wie?</h2>

                <p>
                    Naar <strong>{{ $selection->count() }} geselecteerde {{ $selection->count() === 1 ? 'deelnemer' : 'deelnemers' }}</strong>,
                    elk een eigen e-mail: deelnemers zien elkaars adres niet.
                </p>
                <details class="mail-selection">
                    <summary>Wie?</summary>
                    <ul>
                        @foreach ($selection as $registration)
                            <li>{{ $registration->name }} <span class="muted">({{ $registration->email }})</span></li>
                        @endforeach
                    </ul>
                </details>
                @foreach ($selection as $registration)
                    <input type="hidden" name="ids[]" value="{{ $registration->id }}">
                @endforeach
                @if ($selection->isEmpty())
                    <p class="flash flash-error" role="alert">Niemand van de selectie staat nog ingeschreven. Ga terug naar de inschrijvingen en selecteer opnieuw.</p>
                @endif

                <label class="checkbox">
                    <input type="hidden" name="send_copy" value="0">
                    <input type="checkbox" name="send_copy" value="1" @checked(old('send_copy', '1') === '1')>
                    <span>Stuur mij een kopie</span>
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="button" @disabled($selection->isEmpty())>Versturen</button>
                <a href="{{ route('activiteiten.inschrijvingen', $activity) }}" class="button button-ghost">Annuleren</a>
            </div>
        </form>

        <template id="mail-deelnemers-details">
            <p>
                Het bericht gaat naar <strong>{{ $selection->count() }}</strong> e-mailadressen.
                Eenmaal verstuurd, kan je het niet meer terughalen.
            </p>
        </template>
    </section>
@endsection
