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
                                    <form method="POST" action="{{ route('activiteiten.inschrijvingen.destroy', [$activity, $registration]) }}"
                                          data-confirm="Inschrijving van {{ $registration->name }} verwijderen?"
                                          data-confirm-details="verwijder-inschrijving-{{ $registration->id }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="button button-danger button-small">Verwijderen</button>
                                    </form>
                                    @include('activiteiten._delete-registration-warning', ['registration' => $registration])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
