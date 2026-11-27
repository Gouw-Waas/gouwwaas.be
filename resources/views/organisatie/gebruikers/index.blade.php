@extends('layouts.app')

@section('title', 'Gebruikers')

{{-- Organisatie → Gebruikers (admins). --}}
@section('content')
    <section class="page-header">
        <div class="container">
            <h1>Organisatie</h1>
        </div>
        @include('organisatie._tabs')
    </section>

    <section class="container section">
        <div class="section-actions">
            <a href="{{ route('gebruikers.create') }}" class="button">Gebruiker toevoegen</a>
        </div>

        @error('user')
            <p class="flash flash-error" role="alert">{{ $message }}</p>
        @enderror

        <ul class="user-list">
            @foreach ($users as $user)
                <li class="card user-row">
                    <div class="user-info">
                        <p class="user-name">
                            {{ $user->name }}
                            @if ($user->is_admin)
                                <span class="badge">Beheerder</span>
                            @endif
                            @if ($user->is(auth()->user()))
                                <span class="badge badge-muted">Jij</span>
                            @endif
                        </p>
                        <p class="muted">{{ $user->email }}</p>
                    </div>

                    <div class="user-actions">
                        <a href="{{ route('gebruikers.edit', $user) }}" class="button button-ghost button-small">Bewerken</a>

                        <form method="POST" action="{{ route('gebruikers.wachtwoord-reset', $user) }}"
                              data-confirm="Wachtwoord van {{ $user->name }} resetten?" data-confirm-button="Wachtwoord resetten"
                              data-confirm-text="Het huidige wachtwoord wordt meteen ongeldig en {{ $user->name }} wordt overal afgemeld. Er gaat een e-mail naar {{ $user->email }} om een nieuw wachtwoord te kiezen.">
                            @csrf
                            <button type="submit" class="button button-ghost button-small">Wachtwoord resetten</button>
                        </form>

                        @unless ($user->is(auth()->user()))
                            <form method="POST" action="{{ route('gebruikers.destroy', $user) }}"
                                  data-confirm="{{ $user->name }} verwijderen?" data-confirm-text="{{ $user->name }} kan niet meer inloggen. Activiteiten van deze gebruiker blijven bestaan; beheerders kunnen ze nog beheren.">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="button button-danger button-small">Verwijderen</button>
                            </form>
                        @endunless
                    </div>
                </li>
            @endforeach
        </ul>
    </section>
@endsection
