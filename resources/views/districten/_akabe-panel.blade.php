{{-- Sidebar panel with all groups that have Akabe. --}}
<a href="#" class="map-panel-back" data-select="overzicht">Alle districten</a>

<header class="map-panel-header">
    <div>
        <p class="map-panel-kicker">Anders KAn BEter</p>
        <h2 class="map-panel-title" tabindex="-1">Akabe</h2>
        <p class="muted">{{ $akabeGroups->count() }} {{ $akabeGroups->count() === 1 ? 'scoutsgroep' : 'scoutsgroepen' }}</p>
    </div>
</header>

<div class="map-panel-text">
    <p>
        In Akabe beleven kinderen en jongeren met een beperking scouting op hun eigen tempo.
        Sommige groepen zijn helemaal Akabe, andere hebben een Akabetak naast de gewone takken.
    </p>
</div>

<ul class="akabe-key" aria-label="Legende">
    <li><span class="legend-ring" aria-hidden="true"></span> {{ \App\Models\ScoutGroup::AKABE['groep'] }}</li>
    <li><span class="legend-ring legend-ring-dashed" aria-hidden="true"></span> {{ \App\Models\ScoutGroup::AKABE['tak'] }}</li>
</ul>

<h3 class="map-panel-subtitle">Groepen</h3>
<ul class="map-panel-groups">
    @foreach ($akabeGroups as $group)
        <li>
            <a href="#{{ $group->anchor() }}" data-select="{{ $group->anchor() }}" style="--district: {{ config("gouw.districts.{$group->district}.color") }}">
                <span @class(['legend-dot', 'legend-dot-akabe', 'is-tak' => $group->akabe === 'tak']) aria-hidden="true"></span>
                <span class="legend-name">{{ $group->name }}</span>
                <span class="muted">{{ $group->akabeLabel() }}</span>
            </a>
        </li>
    @endforeach
</ul>
