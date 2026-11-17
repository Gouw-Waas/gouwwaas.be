@extends('layouts.app')

@section('title', $block->exists ? $block->title.' bewerken' : 'Nieuw blok')

{{-- Admins adding or editing a text block on the home page. --}}
@section('content')
    <section class="page-header">
        <div class="container">
            <p><a href="{{ route('home') }}{{ $block->exists ? '#blok-'.$block->id : '' }}" class="back-link">Terug naar de homepagina</a></p>
            <h1>{{ $block->exists ? 'Blok bewerken' : 'Nieuw blok' }}</h1>
        </div>
    </section>

    <section class="container section">
        <form method="POST" action="{{ $block->exists ? route('blokken.update', $block) : route('blokken.store') }}" class="form activity-form">
            @csrf
            @if ($block->exists)
                @method('PUT')
            @endif

            @if ($errors->any())
                <p class="flash flash-error" role="alert">Niet alles is correct ingevuld. Bekijk de velden hieronder.</p>
            @endif

            <div class="card">
                <div class="field">
                    <label for="title">Titel</label>
                    <input id="title" type="text" name="title" value="{{ old('title', $block->title) }}" required maxlength="100" @error('title') aria-invalid="true" aria-describedby="title-error" @enderror>
                    @error('title')
                        <p class="error" id="title-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="body">Tekst</label>
                    <textarea id="body" name="body" rows="12" required maxlength="5000" aria-describedby="body-hint @error('body') body-error @enderror" @error('body') aria-invalid="true" @enderror>{{ old('body', $block->body) }}</textarea>
                    @error('body')
                        <p class="error" id="body-error">{{ $message }}</p>
                    @enderror
                    <div class="hint format-help" id="body-hint">
                        <p>Laat een lege regel tussen alinea's.</p>
                        <ul>
                            <li><code>**Herfstontmoeting**</code> wordt <strong>Herfstontmoeting</strong></li>
                            <li><code>*schuin*</code> wordt <em>schuin</em></li>
                            <li><code>[onze website](https://www.scoutsengidsenvlaanderen.be)</code> wordt een link</li>
                            <li>Regels die beginnen met <code>- </code> worden een opsomming</li>
                            <li><code>{{ \App\Models\PageBlock::GROUP_COUNT }}</code> wordt het aantal scoutsgroepen op de kaart (nu {{ \App\Models\ScoutGroup::count() }})</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="button">{{ $block->exists ? 'Opslaan' : 'Blok toevoegen' }}</button>
                <a href="{{ route('home') }}{{ $block->exists ? '#blok-'.$block->id : '' }}" class="button button-ghost">Annuleren</a>
            </div>
        </form>

        @if ($block->exists)
            <form method="POST" action="{{ route('blokken.destroy', $block) }}" class="danger-zone"
                  data-confirm="&quot;{{ $block->title }}&quot; verwijderen?"
                  data-confirm-text="Het blok verdwijnt van de homepagina. Dit kan niet ongedaan gemaakt worden."
                  data-confirm-button="Blok verwijderen" data-confirm-cancel="Terug">
                @csrf
                @method('DELETE')
                <button type="submit" class="button button-danger">Blok verwijderen</button>
            </form>
        @endif
    </section>
@endsection
