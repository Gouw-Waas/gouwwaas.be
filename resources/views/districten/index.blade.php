@extends('layouts.app')

@section('title', 'Districten')

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
@endpush

@section('content')
    <section class="page-header">
        <div class="container page-header-row">
            <div>
                <h1>Districten</h1>
                <p class="lead">
                    Gouw Waas bestaat uit vier districten, die samen {{ $groupCount }} scoutsgroepen
                    vertegenwoordigen verspreid over het ruime Waasland.
                </p>
            </div>
            @can('admin')
                <a href="{{ route('groepen.create') }}" class="button">Groep toevoegen</a>
            @endcan
        </div>
    </section>

    <section class="container section">
        <div class="map-layout" data-map-root>
            <div class="map" id="map" role="region" aria-label="Kaart van de districten en scoutsgroepen"></div>

            {{-- One panel per district and per group; the map script shows the selected one. --}}
            <aside class="map-sidebar" aria-label="Informatie" data-sidebar>
                <section class="map-panel" data-panel="overzicht">
                    <h2 class="map-panel-title" tabindex="-1">Klik op de kaart</h2>
                    <p class="muted">
                        Kies een groep op de kaart of een district hieronder om meer te weten te komen.
                    </p>
                    <ul class="district-legend">
                        @foreach ($districts as $district)
                            <li>
                                <a href="#district-{{ $district['key'] }}" data-select="district-{{ $district['key'] }}" style="--district: {{ $district['color'] }}">
                                    <span class="legend-dot" aria-hidden="true"></span>
                                    <span class="legend-name">District {{ $district['name'] }}</span>
                                    <span class="muted">{{ $district['groups']->count() }} {{ $district['groups']->count() === 1 ? 'groep' : 'groepen' }}</span>
                                </a>
                            </li>
                        @endforeach
                        @if ($akabeGroups->isNotEmpty())
                            <li class="district-legend-akabe">
                                <a href="#akabe" data-select="akabe">
                                    <span class="legend-ring" aria-hidden="true"></span>
                                    <span class="legend-name">Akabe</span>
                                    <span class="muted">{{ $akabeGroups->count() }} {{ $akabeGroups->count() === 1 ? 'groep' : 'groepen' }}</span>
                                </a>
                            </li>
                        @endif
                    </ul>
                </section>

                @if ($akabeGroups->isNotEmpty())
                    <section class="map-panel" data-panel="akabe" hidden>
                        @include('districten._akabe-panel')
                    </section>
                @endif

                @foreach ($districts as $district)
                    <section class="map-panel" data-panel="district-{{ $district['key'] }}" style="--district: {{ $district['color'] }}" hidden>
                        @include('districten._district-panel', ['district' => $district])
                    </section>

                    @foreach ($district['groups'] as $group)
                        <section class="map-panel" data-panel="{{ $group->anchor() }}" style="--district: {{ $district['color'] }}" hidden>
                            @include('districten._group-panel', ['group' => $group, 'district' => $district])
                        </section>
                    @endforeach
                @endforeach
            </aside>
        </div>
    </section>

    {{-- The same information as a list: for keyboards, screen readers and search engines. --}}
    <section class="section section-tinted">
        <div class="container">
            <div class="section-heading">
                <h2>Alle groepen</h2>
                <p>Klik op een groep om ze op de kaart te tonen.</p>
            </div>

            <div class="districts">
                @foreach ($districts as $district)
                    <article class="district" id="lijst-district-{{ $district['key'] }}" style="--district: {{ $district['color'] }}">
                        <header class="district-header">
                            <h3><a href="#district-{{ $district['key'] }}" data-select="district-{{ $district['key'] }}">District {{ $district['name'] }}</a></h3>
                            <span class="badge">{{ $district['groups']->count() }} {{ $district['groups']->count() === 1 ? 'groep' : 'groepen' }}</span>
                        </header>
                        <ul class="group-list">
                            @forelse ($district['groups'] as $group)
                                <li>
                                    <a href="#{{ $group->anchor() }}" class="group-row" data-select="{{ $group->anchor() }}">
                                        <span class="group-name">{{ $group->name }}@include('districten._akabe-badge')</span>
                                        <span class="group-town">{{ $group->city }}</span>
                                    </a>
                                </li>
                            @empty
                                <li class="muted group-row">Nog geen groepen.</li>
                            @endforelse
                        </ul>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    <script nonce="{{ $cspNonce ?? '' }}">window.districtMap = @json($mapData);</script>
    <script src="{{ asset('js/districten.js') }}?v={{ filemtime(public_path('js/districten.js')) }}"></script>
@endsection
