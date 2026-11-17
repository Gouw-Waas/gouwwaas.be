@extends('layouts.app')

@section('title', 'Deelnemers mailen · '.$activity->title)

{{-- Organisers e-mailing everyone who registered. --}}
@section('content')
    <section class="page-header">
        <div class="container">
            <p><a href="{{ route('activiteiten.inschrijvingen', $activity) }}" class="back-link">Inschrijvingen</a></p>
            <h1>Deelnemers mailen</h1>
            <p class="lead">{{ $activity->title }} · {{ $activity->dateLabel() }}</p>
        </div>
    </section>

    <section class="container section">
        @if ($allCount === 0)
            <div class="empty-state">
                <p>Nog niemand ingeschreven, dus er is niemand om te mailen.</p>
            </div>
        @else
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
                        Naar <strong>{{ $confirmedCount }} {{ $confirmedCount === 1 ? 'deelnemer' : 'deelnemers' }}</strong>,
                        elk een eigen e-mail: deelnemers zien elkaars adres niet.
                    </p>

                    @if ($allCount > $confirmedCount)
                        @php($unconfirmed = $allCount - $confirmedCount)
                        <label class="checkbox">
                            <input type="checkbox" name="include_unconfirmed" value="1" @checked(old('include_unconfirmed')) data-mail-unconfirmed>
                            <span>Ook naar wie de online betaling niet afrondde ({{ $unconfirmed }})</span>
                        </label>
                    @endif

                    <label class="checkbox">
                        <input type="hidden" name="send_copy" value="0">
                        <input type="checkbox" name="send_copy" value="1" @checked(old('send_copy', '1') === '1')>
                        <span>Stuur mij een kopie</span>
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="button">Versturen</button>
                    <a href="{{ route('activiteiten.inschrijvingen', $activity) }}" class="button button-ghost">Annuleren</a>
                </div>
            </form>

            <template id="mail-deelnemers-details">
                <p>
                    Het bericht gaat naar <strong data-mail-count>{{ $confirmedCount }}</strong> e-mailadressen.
                    Eenmaal verstuurd, kan je het niet meer terughalen.
                </p>
            </template>

            <script nonce="{{ $cspNonce ?? '' }}">
                // The confirm dialog names the number of recipients, with or without the unconfirmed ones.
                document.addEventListener('DOMContentLoaded', () => {
                    const dialog = document.querySelector('[data-confirm-dialog]');
                    const toggle = document.querySelector('[data-mail-unconfirmed]');
                    if (! dialog) return;

                    dialog.addEventListener('confirm:open', () => {
                        const count = dialog.querySelector('[data-mail-count]');
                        if (count) count.textContent = toggle && toggle.checked ? @json($allCount) : @json($confirmedCount);
                    });
                });
            </script>
        @endif
    </section>
@endsection
