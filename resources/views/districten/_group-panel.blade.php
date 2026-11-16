{{-- Sidebar panel of a scouting group. --}}
<a href="#district-{{ $district['key'] }}" class="map-panel-back" data-select="district-{{ $district['key'] }}">District {{ $district['name'] }}</a>

<header @class(['map-panel-header', 'has-logo' => $logo = $group->logoUrl()])>
    <div>
        <p class="map-panel-kicker">{{ $group->typeLabel() }}</p>
        <h2 class="map-panel-title" tabindex="-1">{{ $group->name }}</h2>
    </div>
    @if ($logo)
        <img src="{{ $logo }}" alt="Logo van {{ $group->name }}" class="map-panel-logo" loading="lazy">
    @endif
</header>

<dl class="map-panel-details">
    <div>
        <dt>Groepsnummer</dt>
        <dd>{{ $group->number }}</dd>
    </div>
    <div>
        <dt>Lokaal</dt>
        <dd>
            {{ $group->street }}<br>
            {{ $group->postal_code }} {{ $group->city }}
        </dd>
    </div>
    @if ($group->website)
        <div>
            <dt>Website</dt>
            <dd><a href="{{ $group->website }}" target="_blank" rel="noopener">{{ $group->websiteLabel() }}<span class="visually-hidden"> (opent in een nieuw tabblad)</span></a></dd>
        </div>
    @endif
</dl>

@unless ($group->hasLocation())
    <p class="muted small">Deze groep staat nog niet op de kaart.</p>
@endunless

<div class="map-panel-actions">
    @if ($group->website)
        <a href="{{ $group->website }}" class="button button-small" target="_blank" rel="noopener">Naar de website</a>
    @endif
    <a href="{{ $group->directionsUrl() }}" class="button button-ghost button-small" target="_blank" rel="noopener">Route plannen</a>
</div>

@can('admin')
    <p class="map-panel-admin">
        <a href="{{ route('groepen.edit', $group) }}" class="button button-ghost button-small">Bewerken</a>
    </p>
@endcan
