@extends('layouts.app')

@section('title', $registration->isConfirmed() ? 'Ingeschreven' : 'Betalen')

{{-- A registration's own page: checkout for paid activities, the page Mollie sends people back to,
     and the confirmation. Only reachable with the registration's secret token. --}}
@section('content')
    <section class="page-header">
        <div class="container">
            <p><a href="{{ route('activiteiten.show', $activity) }}" class="back-link">{{ $activity->title }}</a></p>
            <h1>{{ $registration->isConfirmed() ? 'Je bent ingeschreven!' : ($registration->isFree() ? 'Inschrijving' : 'Betalen') }}</h1>
        </div>
    </section>

    <section class="container section">
        <div class="checkout">
            <div class="checkout-main">
                @error('payment')
                    <p class="flash flash-error" role="alert">{{ $message }}</p>
                @enderror

                @if ($registration->isConfirmed())
                    <div class="checkout-status checkout-status-ok" role="status">
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m8 12 3 3 5-6"/></svg>
                        <div>
                            <h2>Bedankt {{ $registration->name }}!</h2>
                            <p>
                                @if ($registration->isPaid() && ! $registration->paid_manually)
                                    Je betaling van {{ $registration->amountLabel() }} is gelukt.
                                @endif
                                Je inschrijving voor <strong>{{ $activity->title }}</strong> is in orde.
                                @if ($registration->confirmation_sent_at)
                                    We stuurden een bevestiging naar <strong>{{ $registration->email }}</strong>.
                                @endif
                            </p>
                            @php($calendar = \App\Support\ActivityCalendar::for($activity))
                            <div class="calendar-links">
                                <p class="calendar-links-title">Zet in je agenda</p>
                                <div class="calendar-buttons">
                                    <a href="{{ $calendar->googleUrl() }}" target="_blank" rel="noopener" class="button button-ghost button-small calendar-button">
                                        <img src="{{ asset('images/agenda/google.svg') }}" alt="" width="20" height="20"> Google Agenda
                                    </a>
                                    <a href="{{ $calendar->outlookUrl() }}" target="_blank" rel="noopener" class="button button-ghost button-small calendar-button">
                                        <img src="{{ asset('images/agenda/outlook.svg') }}" alt="" width="20" height="20"> Outlook
                                    </a>
                                    <a href="{{ $calendar->icsUrl() }}" class="button button-ghost button-small calendar-button">
                                        <img src="{{ asset('images/agenda/ics.svg') }}" alt="" width="20" height="20"> Apple / andere (.ics)
                                    </a>
                                </div>
                            </div>
                            <p class="small">
                                Toch verhinderd? Annuleren kan niet online: stuur een mail naar
                                <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.
                            </p>
                        </div>
                    </div>
                @elseif ($registration->paymentProcessing())
                    <div class="checkout-status checkout-status-wait" role="status">
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        <div>
                            <h2>Je betaling wordt verwerkt</h2>
                            <p>
                                We wachten nog op de bevestiging van je bank. Zodra die er is, ben je ingeschreven en
                                krijg je een mail op <strong>{{ $registration->email }}</strong>.
                            </p>
                            <p><a href="{{ route('inschrijvingen.show', $registration) }}">Opnieuw controleren</a></p>
                        </div>
                    </div>
                @else
                    @if ($paymentError)
                        <p class="flash flash-warning" role="status">
                            We konden de status van je betaling nu niet ophalen. Probeer het over enkele minuten opnieuw
                            door deze pagina te vernieuwen.
                        </p>
                    @elseif ($registration->paymentFailed())
                        <div class="checkout-status checkout-status-error" role="alert">
                            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></svg>
                            <div>
                                <h2>
                                    {{ match ($registration->payment_status) {
                                        'canceled' => 'De betaling is geannuleerd',
                                        'expired' => 'De betaling is verlopen',
                                        default => 'De betaling is mislukt',
                                    } }}
                                </h2>
                                <p>Er is niets aangerekend en je bent nog <strong>niet</strong> ingeschreven. Je kan het gewoon opnieuw proberen.</p>
                            </div>
                        </div>
                    @elseif ($registration->payment_status === 'open')
                        <div class="checkout-status checkout-status-wait" role="status">
                            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                            <div>
                                <h2>Je betaling is nog niet afgerond</h2>
                                <p>Je bent nog niet ingeschreven. Rond de betaling af om je plaats vast te leggen.</p>
                            </div>
                        </div>
                    @else
                        <p class="lead checkout-intro">Nog één stap: controleer je gegevens en betaal online om je inschrijving af te ronden.</p>
                    @endif

                    @if ($activity->isRegistrationOpen() && $activity->isFull() && ! in_array($registration->payment_status, \App\Models\Registration::IN_PROGRESS_STATUSES, true))
                        <p class="flash flash-error" role="alert">Deze activiteit is intussen volzet. Er is niets aangerekend.</p>
                    @elseif ($activity->isRegistrationOpen())
                        <form method="POST" action="{{ route('inschrijvingen.betalen', $registration) }}" class="checkout-pay">
                            @csrf
                            @php($payLabel = $registration->payment_status === 'open' ? 'Verder betalen' : ($registration->paymentFailed() ? 'Opnieuw proberen' : 'Betalen'))
                            <button type="submit" class="button button-accent">{{ $payLabel }} · {{ $registration->amountLabel() }}</button>
                            <p class="muted small">Je betaalt veilig via Mollie, met Bancontact, Payconiq, een kredietkaart of een andere methode.</p>
                        </form>
                    @else
                        <p class="flash flash-error" role="alert">De inschrijvingen voor deze activiteit zijn afgesloten.</p>
                    @endif
                @endif
            </div>

            <aside class="card checkout-summary">
                <h2>Overzicht</h2>
                <dl class="details">
                    <div><dt>Activiteit</dt><dd>{{ $activity->title }}</dd></div>
                    <div><dt>Wanneer</dt><dd>{{ $activity->dateLabel() }}</dd></div>
                    <div><dt>Waar</dt><dd>{{ $activity->location }}</dd></div>
                    <div><dt>Naam</dt><dd>{{ $registration->name }}</dd></div>
                    <div><dt>E-mailadres</dt><dd>{{ $registration->email }}</dd></div>
                    @foreach ($registration->answers as $answer)
                        <div><dt>{{ $answer['label'] }}</dt><dd>{{ filled($answer['value']) ? $answer['value'] : '–' }}</dd></div>
                    @endforeach
                </dl>
                <p class="checkout-total">
                    <span>{{ $registration->isFree() ? 'Prijs' : ($registration->isPaid() ? 'Betaald' : 'Te betalen') }}</span>
                    <strong>{{ $registration->isFree() ? 'Gratis' : $registration->amountLabel() }}</strong>
                </p>
            </aside>
        </div>
    </section>
@endsection
