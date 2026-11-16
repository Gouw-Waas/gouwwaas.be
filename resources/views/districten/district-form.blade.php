@extends('layouts.app')

@section('title', 'District '.$district->name().' bewerken')

@section('content')
    <section class="page-header">
        <div class="container">
            <p>
                <a href="{{ route('districten') }}#district-{{ $district->key }}" class="back-link">Terug naar de kaart</a>
            </p>
            <h1>District {{ $district->name() }} bewerken</h1>
        </div>
    </section>

    <section class="container section">
        <form method="POST" action="{{ route('districten.update', $district->key) }}" enctype="multipart/form-data" class="form activity-form">
            @csrf
            @method('PUT')

            @if ($errors->any())
                <p class="flash flash-error" role="alert">Niet alles is correct ingevuld. Bekijk de velden hieronder.</p>
            @endif

            <div class="card">
                <h2>Logo</h2>
                <p class="muted">
                    Verschijnt rechtsboven in de zijbalk van het district op de kaart.
                    De districtsploeg zelf pas je aan op <a href="{{ route('wie-is-wie') }}">wie is wie</a>.
                </p>

                @include('districten._logo-field', ['logoUrl' => $district->logoUrl()])
            </div>

            <div class="form-actions">
                <button type="submit" class="button">Opslaan</button>
                <a href="{{ route('districten') }}#district-{{ $district->key }}" class="button button-ghost">Annuleren</a>
            </div>
        </form>
    </section>
@endsection
