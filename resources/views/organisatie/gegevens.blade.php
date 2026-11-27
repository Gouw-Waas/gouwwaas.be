@extends('layouts.app')

@section('title', 'Organisatie')

{{-- Organisatie → Gegevens: name, address and e-mail of the organisation (admins). --}}
@section('content')
    <section class="page-header">
        <div class="container">
            <h1>Organisatie</h1>
        </div>
        @include('organisatie._tabs')
    </section>

    <section class="container section">
        <form method="POST" action="{{ route('organisatie.update') }}" class="card form-card form-card-left form">
            @csrf
            @method('PUT')

            <p class="muted">
                Staat op de deelnameattesten, onderaan elke pagina en in de privacyverklaring.
                Antwoorden op een attest-e-mail gaan naar dit e-mailadres.
            </p>

            @foreach ([
                'name' => ['Korte naam', 'text', 100, 'organization', 'bv. Gouw Waas'],
                'full_name' => ['Volledige naam', 'text', 255, 'off', 'bv. Gouw Waas – Scouts en Gidsen Vlaanderen'],
                'building' => ['Gebouw (optioneel)', 'text', 255, 'off', 'bv. Gouwhuis Karree'],
                'street' => ['Straat en nummer', 'text', 255, 'street-address', null],
                'city' => ['Postcode en gemeente', 'text', 255, 'off', 'bv. 9100 Sint-Niklaas'],
                'email' => ['E-mailadres', 'email', 255, 'email', null],
            ] as $field => [$label, $type, $max, $autocomplete, $placeholder])
                <div class="field">
                    <label for="{{ $field }}">{{ $label }}</label>
                    <input id="{{ $field }}" type="{{ $type }}" name="{{ $field }}" value="{{ old($field, $organisation->{$field}) }}" @if ($field !== 'building') required @endif maxlength="{{ $max }}" autocomplete="{{ $autocomplete }}" @if ($placeholder) placeholder="{{ $placeholder }}" @endif @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>
                    @error($field)
                        <p class="error" id="{{ $field }}-error">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach

            <button type="submit" class="button">Opslaan</button>
        </form>
    </section>
@endsection
