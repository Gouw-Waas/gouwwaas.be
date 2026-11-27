@extends('layouts.app')

@section('title', 'Attest')

@section('content')
    <section class="page-header">
        <div class="container page-header-row">
            <h1>Deelnameattest</h1>
            @unless ($problems)
                <a href="{{ route('attest.voorbeeld') }}" class="button button-ghost" target="_blank" rel="noopener">Voorbeeld bekijken</a>
            @endunless
        </div>
    </section>

    <section class="container section">
        @error('certificate')
            <p class="flash flash-error" role="alert">{{ $message }}</p>
        @enderror
        @foreach ($problems as $problem)
            <p class="flash flash-error" role="alert">{{ $problem }}</p>
        @endforeach

        <p class="lead">
            Activiteiten met vormingsuren geven aanwezige deelnemers een deelnameattest (PDF). Hier beheer je wat erop staat.
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

        <div class="card">
            <h2>Organisatie</h2>
            <p>
                {{ $organisation['full_name'] }}<br>
                {{ $organisation['building'] }}, {{ $organisation['street'] }}, {{ $organisation['city'] }}<br>
                {{ $organisation['email'] }}
            </p>
            <p class="hint">Deze gegevens staan in <code>config/gouw.php</code> (vraag het aan de webmaster om ze te wijzigen).</p>
        </div>
    </section>
@endsection
