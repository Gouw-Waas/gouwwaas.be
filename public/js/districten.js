/*
 * The map on /districten (Leaflet).
 *
 * - One marker per scouting group, in its district's colour.
 * - District outlines from config/gouw.php (boundary), when they are filled in.
 * - Clicking a marker, outline or any [data-select] link shows that panel in the
 *   sidebar. The panels themselves are rendered by the server.
 * - The selection is kept in the address (#groep-o3101g, #district-klaas), so it can be shared.
 */
(() => {
    const data = window.districtMap;
    const mapElement = document.getElementById('map');
    const sidebar = document.querySelector('[data-sidebar]');

    if (! data || ! mapElement || ! window.L) {
        return;
    }

    const OVERVIEW = 'overzicht';
    const colors = Object.fromEntries(data.districts.map((d) => [d.key, d.color]));
    const panels = new Map([...sidebar.querySelectorAll('[data-panel]')].map((p) => [p.dataset.panel, p]));

    // Flemish government base map (grey), calm enough for coloured markers on top.
    const map = L.map(mapElement, {
        scrollWheelZoom: false, // scrolling the page shouldn't zoom the map; click the map first
        // Whole zoom steps only: at half steps the base map's tiles get resampled,
        // which blurs the labels and shows faint lines along the tile edges.
        maxBounds: [[50.4, 2.3], [51.7, 6.2]],
        maxBoundsViscosity: 0.8,
    });

    L.tileLayer(
        'https://geo.api.vlaanderen.be/GRB/wmts?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0&LAYER=grb_bsk_grijs&STYLE=&FORMAT=image/png&TILEMATRIXSET=GoogleMapsVL&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}',
        {
            minZoom: 8,
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.vlaanderen.be/digitaal-vlaanderen" target="_blank" rel="noopener">Digitaal Vlaanderen</a>',
        },
    ).addTo(map);

    map.on('click focus', () => map.scrollWheelZoom.enable());
    mapElement.addEventListener('mouseleave', () => map.scrollWheelZoom.disable());

    // District outlines (once they exist in the config).
    const outlines = new Map();
    data.districts.filter((d) => d.boundary).forEach((d) => {
        const outline = L.polygon(d.boundary, {
            color: d.color, weight: 2, fillColor: d.color, fillOpacity: 0.08,
        }).addTo(map);
        outline.bindTooltip(`District ${d.name}`, { sticky: true });
        outline.on('click', () => select(`district-${d.key}`));
        outlines.set(d.key, outline);
    });

    // Group markers.
    const markerStyle = (group, state) => ({
        radius: state === 'selected' ? 11 : 8,
        color: state === 'selected' ? '#1f2408' : '#ffffff',
        weight: state === 'selected' ? 3 : 2,
        fillColor: colors[group.district] || '#5c6a12',
        fillOpacity: state === 'dimmed' ? 0.35 : 1,
        opacity: state === 'dimmed' ? 0.5 : 1,
    });

    const markers = new Map();
    data.groups.forEach((group) => {
        const marker = L.circleMarker([group.lat, group.lng], markerStyle(group, 'normal')).addTo(map);
        marker.bindTooltip(group.name, { direction: 'top', offset: [0, -8] });
        marker.on('click', () => select(group.id));
        markers.set(group.id, { marker, group });
    });

    const allBounds = () => {
        const layers = [...markers.values()].map((m) => m.marker).concat([...outlines.values()]);
        return layers.length ? L.featureGroup(layers).getBounds() : L.latLngBounds([[51.0, 3.9], [51.3, 4.4]]);
    };

    map.fitBounds(allBounds(), { padding: [20, 20] });

    // Selection
    let current = OVERVIEW;

    function styleMarkers(selectedId, district) {
        markers.forEach(({ marker, group }) => {
            let state = 'normal';
            if (group.id === selectedId) state = 'selected';
            else if (district && group.district !== district) state = 'dimmed';
            marker.setStyle(markerStyle(group, state));
            if (state === 'selected') marker.bringToFront();
        });
        outlines.forEach((outline, key) => outline.setStyle({
            fillOpacity: district === key ? 0.16 : 0.08,
            weight: district === key ? 3 : 2,
        }));
    }

    function select(id, { focus = true, updateHash = true } = {}) {
        if (! panels.has(id)) id = OVERVIEW;
        current = id;

        panels.forEach((panel, key) => { panel.hidden = key !== id; });
        sidebar.scrollTop = 0;

        if (id.startsWith('groep-')) {
            const entry = markers.get(id);
            const district = entry ? entry.group.district : null;
            styleMarkers(id, district);
            if (entry) map.flyTo(entry.marker.getLatLng(), Math.max(map.getZoom(), 14), { duration: 0.6 });
        } else if (id.startsWith('district-')) {
            const key = id.slice('district-'.length);
            styleMarkers(null, key);
            const layers = [...markers.values()].filter((m) => m.group.district === key).map((m) => m.marker);
            if (outlines.has(key)) layers.push(outlines.get(key));
            if (layers.length) map.flyToBounds(L.featureGroup(layers).getBounds(), { padding: [40, 40], maxZoom: 14, duration: 0.6 });
        } else {
            styleMarkers(null, null);
            map.flyToBounds(allBounds(), { padding: [20, 20], duration: 0.6 });
        }

        if (updateHash) {
            const url = id === OVERVIEW ? location.pathname + location.search : `#${id}`;
            history.replaceState(null, '', url);
        }

        if (focus) {
            const title = panels.get(id).querySelector('.map-panel-title');
            if (title) title.focus({ preventScroll: true });

            // Phones: the sidebar is below the map, bring it into view.
            const rect = sidebar.getBoundingClientRect();
            if (rect.top > window.innerHeight || rect.bottom < 0) {
                sidebar.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    }

    // Links anywhere on the page (sidebar, legend, list below the map).
    document.addEventListener('click', (event) => {
        const link = event.target.closest('[data-select]');
        if (! link) return;
        event.preventDefault();

        const fromList = ! sidebar.contains(link);
        select(link.dataset.select, { focus: ! fromList });

        if (fromList) {
            mapElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    window.addEventListener('hashchange', () => select(location.hash.slice(1), { focus: false, updateHash: false }));

    // Opened with a link such as /districten#district-klaas.
    if (location.hash) {
        select(location.hash.slice(1), { focus: false, updateHash: false });
        mapElement.scrollIntoView({ block: 'center' });
    }
})();
