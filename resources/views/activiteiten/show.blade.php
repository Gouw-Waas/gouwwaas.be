@extends('layouts.app')

@section('title', $activity->title)
@section('description', \Illuminate\Support\Str::limit($activity->description, 155))
@if ($activity->imageAbsoluteUrl())
    @section('og_image', $activity->imageAbsoluteUrl())
@endif

@section('content')
    @php($image = $activity->imageUrl())
    <section @class(['page-header', 'page-header-image' => $image])
             @if ($image) style="--header-image: url('{{ $image }}')" @endif>
        <div class="container">
            <p><a href="{{ route('activiteiten.index') }}" class="back-link">Alle activiteiten</a></p>
            <h1>{{ $activity->title }}</h1>

            <ul class="facts">
                <li>
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    <span><span class="visually-hidden">Wanneer: </span>{{ $activity->dateLabel() }}</span>
                </li>
                <li>
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/></svg>
                    <span><span class="visually-hidden">Waar: </span>{{ $activity->location }}</span>
                </li>
                <li>
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>
                    <span><span class="visually-hidden">Prijs: </span>{{ $activity->priceLabel() }}</span>
                </li>
                <li>
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                    <span>Inschrijven tot {{ $activity->registration_deadline->translatedFormat('l j F Y, H:i') }}</span>
                </li>
                @if ($activity->capacity)
                    <li>
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        <span><span class="visually-hidden">Plaatsen: </span>{{ $activity->isFull() ? 'Volzet' : $activity->placesLabel().' (van '.$activity->capacity.')' }}</span>
                    </li>
                @endif
            </ul>

            @can('update', $activity)
                <div class="owner-actions">
                    <a href="{{ route('activiteiten.inschrijvingen', $activity) }}" class="button button-small">Inschrijvingen ({{ $activity->registrations()->confirmed()->count() }})</a>
                    <a href="{{ route('activiteiten.edit', $activity) }}" class="button button-ghost button-small">Bewerken</a>
                </div>
            @endcan
        </div>
    </section>

    <section class="container section">
        <div class="activity-layout">
            <article class="prose activity-description">
                {!! $activity->descriptionHtml() !!}
            </article>

            <aside class="card registration-card" id="inschrijven">
                <h2>Inschrijven</h2>

                @error('registration')
                    <p class="flash flash-error" role="alert">{{ $message }}</p>
                @enderror

                @if (! $activity->isRegistrationOpen())
                    <p class="muted">De inschrijvingen voor deze activiteit zijn afgesloten.</p>
                @elseif ($activity->isFull())
                    <p class="muted">Deze activiteit is volzet: er kan niemand meer bij.</p>
                @else
                    <form method="POST" action="{{ route('activiteiten.inschrijven', $activity) }}" class="form">
                        @csrf

                        <div class="field">
                            <label for="reg-name">Naam <span class="required" aria-hidden="true">*</span></label>
                            <input id="reg-name" type="text" name="name" value="{{ old('name') }}" required maxlength="255" autocomplete="name" @error('name') aria-invalid="true" aria-describedby="reg-name-error" @enderror>
                            @error('name')
                                <p class="error" id="reg-name-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="field">
                            <label for="reg-email">E-mailadres <span class="required" aria-hidden="true">*</span></label>
                            <input id="reg-email" type="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email" @error('email') aria-invalid="true" aria-describedby="reg-email-error" @enderror>
                            @error('email')
                                <p class="error" id="reg-email-error">{{ $message }}</p>
                            @enderror
                        </div>

                        @include('activiteiten._answer-fields', ['fields' => $activity->fields])

                        <p class="muted small"><span class="required" aria-hidden="true">*</span> verplicht</p>
                        <p class="muted small">Wat we met je gegevens doen, lees je in onze <a href="{{ route('privacy') }}">privacyverklaring</a>.</p>

                        @unless ($activity->isFree())
                            <p class="muted small">Na het invullen betaal je {{ $activity->priceLabel() }} online. Je inschrijving is pas definitief als de betaling gelukt is.</p>
                        @endunless

                        <button type="submit" class="button button-accent button-block">
                            {{ $activity->isFree() ? 'Inschrijven' : 'Verder naar betalen · '.$activity->priceLabel() }}
                        </button>
                    </form>
                @endif
            </aside>
        </div>
    </section>
@endsection
