@extends('layouts.app')

@section('title', 'Attest')

{{-- Organisatie → Attest: the stamp and signers on participation certificates (admins). --}}
@section('content')
    <section class="page-header">
        <div class="container">
            <h1>Organisatie</h1>
        </div>
        @include('organisatie._tabs')
    </section>

    <section class="container section">
        @unless ($problems)
            <div class="section-actions">
                <a href="{{ route('attest.voorbeeld') }}" class="button button-ghost" target="_blank" rel="noopener">Voorbeeld bekijken</a>
            </div>
        @endunless

        @error('certificate')
            <p class="flash flash-error" role="alert">{{ $message }}</p>
        @enderror
        @foreach ($problems as $problem)
            <p class="flash flash-error" role="alert">{{ $problem }}</p>
        @endforeach

        <p class="lead">
            Activiteiten met vormingsuren geven aanwezige deelnemers een deelnameattest (PDF). Hier beheer je wat erop staat; de naam en het adres van de organisatie komen uit <a href="{{ route('organisatie.edit') }}">Gegevens</a>.
        </p>

        <div class="card">
            <h2>Stempel</h2>
            @if ($stamp)
                <p><img src="{{ $stamp }}" alt="Huidige stempel" class="stamp-preview"></p>
            @endif
            <form method="POST" action="{{ route('attest.instellingen.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="field">
                    <label for="stamp">{{ $stamp ? 'Andere stempel opladen' : 'Stempel opladen' }}</label>
                    <input id="stamp" type="file" name="stamp" accept="image/png" required aria-describedby="stamp-hint @error('stamp') stamp-error @enderror" @error('stamp') aria-invalid="true" @enderror>
                    <p class="hint" id="stamp-hint">PNG, maximaal 2 MB, liefst met een doorzichtige achtergrond.</p>
                    @error('stamp')
                        <p class="error" id="stamp-error">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="button">Opslaan</button>
            </form>
        </div>

        <div class="card">
            <h2>Ondertekend door</h2>
            @if ($signers->isNotEmpty())
                <ul>
                    @foreach ($signers as $signer)
                        <li>{{ $signer }}</li>
                    @endforeach
                </ul>
            @endif
            <p class="hint">Iedereen met de functie Gouwcommissaris op <a href="{{ route('wie-is-wie') }}">Wie is wie</a>. Pas het daar aan.</p>
        </div>
    </section>
@endsection
