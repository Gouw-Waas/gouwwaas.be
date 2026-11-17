@extends('layouts.app')

@section('title', 'Activiteiten')

@section('content')
    <section class="page-header">
        <div class="container page-header-row">
            <h1>Activiteiten</h1>
            @auth
                <a href="{{ route('activiteiten.create') }}" class="button">Nieuwe activiteit</a>
            @endauth
        </div>
    </section>

    <section class="container section">
        @error('archive')
            <p class="flash flash-error" role="alert">{{ $message }}</p>
        @enderror

        @if ($upcoming->isEmpty())
            <div class="empty-state">
                <p>Er staan momenteel geen activiteiten gepland. Kom binnenkort nog eens kijken!</p>
            </div>
        @else
            <div class="activity-list">
                @foreach ($upcoming as $activity)
                    @include('activiteiten._card', ['activity' => $activity])
                @endforeach
            </div>
        @endif
    </section>

    @if ($mine->isNotEmpty())
        <section class="section section-tinted">
            <div class="container">
                <div class="section-heading">
                    <h2>Mijn activiteiten</h2>
                    <p>Activiteiten die jij hebt aangemaakt.</p>
                </div>

                <ul class="user-list">
                    @foreach ($mine as $activity)
                        <li class="card user-row">
                            <div class="user-info">
                                <p class="user-name"><a href="{{ route('activiteiten.show', $activity) }}">{{ $activity->title }}</a></p>
                                <p class="muted">{{ $activity->starts_at->translatedFormat('j F Y') }} · {{ $activity->registrations_count }} {{ $activity->registrations_count === 1 ? 'inschrijving' : 'inschrijvingen' }}</p>
                            </div>
                            <div class="user-actions">
                                <a href="{{ route('activiteiten.inschrijvingen', $activity) }}" class="button button-ghost button-small">Inschrijvingen</a>
                                <a href="{{ route('activiteiten.edit', $activity) }}" class="button button-ghost button-small">Bewerken</a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- Only for admins: past activities, with the option to archive (delete permanently). --}}
    @if ($past->isNotEmpty())
        <section class="container section">
            <div class="section-heading">
                <h2>Voorbije activiteiten</h2>
                <p>Alleen zichtbaar voor beheerders. Archiveren verwijdert een activiteit en haar inschrijvingen definitief.</p>
            </div>
            <div class="activity-list activity-list-past">
                @foreach ($past as $activity)
                    <div class="activity-archive-row">
                        @include('activiteiten._card', ['activity' => $activity])
                        <form method="POST" action="{{ route('activiteiten.archive', $activity) }}"
                              data-confirm="&quot;{{ $activity->title }}&quot; archiveren?"
                              data-confirm-text="{{ match ($activity->registrations_count) {
                                  0 => 'De activiteit wordt definitief verwijderd. Er zijn geen inschrijvingen.',
                                  1 => 'De activiteit en de enige inschrijving worden definitief verwijderd. Er wordt niets terugbetaald en niemand krijgt een e-mail.',
                                  default => "De activiteit en alle {$activity->registrations_count} inschrijvingen worden definitief verwijderd. Er wordt niets terugbetaald en niemand krijgt een e-mail.",
                              } }}"
                              data-confirm-button="Archiveren" data-confirm-cancel="Terug">
                            @csrf
                            <button type="submit" class="button button-danger button-small">Archiveren</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
@endsection
