@extends('layouts.app')

@section('title', 'Inschrijvingen · '.$activity->title)

@section('content')
    <section class="page-header">
        <div class="container">
            <p><a href="{{ route('activiteiten.show', $activity) }}" class="back-link">{{ $activity->title }}</a></p>
            <div class="page-header-row">
                <h1>Inschrijvingen</h1>
                <div class="header-actions">
                    <a href="{{ route('activiteiten.inschrijvingen.create', $activity) }}" class="button">Inschrijving toevoegen</a>
                    @if ($activity->registrations->isNotEmpty())
                        <form method="POST" action="{{ route('activiteiten.aanwezigheid.alle', $activity) }}">
                            @csrf
                            <button type="submit" class="button button-ghost">Iedereen aanwezig</button>
                        </form>
                        <a href="{{ route('activiteiten.mail', $activity) }}" class="button button-ghost">Deelnemers mailen</a>
                        <a href="{{ route('activiteiten.inschrijvingen.export', $activity) }}" class="button button-ghost">Download (Excel)</a>
                    @endif
                </div>
            </div>
            @php
                $confirmed = $activity->registrations->filter->isConfirmed()->count();
                $unfinished = $activity->registrations->count() - $confirmed;
                // Added by an organiser, paying later.
                $toPay = $activity->registrations->filter(fn ($registration) => $registration->isManual() && $registration->canBeMarkedPaid())->count();
            @endphp
            <p class="lead">
                {{ $confirmed }} {{ $confirmed === 1 ? 'inschrijving' : 'inschrijvingen' }}
                @if ($toPay)
                    <span class="muted">(waarvan {{ $toPay }} nog te betalen)</span>
                @endif
                @if ($unfinished)
                    <span class="muted">(+ {{ $unfinished }} niet betaald)</span>
                @endif
                · {{ $activity->dateLabel() }}
            </p>
            @if ($activity->capacity)
                @php($taken = $activity->takenPlaces())
                <p @class(['places', 'places-full' => $taken >= $activity->capacity])>
                    <strong>{{ $taken }} / {{ $activity->capacity }}</strong> plaatsen bezet{{ $taken >= $activity->capacity ? ': volzet' : '' }}
                    @if ($taken > $confirmed)
                        <span class="muted">(waarvan {{ $taken - $confirmed }} met een online betaling bezig)</span>
                    @endif
                </p>
            @endif
        </div>
    </section>

    <section class="container section">
        @error('cancel')
            <p class="flash flash-error" role="alert">{{ $message }}</p>
        @enderror
        @if ($errors->hasAny(['refund', 'admin_cost']))
            <p class="flash flash-error" role="alert">{{ $errors->first('refund') ?: $errors->first('admin_cost') }}</p>
        @endif

        @if ($activity->registrations->isEmpty())
            <div class="empty-state">
                <p>Nog niemand ingeschreven.</p>
            </div>
        @else
            <div class="table-scroll" tabindex="0" role="region" aria-label="Inschrijvingen">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Ingeschreven op</th>
                            <th scope="col">Naam</th>
                            <th scope="col">Aanwezig</th>
                            <th scope="col">E-mailadres</th>
                            @unless ($activity->isFree() && $activity->registrations->every->isFree())
                                <th scope="col">Betaling</th>
                            @endunless
                            @foreach ($columns as $column)
                                <th scope="col">{{ $column['label'] }}</th>
                            @endforeach
                            <th scope="col"><span class="visually-hidden">Acties</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($activity->registrations as $registration)
                            <tr @class(['row-unconfirmed' => ! $registration->isConfirmed()])>
                                <td class="nowrap">{{ $registration->created_at->format('d/m/Y H:i') }}</td>
                                <td class="nowrap">{{ $registration->name }}</td>
                                <td class="nowrap attendance">
                                    <form method="POST" action="{{ route('activiteiten.aanwezigheid', [$activity, $registration->id]) }}">
                                        @csrf
                                        <input type="hidden" name="present" value="0">
                                        <label class="checkbox">
                                            <input type="checkbox" name="present" value="1" @checked($registration->isPresent()) data-autosubmit>
                                            <span class="visually-hidden">{{ $registration->name }} aanwezig</span>
                                        </label>
                                        <noscript><button type="submit" class="button button-ghost">Opslaan</button></noscript>
                                    </form>
                                </td>
                                <td class="nowrap"><a href="mailto:{{ $registration->email }}">{{ $registration->email }}</a></td>
                                @unless ($activity->isFree() && $activity->registrations->every->isFree())
                                    <td class="nowrap">
                                        <span @class(['badge', 'badge-open' => ! $registration->isConfirmed()])>{{ $registration->paymentLabel() }}</span>
                                        @unless ($registration->isFree())
                                            <span class="muted small">{{ $registration->amountLabel() }}</span>
                                        @endunless
                                        @if ($registration->canBeMarkedPaid())
                                            @include('activiteiten._mark-paid', ['registration' => $registration])
                                        @endif
                                    </td>
                                @endunless
                                @foreach ($columns as $column)
                                    <td>{{ $registration->answerFor($column['id']) }}</td>
                                @endforeach
                                <td class="nowrap">
                                    @include('activiteiten._cancel-registration', ['registration' => $registration])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @unless ($activity->isFree() && $activity->registrations->every->isFree())
                @php($revenue = $activity->revenue())
                @php($count = fn (int $n) => $n === 1 ? '1 inschrijving' : "{$n} inschrijvingen")
                <section class="card revenue" aria-labelledby="revenue-title">
                    <h2 id="revenue-title">Financieel overzicht</h2>
                    <dl class="revenue-lines">
                        <div>
                            <dt>Online betaald <span class="muted">({{ $count($revenue['online_count']) }})</span></dt>
                            <dd>{{ \App\Models\Activity::euro($revenue['online']) }}</dd>
                        </div>
                        @if ($revenue['manual_count'])
                            <div>
                                <dt>Manueel betaald <span class="muted">({{ $count($revenue['manual_count']) }}, cash of overschrijving)</span></dt>
                                <dd>{{ \App\Models\Activity::euro($revenue['manual']) }}</dd>
                            </div>
                        @endif
                        <div class="revenue-total">
                            <dt>Totale inkomsten</dt>
                            <dd>{{ \App\Models\Activity::euro($revenue['total']) }}</dd>
                        </div>
                        @if ($revenue['outstanding_count'])
                            <div class="revenue-outstanding">
                                <dt>Nog te ontvangen <span class="muted">({{ $count($revenue['outstanding_count']) }}, manueel toegevoegd)</span></dt>
                                <dd>{{ \App\Models\Activity::euro($revenue['outstanding']) }}</dd>
                            </div>
                        @endif
                    </dl>
                    <p class="hint">
                        Online betalingen zijn de bedragen vóór de transactiekosten van Mollie. Geannuleerde inschrijvingen
                        tellen niet mee (ook niet ingehouden administratiekosten).
                    </p>
                </section>
            @endunless
        @endif
    </section>

    <script nonce="{{ $cspNonce ?? '' }}">
        // Refund dialog: show what will be refunded, minus the administrative costs when chosen.
        // After DOMContentLoaded: the dialog is in the layout, below this content.
        document.addEventListener('DOMContentLoaded', () => {
            const dialog = document.querySelector('[data-confirm-dialog]');
            if (! dialog) return;
            const euro = (cents) => '€ ' + (cents / 100).toLocaleString('nl-BE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            const update = () => {
                const box = dialog.querySelector('[data-refund]');
                if (! box) return;
                const amount = Number(box.dataset.refundAmount);
                const minusCosts = box.querySelector('input[value=minus_costs]').checked;
                const costInput = box.querySelector('[data-refund-cost]');
                const cost = Math.round(parseFloat(costInput.value.replace(',', '.')) * 100);
                const valid = ! minusCosts || (Number.isFinite(cost) && cost >= 0 && cost < amount);

                box.querySelector('[data-refund-total]').textContent = valid ? euro(minusCosts ? amount - cost : amount) : 'ongeldig bedrag';
                costInput.toggleAttribute('aria-invalid', ! valid);
                dialog.querySelector('[data-confirm-ok]').disabled = ! valid;
            };

            dialog.addEventListener('input', update);
            dialog.addEventListener('change', update);
            dialog.addEventListener('confirm:open', () => requestAnimationFrame(update));
            // Typing in the costs field picks that option.
            dialog.addEventListener('focusin', (event) => {
                if (event.target.matches('[data-refund-cost]')) {
                    event.target.closest('[data-refund]').querySelector('input[value=minus_costs]').checked = true;
                    update();
                }
            });
        });
    </script>
@endsection
