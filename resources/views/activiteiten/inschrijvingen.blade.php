@extends('layouts.app')

@section('title', 'Inschrijvingen · '.$activity->title)

@section('content')
    <section class="page-header">
        <div class="container">
            <p><a href="{{ route('activiteiten.show', $activity) }}" class="back-link">{{ $activity->title }}</a></p>
            <div class="page-header-row">
                <h1>Inschrijvingen</h1>
                @if ($activity->registrations->isNotEmpty())
                    <a href="{{ route('activiteiten.inschrijvingen.export', $activity) }}" class="button">Download (Excel)</a>
                @endif
            </div>
            @php
                $confirmed = $activity->registrations->filter->isConfirmed()->count();
                $unfinished = $activity->registrations->count() - $confirmed;
            @endphp
            <p class="lead">
                {{ $confirmed }} {{ $confirmed === 1 ? 'inschrijving' : 'inschrijvingen' }}
                @if ($unfinished)
                    <span class="muted">(+ {{ $unfinished }} niet betaald)</span>
                @endif
                · {{ $activity->dateLabel() }}
            </p>
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
                                <td class="nowrap"><a href="mailto:{{ $registration->email }}">{{ $registration->email }}</a></td>
                                @unless ($activity->isFree() && $activity->registrations->every->isFree())
                                    <td class="nowrap">
                                        <span @class(['badge', 'badge-open' => ! $registration->isConfirmed()])>{{ $registration->paymentLabel() }}</span>
                                        @unless ($registration->isFree())
                                            <span class="muted small">{{ $registration->amountLabel() }}</span>
                                        @endunless
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
        @endif
    </section>

    <script>
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
