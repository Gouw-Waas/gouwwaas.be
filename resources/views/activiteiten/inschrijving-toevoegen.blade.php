@extends('layouts.app')

@section('title', 'Inschrijving toevoegen · '.$activity->title)

{{-- Organisers adding someone themselves (signed up by phone, on paper…). --}}
@section('content')
    <section class="page-header">
        <div class="container">
            <p><a href="{{ route('activiteiten.inschrijvingen', $activity) }}" class="back-link">Inschrijvingen</a></p>
            <h1>Inschrijving toevoegen</h1>
            <p class="lead">{{ $activity->title }} · {{ $activity->dateLabel() }}</p>
        </div>
    </section>

    <section class="container section">
        <form method="POST" action="{{ route('activiteiten.inschrijvingen.store', $activity) }}" class="form activity-form">
            @csrf

            @if ($errors->any())
                <p class="flash flash-error" role="alert">Niet alles is correct ingevuld. Bekijk de velden hieronder.</p>
            @endif

            @if ($activity->isFull())
                <p class="flash flash-warning" role="status">
                    Deze activiteit is volzet ({{ $activity->capacity }} plaatsen). Je kan toch iemand toevoegen:
                    die komt dan boven het maximum.
                </p>
            @endif

            <div class="card">
                <h2>Deelnemer</h2>

                <div class="field">
                    <label for="reg-name">Naam <span class="required" aria-hidden="true">*</span></label>
                    <input id="reg-name" type="text" name="name" value="{{ old('name') }}" required maxlength="255" autocomplete="off" @error('name') aria-invalid="true" aria-describedby="reg-name-error" @enderror>
                    @error('name')
                        <p class="error" id="reg-name-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="reg-email">E-mailadres <span class="required" aria-hidden="true">*</span></label>
                    <input id="reg-email" type="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="off" aria-describedby="reg-email-hint @error('email') reg-email-error @enderror" @error('email') aria-invalid="true" @enderror>
                    <p class="hint" id="reg-email-hint">Voor de bevestiging en berichten aan alle deelnemers.</p>
                    @error('email')
                        <p class="error" id="reg-email-error">{{ $message }}</p>
                    @enderror
                </div>

                @if ($activity->fields->isNotEmpty())
                    <p class="muted small">De vragen hieronder zijn niet verplicht: vul in wat je weet.</p>
                    @include('activiteiten._answer-fields', ['fields' => $activity->fields, 'optional' => true])
                @endif
            </div>

            <div class="card">
                <h2>{{ $activity->isFree() ? 'Bevestiging' : 'Betaling' }}</h2>

                @unless ($activity->isFree())
                    <fieldset class="refund-options">
                        <legend class="visually-hidden">Betaling van {{ $activity->priceLabel() }}</legend>

                        <label class="refund-option">
                            <input type="radio" name="paid" value="1" @checked(old('paid') === '1') required>
                            <span>
                                <strong>Betaald</strong>
                                <span class="muted">{{ $activity->priceLabel() }} cash, via overschrijving of op een andere manier ontvangen</span>
                            </span>
                        </label>

                        <label class="refund-option">
                            <input type="radio" name="paid" value="0" @checked(old('paid') === '0')>
                            <span>
                                <strong>Nog niet betaald</strong>
                                <span class="muted">Betaalt later, zoals met jou afgesproken. Je kan het later op betaald zetten.</span>
                            </span>
                        </label>
                    </fieldset>
                    @error('paid')
                        <p class="error">{{ $message }}</p>
                    @enderror
                    <p class="hint">Betalingen buiten de website om lopen niet via Mollie: bij annuleren regel je de terugbetaling zelf.</p>
                @endunless

                <label class="checkbox">
                    <input type="hidden" name="send_confirmation" value="0">
                    <input type="checkbox" name="send_confirmation" value="1" @checked(old('send_confirmation', '1') === '1')>
                    <span>Stuur een bevestigingsmail naar de deelnemer</span>
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="button">Inschrijving toevoegen</button>
                <a href="{{ route('activiteiten.inschrijvingen', $activity) }}" class="button button-ghost">Annuleren</a>
            </div>
        </form>
    </section>
@endsection
