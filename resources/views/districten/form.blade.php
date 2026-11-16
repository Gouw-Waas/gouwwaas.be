@extends('layouts.app')

@section('title', $group->exists ? $group->name.' bewerken' : 'Nieuwe groep')

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
@endpush

@section('content')
    <section class="page-header">
        <div class="container">
            <p>
                <a href="{{ route('districten') }}{{ $group->exists ? '#'.$group->anchor() : '' }}" class="back-link">Terug naar de kaart</a>
            </p>
            <h1>{{ $group->exists ? $group->name.' bewerken' : 'Nieuwe groep' }}</h1>
        </div>
    </section>

    <section class="container section">
        <form method="POST" action="{{ $group->exists ? route('groepen.update', $group) : route('groepen.store') }}" enctype="multipart/form-data" class="form activity-form">
            @csrf
            @if ($group->exists)
                @method('PUT')
            @endif

            @if ($errors->any())
                <p class="flash flash-error" role="alert">Niet alles is correct ingevuld. Bekijk de velden hieronder.</p>
            @endif

            <div class="card">
                <h2>Over de groep</h2>

                <div class="field">
                    <label for="name">Naam</label>
                    <input id="name" type="text" name="name" value="{{ old('name', $group->name) }}" required maxlength="255" placeholder="bv. Sint-Jozef" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                    @error('name')
                        <p class="error" id="name-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-grid">
                    <div class="field">
                        <label for="number">Groepsnummer</label>
                        <input id="number" type="text" name="number" value="{{ old('number', $group->number) }}" required maxlength="6" placeholder="bv. O3103G" autocapitalize="characters" @error('number') aria-invalid="true" aria-describedby="number-error" @enderror>
                        @error('number')
                            <p class="error" id="number-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="type">Type</label>
                        <select id="type" name="type" required>
                            @foreach (\App\Models\ScoutGroup::TYPES as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', $group->type) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label for="district">District</label>
                        <select id="district" name="district" required @error('district') aria-invalid="true" @enderror>
                            <option value="" disabled @selected(! old('district', $group->district))>Kies een district</option>
                            @foreach (\App\Models\ScoutGroup::districts() as $key => $district)
                                <option value="{{ $key }}" @selected(old('district', $group->district) === $key)>{{ $district['name'] }}</option>
                            @endforeach
                        </select>
                        @error('district')
                            <p class="error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="website">Website <span class="muted">(optioneel)</span></label>
                        <input id="website" type="url" name="website" value="{{ old('website', $group->website) }}" maxlength="255" placeholder="https://…" @error('website') aria-invalid="true" aria-describedby="website-error" @enderror>
                        @error('website')
                            <p class="error" id="website-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="card">
                <h2>Logo</h2>
                <p class="muted">Verschijnt rechtsboven in de zijbalk van de groep op de kaart.</p>
                @include('districten._logo-field', ['logoUrl' => $group->exists ? $group->logoUrl() : null])
            </div>

            <div class="card">
                <h2>Lokaal</h2>

                <div class="field">
                    <label for="street">Straat en nummer</label>
                    <input id="street" type="text" name="street" value="{{ old('street', $group->street) }}" required maxlength="255" @error('street') aria-invalid="true" aria-describedby="street-error" @enderror>
                    @error('street')
                        <p class="error" id="street-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-grid form-grid-address">
                    <div class="field">
                        <label for="postal_code">Postcode</label>
                        <input id="postal_code" type="text" name="postal_code" value="{{ old('postal_code', $group->postal_code) }}" required inputmode="numeric" maxlength="4" @error('postal_code') aria-invalid="true" aria-describedby="postal-error" @enderror>
                        @error('postal_code')
                            <p class="error" id="postal-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="city">Gemeente</label>
                        <input id="city" type="text" name="city" value="{{ old('city', $group->city) }}" required maxlength="255" @error('city') aria-invalid="true" aria-describedby="city-error" @enderror>
                        @error('city')
                            <p class="error" id="city-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="field">
                    <span class="label">Locatie op de kaart</span>
                    <p class="hint" id="location-hint">
                        Klik op de kaart of sleep de markering naar het lokaal. "Zoek adres" zoekt het adres hierboven op
                        (via OpenStreetMap).
                    </p>
                    <div class="location-picker" id="location-map" aria-describedby="location-hint"></div>
                    <div class="location-actions">
                        <button type="button" class="button button-ghost button-small" data-geocode>Zoek adres</button>
                        <button type="button" class="link-button small" data-clear-location>Locatie wissen</button>
                        <span class="muted small" data-location-status role="status"></span>
                    </div>
                    <input type="hidden" name="latitude" value="{{ old('latitude', $group->latitude) }}" data-lat>
                    <input type="hidden" name="longitude" value="{{ old('longitude', $group->longitude) }}" data-lng>
                    @error('latitude')
                        <p class="error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="button">{{ $group->exists ? 'Opslaan' : 'Groep toevoegen' }}</button>
                <a href="{{ route('districten') }}{{ $group->exists ? '#'.$group->anchor() : '' }}" class="button button-ghost">Annuleren</a>
            </div>
        </form>

        @if ($group->exists)
            <form method="POST" action="{{ route('groepen.destroy', $group) }}" class="danger-zone"
                  onsubmit="return confirm({{ Js::from("\"{$group->name}\" definitief verwijderen?") }})">
                @csrf
                @method('DELETE')
                <button type="submit" class="button button-danger">Groep verwijderen</button>
            </form>
        @endif
    </section>

    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    <script>
        (() => {
            const lat = document.querySelector('[data-lat]');
            const lng = document.querySelector('[data-lng]');
            const status = document.querySelector('[data-location-status]');
            const has = () => lat.value !== '' && lng.value !== '';

            const map = L.map('location-map', { scrollWheelZoom: false });
            L.tileLayer(
                'https://geo.api.vlaanderen.be/GRB/wmts?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0&LAYER=grb_bsk_grijs&STYLE=&FORMAT=image/png&TILEMATRIXSET=GoogleMapsVL&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}',
                { minZoom: 8, maxZoom: 19, attribution: '&copy; Digitaal Vlaanderen' },
            ).addTo(map);
            map.on('click focus', () => map.scrollWheelZoom.enable());

            let marker = null;
            const place = (latlng, zoom) => {
                if (! marker) {
                    marker = L.marker(latlng, { draggable: true, autoPan: true }).addTo(map);
                    marker.on('dragend', () => place(marker.getLatLng()));
                }
                marker.setLatLng(latlng);
                lat.value = latlng.lat.toFixed(7);
                lng.value = latlng.lng.toFixed(7);
                if (zoom) map.setView(latlng, zoom);
                status.textContent = 'Locatie ingesteld.';
            };

            if (has()) {
                place(L.latLng(+lat.value, +lng.value), 16);
                status.textContent = '';
            } else {
                map.fitBounds([[51.0, 3.95], [51.3, 4.35]]); // Waasland
                status.textContent = 'Nog geen locatie.';
            }

            map.on('click', (event) => place(event.latlng));

            document.querySelector('[data-clear-location]').addEventListener('click', () => {
                if (marker) { marker.remove(); marker = null; }
                lat.value = lng.value = '';
                status.textContent = 'Locatie gewist: de groep staat niet op de kaart.';
            });

            document.querySelector('[data-geocode]').addEventListener('click', async () => {
                const address = ['street', 'postal_code', 'city'].map((id) => document.getElementById(id).value.trim()).filter(Boolean).join(', ');
                if (! address) { status.textContent = 'Vul eerst het adres in.'; return; }

                status.textContent = 'Zoeken…';
                try {
                    const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=be&q=' + encodeURIComponent(address);
                    const results = await (await fetch(url, { headers: { 'Accept-Language': 'nl' } })).json();
                    if (results.length) {
                        place(L.latLng(+results[0].lat, +results[0].lon), 17);
                        status.textContent = 'Gevonden. Controleer de markering en sleep ze indien nodig.';
                    } else {
                        status.textContent = 'Adres niet gevonden. Klik op de kaart om de locatie te kiezen.';
                    }
                } catch (e) {
                    status.textContent = 'Zoeken lukte niet. Klik op de kaart om de locatie te kiezen.';
                }
            });
        })();
    </script>
@endsection
