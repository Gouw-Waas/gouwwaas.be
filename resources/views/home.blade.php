@extends('layouts.app')

@section('title', 'Home')

@section('content')
    <section class="hero">
        <div class="container hero-inner">
            <div class="hero-text">
                <h1>Welkom bij Gouw&nbsp;Waas!</h1>
                <p class="lead">
                    Vier districten, {{ $groupCount }} scoutsgroepen
                    en één thuisbasis: gouwhuis Karree in Sint-Niklaas.
                </p>
                <div class="hero-actions">
                    <a href="{{ route('districten') }}" class="button button-accent">Onze districten</a>
                    <a href="{{ route('wie-is-wie') }}" class="button button-outline-light">Wie is wie</a>
                </div>
            </div>
            <img src="{{ asset('images/logo-600.webp') }}"
                 srcset="{{ asset('images/logo-600.webp') }} 600w, {{ asset('images/logo-1200.webp') }} 1200w"
                 sizes="(max-width: 860px) 70vw, 480px"
                 alt="" class="hero-logo" width="600" height="433">
        </div>
    </section>

    @if ($blocks->isNotEmpty() || auth()->user()?->is_admin)
    <section class="container section">
        <div class="intro-grid">
            @foreach ($blocks as $block)
                <article class="prose page-block" id="blok-{{ $block->id }}">
                    <div class="page-block-header">
                        <h2>{{ $block->title }}</h2>
                        @can('admin')
                            <a href="{{ route('blokken.edit', $block) }}" class="button button-ghost button-small page-block-edit" aria-label="{{ $block->title }} bewerken">Bewerken</a>
                        @endcan
                    </div>
                    {!! $block->bodyHtml() !!}
                </article>
            @endforeach
        </div>
        @can('admin')
            <p class="page-block-add">
                <a href="{{ route('blokken.create') }}" class="button button-ghost button-small">+ Blok toevoegen</a>
            </p>
        @endcan
    </section>
    @endif

    <section class="container section section-flush-top">
        <div class="cta">
            <div class="cta-text">
                <h2>Interessant, kan ik ook bij de gouw komen?</h2>
                <p>
                    Zit jouw leidingscarrière erop, maar wil je nog geen afscheid nemen van scouting? Of heb je nood
                    aan een extra uitdaging buiten het engagement bij jouw groep? Perfect, wij kunnen altijd
                    enthousiaste nieuwe mensen gebruiken!
                </p>
                <p>
                    Neem zeker contact op met iemand van de gouw (via de knoppen hieronder of rechtstreeks via een
                    lid) en dan kijken we samen met jou wat je in onze gouw kan betekenen.
                </p>
            </div>
            <div class="cta-actions">
                <a href="mailto:gouwbureau@gouwwaas.be" class="button button-accent">Stuur ons een mailtje</a>
                <a href="https://www.instagram.com/gouw.waas/" class="button button-outline-light" rel="noopener">Stuur een bericht op Instagram</a>
            </div>
        </div>
    </section>
@endsection
