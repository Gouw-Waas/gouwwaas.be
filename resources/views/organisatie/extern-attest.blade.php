@extends('layouts.app')

@section('title', 'Extern attest')

{{-- Organisatie → Extern attest: certificates for an activity a subgroup organised, sent from a list (admins). Nothing is stored. --}}
@section('content')
    <section class="page-header">
        <div class="container">
            <h1>Organisatie</h1>
        </div>
        @include('organisatie._tabs')
    </section>

    <section class="container section">
        @error('certificate')
            <p class="flash flash-error" role="alert">{{ $message }}</p>
        @enderror
        @foreach ($problems as $problem)
            <p class="flash flash-error" role="alert">{{ $problem }}</p>
        @endforeach

        <form method="POST" action="{{ route('attest.extern.versturen') }}" class="card form-card form-card-left form" enctype="multipart/form-data"
              data-confirm="Attesten versturen?"
              data-confirm-text="Iedereen in de lijst krijgt een attest per e-mail. Er wordt niets bewaard."
              data-confirm-button="Versturen" data-confirm-tone="neutral" data-submit-once>
            @csrf

            <p class="muted">
                Voor een activiteit die niet wij maar een groep of district organiseerde: iedereen in de lijst krijgt hetzelfde deelnameattest per e-mail als bij onze eigen activiteiten.
                De activiteit en de deelnemers worden nergens bewaard. Wil je later nog een attest sturen, vul het formulier dan opnieuw in.
            </p>

            @foreach ([
                'title' => ['Titel van de activiteit', 'text', 255, true, 'bv. Groepsleidingsweekend'],
                'organiser' => ['Georganiseerd door (optioneel)', 'text', 255, false, 'bv. Scouts Lokeren'],
                'location' => ['Locatie', 'text', 255, true, 'bv. Lokeren'],
                'starts_on' => ['Startdatum', 'date', null, true, null],
                'ends_on' => ['Einddatum (optioneel)', 'date', null, false, null],
                'training_hours' => ['Vormingsuren', 'text', 4, true, 'bv. 7,5'],
                'price' => ['Deelnameprijs in euro (optioneel, 0 voor gratis)', 'text', 7, false, 'bv. 25 of 12,50'],
            ] as $field => [$label, $type, $max, $required, $placeholder])
                <div class="field">
                    <label for="{{ $field }}">{{ $label }}</label>
                    <input id="{{ $field }}" type="{{ $type }}" name="{{ $field }}" value="{{ old($field) }}" @if ($required) required @endif @if ($max) maxlength="{{ $max }}" @endif @if (in_array($field, ['training_hours', 'price'], true)) inputmode="decimal" @endif autocomplete="off" @if ($placeholder) placeholder="{{ $placeholder }}" @endif @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>
                    @error($field)
                        <p class="error" id="{{ $field }}-error">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach

            <div class="field">
                <label for="participants">Deelnemers</label>
                <textarea id="participants" name="participants" rows="10" placeholder="An Peeters;an@example.com&#10;Bert Claes;bert@example.com" aria-describedby="participants-hint @error('participants') participants-error @enderror" @error('participants') aria-invalid="true" @enderror>{{ old('participants') }}</textarea>
                <p class="hint" id="participants-hint">Eén deelnemer per regel: naam en e-mailadres, gescheiden door ; of , (de volgorde maakt niet uit).</p>
                @error('participants')
                    <p class="error" id="participants-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="field">
                <label for="file">Of een CSV-bestand <span class="muted">(optioneel)</span></label>
                <input id="file" type="file" name="file" accept=".csv,text/csv,text/plain" aria-describedby="file-hint @error('file') file-error @enderror" @error('file') aria-invalid="true" @enderror>
                <p class="hint" id="file-hint">Bv. een export uit Excel met een kolom naam en een kolom e-mailadres. Het bestand wordt niet bewaard.</p>
                @error('file')
                    <p class="error" id="file-error">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="button" @disabled($problems)>Attesten versturen</button>
        </form>
    </section>
@endsection
