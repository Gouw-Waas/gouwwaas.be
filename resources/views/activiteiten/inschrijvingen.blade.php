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
                        <a href="{{ route('activiteiten.inschrijvingen.export', $activity) }}" class="button button-ghost">Download (Excel)</a>
                    @endif
                </div>
            </div>
            @php
                $confirmed = $activity->registrations->filter->isConfirmed()->count();
                // Added by an organiser, paying later.
                $toPay = $activity->registrations->filter(fn ($registration) => $registration->isManual() && $registration->canBeMarkedPaid())->count();
                // Certificates (toolbar above the table).
                $sentCount = $activity->registrations->whereNotNull('certificate_sent_at')->count();
            @endphp
            <p class="lead">
                {{ $confirmed }} {{ $confirmed === 1 ? 'inschrijving' : 'inschrijvingen' }}
                @if ($toPay)
                    <span class="muted">(waarvan {{ $toPay }} nog te betalen)</span>
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
        @error('certificate')
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
            @if ($activity->hasCertificates())
                <div class="registrations-toolbar">
                    <div class="toolbar-group">
                        <span class="toolbar-label">
                            Attesten ({{ $activity->trainingHoursLabel() }} vormingsuren)@if ($sentCount): <strong>{{ $sentCount }}</strong> verstuurd @endif
                        </span>
                        @if ($certificateCount)
                            <form method="POST" action="{{ route('activiteiten.attesten.versturen', $activity) }}"
                                  data-confirm="{{ $certificateCount === 1 ? '1 attest' : $certificateCount.' attesten' }} versturen?"
                                  data-confirm-text="Elke aanwezige deelnemer krijgt het eigen attest per e-mail. Wie al een attest kreeg, krijgt het niet nog eens."
                                  data-confirm-button="Versturen"
                                  data-confirm-tone="neutral">
                                @csrf
                                <button type="submit" class="button button-small">Attesten versturen ({{ $certificateCount }})</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Filtering, sorting and selecting: public/js/registrations.js reveals these (hidden without JS). --}}
            <div class="registrations-filters" data-filters hidden>
                <div class="filter-field filter-search">
                    <label for="filter-search" class="visually-hidden">Zoeken</label>
                    <input id="filter-search" type="search" placeholder="Zoek op naam of e-mail" data-filter="search" data-match="contains">
                </div>
                <div class="filter-field">
                    <label for="filter-present">Aanwezig</label>
                    <select id="filter-present" data-filter="present" data-match="equals">
                        <option value="">Alle</option>
                        <option value="1">Aanwezig</option>
                        <option value="0">Niet aanwezig</option>
                    </select>
                </div>
                @if ($activity->involvesMoney())
                    <div class="filter-field">
                        <label for="filter-payment">Betaling</label>
                        <select id="filter-payment" data-filter="payment" data-match="equals">
                            <option value="">Alle</option>
                            @foreach ($activity->registrations->map->paymentLabel()->unique()->sort() as $label)
                                <option value="{{ $label }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @if ($columns->isNotEmpty())
                    @if ($columns->count() > 3)
                        <button type="button" class="button button-ghost button-small" data-more-filters-toggle aria-expanded="false" aria-controls="more-filters">Meer filters</button>
                    @endif
                    <div class="filter-columns" id="more-filters" @if ($columns->count() > 3) data-more-filters hidden @endif>
                        @foreach ($columns as $column)
                            <div class="filter-field">
                                <label for="filter-col-{{ $column['id'] }}">{{ $column['label'] }}</label>
                                @if (($columnFilters[$column['id']]['options'] ?? null) !== null)
                                    <select id="filter-col-{{ $column['id'] }}" data-filter="col-{{ $column['id'] }}" data-match="equals">
                                        <option value="">Alle</option>
                                        @foreach ($columnFilters[$column['id']]['options'] as $option)
                                            <option value="{{ $option }}">{{ $option }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input id="filter-col-{{ $column['id'] }}" type="text" data-filter="col-{{ $column['id'] }}" data-match="contains" placeholder="bevat">
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
                <div class="filter-summary">
                    <span data-filter-count></span>
                    <button type="button" class="link-button" data-filter-reset>Filters wissen</button>
                </div>
            </div>

            <div class="table-scroll" tabindex="0" role="region" aria-label="Inschrijvingen">
                <table class="data-table registrations-table" data-registrations>
                    <thead>
                        <tr>
                            <th scope="col" class="select-cell" data-select-cell hidden>
                                <label class="checkbox"><input type="checkbox" data-select-all><span class="visually-hidden">Alles selecteren</span></label>
                            </th>
                            <th scope="col" data-sort="created" data-type="number">Ingeschreven op</th>
                            <th scope="col" data-sort="name" data-type="text">Naam</th>
                            <th scope="col" data-sort="present" data-type="number">Aanwezig</th>
                            <th scope="col" data-sort="email" data-type="text">E-mailadres</th>
                            @if ($activity->involvesMoney())
                                <th scope="col" data-sort="payment" data-type="text">Betaling</th>
                            @endif
                            @foreach ($columns as $column)
                                <th scope="col" data-sort="col-{{ $column['id'] }}" data-type="{{ $columnFilters[$column['id']]['sort'] ?? 'text' }}">{{ $column['label'] }}</th>
                            @endforeach
                            <th scope="col"><span class="visually-hidden">Acties</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($activity->registrations as $registration)
                            <tr id="inschrijving-{{ $registration->id }}" @class(['row-unconfirmed' => ! $registration->isConfirmed()])
                                data-row data-id="{{ $registration->id }}"
                                data-search="{{ $registration->name }} {{ $registration->email }}"
                                data-name="{{ $registration->name }}" data-email="{{ $registration->email }}"
                                data-created="{{ $registration->created_at->getTimestamp() }}"
                                data-present="{{ $registration->isPresent() ? 1 : 0 }}"
                                data-payment="{{ $registration->paymentLabel() }}"
                                @foreach ($columns as $column) data-col-{{ $column['id'] }}="{{ $registration->answerFor($column['id']) }}" @endforeach>
                                <td class="select-cell" data-select-cell hidden>
                                    <label class="checkbox"><input type="checkbox" value="{{ $registration->id }}" data-select-row><span class="visually-hidden">{{ $registration->name }} selecteren</span></label>
                                </td>
                                <td class="nowrap">{{ $registration->created_at->format('d/m/Y H:i') }}</td>
                                <td class="nowrap">{{ $registration->name }}</td>
                                <td class="nowrap">
                                    <div class="attendance">
                                    <form class="attendance-form" data-attendance-form method="POST" action="{{ route('activiteiten.aanwezigheid', [$activity, $registration->id]) }}">
                                        @csrf
                                        <input type="hidden" name="present" value="0">
                                        <label class="checkbox">
                                            <input type="checkbox" name="present" value="1" @checked($registration->isPresent()) data-autosubmit>
                                            <span class="visually-hidden">{{ $registration->name }} aanwezig</span>
                                        </label>
                                        <noscript><button type="submit" class="button button-ghost">Opslaan</button></noscript>
                                    </form>
                                    @if ($registration->certificate_sent_at)
                                        <span class="badge badge-small" title="Verstuurd op {{ $registration->certificate_sent_at->format('d/m/Y H:i') }}">attest verstuurd</span>
                                    @endif
                                    </div>
                                </td>
                                <td class="nowrap"><a href="mailto:{{ $registration->email }}">{{ $registration->email }}</a></td>
                                @if ($activity->involvesMoney())
                                    <td class="nowrap">
                                        <span @class(['badge', 'badge-open' => ! $registration->isConfirmed()])>{{ $registration->paymentLabel() }}</span>
                                        @unless ($registration->isFree())
                                            <span class="muted small">{{ $registration->amountLabel() }}</span>
                                        @endunless
                                        @if ($registration->canBeMarkedPaid())
                                            @include('activiteiten._mark-paid', ['registration' => $registration])
                                        @endif
                                    </td>
                                @endif
                                @foreach ($columns as $column)
                                    <td>{{ $registration->answerFor($column['id']) }}</td>
                                @endforeach
                                <td class="nowrap">
                                    <div class="row-actions">
                                        @if ($activity->hasCertificates() && $registration->isPresent() && $registration->isConfirmed())
                                            <a href="{{ route('activiteiten.attest', [$activity, $registration->id]) }}" class="button button-ghost button-small" download>Attest</a>
                                        @endif
                                        @include('activiteiten._cancel-registration', ['registration' => $registration])
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="selection-bar" data-selection-bar hidden role="region" aria-label="Selectie">
                <strong data-selection-count></strong>
                <form method="GET" action="{{ route('activiteiten.mail', $activity) }}" data-selection-form>
                    <button type="submit" class="button button-ghost button-small">Mailen</button>
                </form>
                <form method="POST" action="{{ route('activiteiten.aanwezigheid.alle', $activity) }}" data-selection-form>
                    @csrf
                    <input type="hidden" name="present" value="1">
                    <button type="submit" class="button button-ghost button-small">Aanwezig</button>
                </form>
                <form method="POST" action="{{ route('activiteiten.aanwezigheid.alle', $activity) }}" data-selection-form>
                    @csrf
                    <input type="hidden" name="present" value="0">
                    <button type="submit" class="button button-ghost button-small">Niet aanwezig</button>
                </form>
                @if ($activity->hasCertificates() && ! $activity->starts_at->isFuture())
                    <form method="POST" action="{{ route('activiteiten.attesten.versturen', $activity) }}" data-selection-form
                          data-confirm="Attesten versturen aan de selectie?"
                          data-confirm-text="Wie in de selectie aanwezig is en nog geen attest kreeg, krijgt het per e-mail."
                          data-confirm-button="Versturen"
                          data-confirm-tone="neutral">
                        @csrf
                        <button type="submit" class="button button-small">Attesten versturen</button>
                    </form>
                @endif
                <button type="button" class="link-button" data-selection-clear>Selectie wissen</button>
            </div>

            @if ($activity->involvesMoney())
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
            @endif
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

    <script src="{{ asset('js/registrations.js') }}?v={{ filemtime(public_path('js/registrations.js')) }}" defer></script>
@endsection
