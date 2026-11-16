{{-- Sidebar panel of a district: its districtsploeg (from /wie-is-wie) and its groups. --}}
<a href="#" class="map-panel-back" data-select="overzicht">Alle districten</a>

<header @class(['map-panel-header', 'has-logo' => $district['logo']])>
    <div>
        <p class="map-panel-kicker">District</p>
        <h2 class="map-panel-title" tabindex="-1">{{ $district['name'] }}</h2>
        <p class="muted">{{ $district['groups']->count() }} {{ $district['groups']->count() === 1 ? 'scoutsgroep' : 'scoutsgroepen' }}</p>
    </div>
    @if ($district['logo'])
        <img src="{{ $district['logo'] }}" alt="Logo van district {{ $district['name'] }}" class="map-panel-logo" loading="lazy">
    @endif
</header>

@if ($team = $district['team'])
    @if (filled($team->description))
        <div class="map-panel-text">{!! $team->descriptionHtml() !!}</div>
    @endif

    <h3 class="map-panel-subtitle">Districtsploeg</h3>
    @include('wie-is-wie._people', ['role' => $team])
    @include('wie-is-wie._contact', ['role' => $team])
@endif

<h3 class="map-panel-subtitle">Groepen</h3>
@if ($district['groups']->isNotEmpty())
    <ul class="map-panel-groups">
        @foreach ($district['groups'] as $group)
            <li>
                <a href="#{{ $group->anchor() }}" data-select="{{ $group->anchor() }}">
                    <span class="legend-dot" aria-hidden="true"></span>
                    <span class="legend-name">{{ $group->name }}</span>
                    <span class="muted">{{ $group->city }}</span>
                </a>
            </li>
        @endforeach
    </ul>
@else
    <p class="muted">Nog geen groepen.</p>
@endif

@can('admin')
    <p class="map-panel-admin">
        <a href="{{ route('districten.edit', $district['key']) }}" class="button button-ghost button-small">Logo wijzigen</a>
        <a href="{{ route('groepen.create', ['district' => $district['key']]) }}" class="button button-ghost button-small">Groep toevoegen</a>
    </p>
@endcan
